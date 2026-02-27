<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BomRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'production-bom-register';
    }

    public function name(): string
    {
        return 'BOM Register';
    }

    public function module(): string
    {
        return 'Production';
    }

    public function description(): ?string
    {
        return 'Bill of Materials (BOM) list by project/status.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable','date'],
            'to_date' => ['nullable','date','after_or_equal:from_date'],
            'project_id' => ['nullable','integer','exists:projects,id'],
            'status' => ['nullable','string','max:30'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id','code','name']);

        $statusOptions = DB::table('boms')
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->all();

        return [
            ['name'=>'from_date','label'=>'Created From','type'=>'date','col'=>2],
            ['name'=>'to_date','label'=>'Created To','type'=>'date','col'=>2],
            [
                'name'=>'project_id','label'=>'Project','type'=>'select','col'=>4,
                'options'=>collect($projects)->map(fn($p)=>['value'=>$p->id,'label'=>trim(($p->code?$p->code.' - ':'').$p->name)])->all(),
            ],
            [
                'name'=>'status','label'=>'Status','type'=>'select','col'=>2,
                'options'=>collect($statusOptions)->map(fn($s)=>['value'=>$s,'label'=>strtoupper($s)])->all(),
            ],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'BOM No'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'BOM No','value'=>'bom_number','w'=>'22%'],
            ['label'=>'Version','align'=>'right','value'=>fn($r)=>(int)($r->version??0),'w'=>'6%'],
            ['label'=>'Project','value'=>fn($r)=>trim(($r->project_code?$r->project_code.' - ':'').($r->project_name??'')),'w'=>'28%'],
            ['label'=>'Status','value'=>fn($r)=>strtoupper((string)$r->status),'w'=>'10%'],
            [
                'label'=>'Total Weight','align'=>'right','w'=>'12%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->total_weight??0):number_format((float)($r->total_weight??0),3),
            ],
            ['label'=>'Finalized At','value'=>'finalized_date','w'=>'14%'],
            ['label'=>'Created At','value'=>'created_at','w'=>'14%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'created_at','direction'=>'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('boms as b')
            ->leftJoin('projects as p','p.id','=','b.project_id')
            ->select([
                'b.id',
                'b.bom_number',
                'b.version',
                'b.status',
                'b.total_weight',
                'b.finalized_date',
                'b.created_at',
                'p.code as project_code',
                'p.name as project_name',
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('b.created_at','>=',$filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('b.created_at','<=',$filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('b.project_id', $filters['project_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('b.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where('b.bom_number','like',"%{$term}%");
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_weight),0) as w')
            ->first();

        return [
            ['label'=>'BOMs','value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Total Weight','value'=>number_format((float)($row->w ?? 0),3)],
        ];
    }
}
