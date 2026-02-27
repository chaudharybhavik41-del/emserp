<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreReturnRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'stores-return-register';
    }

    public function name(): string
    {
        return 'Store Return Register';
    }

    public function module(): string
    {
        return 'Stores';
    }

    public function description(): ?string
    {
        return 'Material returns from contractors (returned/damaged quantities).';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable','date'],
            'to_date' => ['nullable','date','after_or_equal:from_date'],
            'project_id' => ['nullable','integer','exists:projects,id'],
            'contractor_party_id' => ['nullable','integer','exists:parties,id'],
            'status' => ['nullable','string','max:30'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id','code','name']);
        $contractors = DB::table('parties')->where('is_contractor',1)->orderBy('name')->limit(500)->get(['id','name']);

        $statusOptions = DB::table('store_returns')
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->all();

        return [
            ['name'=>'from_date','label'=>'From Date','type'=>'date','col'=>2],
            ['name'=>'to_date','label'=>'To Date','type'=>'date','col'=>2],
            [
                'name'=>'project_id','label'=>'Project','type'=>'select','col'=>3,
                'options'=>collect($projects)->map(fn($p)=>['value'=>$p->id,'label'=>trim(($p->code?$p->code.' - ':'').$p->name)])->all(),
            ],
            [
                'name'=>'contractor_party_id','label'=>'Contractor','type'=>'select','col'=>3,
                'options'=>collect($contractors)->map(fn($c)=>['value'=>$c->id,'label'=>$c->name])->all(),
            ],
            [
                'name'=>'status','label'=>'Status','type'=>'select','col'=>2,
                'options'=>collect($statusOptions)->map(fn($s)=>['value'=>$s,'label'=>strtoupper($s)])->all(),
            ],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'Return No / Issue No / Remarks'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Return No','value'=>'return_number','w'=>'14%'],
            ['label'=>'Date','value'=>'return_date','w'=>'9%'],
            ['label'=>'Project','value'=>fn($r)=>trim(($r->project_code?$r->project_code.' - ':'').($r->project_name??'')),'w'=>'22%'],
            ['label'=>'Contractor','value'=>'contractor_name','w'=>'18%'],
            ['label'=>'Issue No','value'=>'issue_number','w'=>'12%'],
            ['label'=>'Items','align'=>'right','w'=>'6%','value'=>fn($r)=>(int)($r->item_count ?? 0)],
            ['label'=>'Returned','align'=>'right','w'=>'8%','value'=>fn($r)=>(int)($r->returned_pcs ?? 0)],
            ['label'=>'Damaged','align'=>'right','w'=>'8%','value'=>fn($r)=>(int)($r->damaged_pcs ?? 0)],
            ['label'=>'Status','value'=>fn($r)=>strtoupper((string)$r->status),'w'=>'8%'],
            ['label'=>'Accounting','value'=>fn($r)=>strtoupper((string)$r->accounting_status),'w'=>'9%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'return_date','direction'=>'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('store_returns as sr')
            ->leftJoin('projects as p','p.id','=','sr.project_id')
            ->leftJoin('parties as c','c.id','=','sr.contractor_party_id')
            ->leftJoin('store_issues as si','si.id','=','sr.store_issue_id')
            ->select([
                'sr.id',
                'sr.return_number',
                'sr.return_date',
                'sr.status',
                'sr.accounting_status',
                'p.code as project_code',
                'p.name as project_name',
                'c.name as contractor_name',
                'si.issue_number',
                DB::raw('(select count(*) from store_return_lines l where l.store_return_id = sr.id) as item_count'),
                DB::raw('(select COALESCE(SUM(l.returned_qty_pcs),0) from store_return_lines l where l.store_return_id = sr.id) as returned_pcs'),
                DB::raw('(select COALESCE(SUM(l.damaged_qty_pcs),0) from store_return_lines l where l.store_return_id = sr.id) as damaged_pcs'),
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('sr.return_date','>=',$filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('sr.return_date','<=',$filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('sr.project_id', $filters['project_id']);
        }
        if (!empty($filters['contractor_party_id'])) {
            $q->where('sr.contractor_party_id', $filters['contractor_party_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('sr.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function($sub) use($term){
                $sub->where('sr.return_number','like',"%{$term}%")
                    ->orWhere('si.issue_number','like',"%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(item_count),0) as items, COALESCE(SUM(returned_pcs),0) as rpcs, COALESCE(SUM(damaged_pcs),0) as dpcs')
            ->first();

        return [
            ['label'=>'Returns','value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Items','value'=>(int)($row->items ?? 0)],
            ['label'=>'Returned','value'=>(int)($row->rpcs ?? 0)],
            ['label'=>'Damaged','value'=>(int)($row->dpcs ?? 0)],
        ];
    }
}
