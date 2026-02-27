<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuttingPlanRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'production-cutting-plans';
    }

    public function name(): string
    {
        return 'Cutting Plan Register';
    }

    public function module(): string
    {
        return 'Production';
    }

    public function description(): ?string
    {
        return 'Cutting plans by project/BOM with plate and allocation counts.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable','date'],
            'to_date' => ['nullable','date','after_or_equal:from_date'],
            'project_id' => ['nullable','integer','exists:projects,id'],
            'bom_id' => ['nullable','integer','exists:boms,id'],
            'status' => ['nullable','string','max:30'],
            'grade' => ['nullable','string','max:80'],
            'thickness_mm' => ['nullable','integer','min:0'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id','code','name']);
        $boms = DB::table('boms')->orderBy('bom_number')->limit(500)->get(['id','bom_number']);

        $statusOptions = DB::table('cutting_plans')->select('status')->distinct()->orderBy('status')->pluck('status')->filter()->values()->all();

        $grades = DB::table('cutting_plans')->select('grade')->distinct()->orderBy('grade')->pluck('grade')->filter()->values()->all();

        return [
            ['name'=>'from_date','label'=>'Created From','type'=>'date','col'=>2],
            ['name'=>'to_date','label'=>'Created To','type'=>'date','col'=>2],
            [
                'name'=>'project_id','label'=>'Project','type'=>'select','col'=>3,
                'options'=>collect($projects)->map(fn($p)=>['value'=>$p->id,'label'=>trim(($p->code?$p->code.' - ':'').$p->name)])->all(),
            ],
            [
                'name'=>'bom_id','label'=>'BOM','type'=>'select','col'=>3,
                'options'=>collect($boms)->map(fn($b)=>['value'=>$b->id,'label'=>$b->bom_number])->all(),
            ],
            [
                'name'=>'status','label'=>'Status','type'=>'select','col'=>2,
                'options'=>collect($statusOptions)->map(fn($s)=>['value'=>$s,'label'=>strtoupper($s)])->all(),
            ],
            [
                'name'=>'grade','label'=>'Grade','type'=>'select','col'=>3,
                'options'=>collect($grades)->map(fn($g)=>['value'=>$g,'label'=>$g])->all(),
            ],
            ['name'=>'thickness_mm','label'=>'Thk (mm)','type'=>'number','col'=>2,'placeholder'=>'e.g. 12'],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'Name'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Name','value'=>'name','w'=>'22%'],
            ['label'=>'Project','value'=>fn($r)=>trim(($r->project_code?$r->project_code.' - ':'').($r->project_name??'')),'w'=>'22%'],
            ['label'=>'BOM','value'=>'bom_number','w'=>'12%'],
            ['label'=>'Grade','value'=>'grade','w'=>'12%'],
            ['label'=>'Thk','align'=>'right','value'=>fn($r)=>(int)($r->thickness_mm ?? 0),'w'=>'6%'],
            ['label'=>'Status','value'=>fn($r)=>strtoupper((string)$r->status),'w'=>'8%'],
            ['label'=>'Plates','align'=>'right','value'=>fn($r)=>(int)($r->plate_count ?? 0),'w'=>'7%'],
            ['label'=>'Alloc Qty','align'=>'right','value'=>fn($r)=>(int)($r->alloc_qty ?? 0),'w'=>'8%'],
            ['label'=>'Created','value'=>'created_at','w'=>'12%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'created_at','direction'=>'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('cutting_plans as cp')
            ->leftJoin('projects as p','p.id','=','cp.project_id')
            ->leftJoin('boms as b','b.id','=','cp.bom_id')
            ->select([
                'cp.id',
                'cp.name',
                'cp.grade',
                'cp.thickness_mm',
                'cp.status',
                'cp.created_at',
                'p.code as project_code',
                'p.name as project_name',
                'b.bom_number',
                DB::raw('(select count(*) from cutting_plan_plates pl where pl.cutting_plan_id = cp.id) as plate_count'),
                DB::raw('(select COALESCE(SUM(a.quantity),0) from cutting_plan_allocations a join cutting_plan_plates pl on pl.id = a.cutting_plan_plate_id where pl.cutting_plan_id = cp.id) as alloc_qty'),
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('cp.created_at','>=',$filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('cp.created_at','<=',$filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('cp.project_id', $filters['project_id']);
        }
        if (!empty($filters['bom_id'])) {
            $q->where('cp.bom_id', $filters['bom_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('cp.status', $filters['status']);
        }
        if (!empty($filters['grade'])) {
            $q->where('cp.grade', $filters['grade']);
        }
        if (!empty($filters['thickness_mm'])) {
            $q->where('cp.thickness_mm', (int)$filters['thickness_mm']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where('cp.name','like',"%{$term}%");
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(plate_count),0) as plates, COALESCE(SUM(alloc_qty),0) as qty')
            ->first();

        return [
            ['label'=>'Plans','value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Plates','value'=>(int)($row->plates ?? 0)],
            ['label'=>'Alloc Qty','value'=>(int)($row->qty ?? 0)],
        ];
    }
}
