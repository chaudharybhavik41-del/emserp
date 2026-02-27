<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionPlanRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'production-production-plans';
    }

    public function name(): string
    {
        return 'Production Plan Register';
    }

    public function module(): string
    {
        return 'Production';
    }

    public function description(): ?string
    {
        return 'Production plans with item counts and planned quantities/weights.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable','date'],
            'to_date' => ['nullable','date','after_or_equal:from_date'],
            'project_id' => ['nullable','integer','exists:projects,id'],
            'bom_id' => ['nullable','integer','exists:boms,id'],
            'status' => ['nullable','string','max:30'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id','code','name']);
        $boms = DB::table('boms')->orderBy('bom_number')->limit(500)->get(['id','bom_number']);

        $statusOptions = DB::table('production_plans')
            ->select('status')->distinct()->orderBy('status')->pluck('status')->filter()->values()->all();

        return [
            ['name'=>'from_date','label'=>'Plan From','type'=>'date','col'=>2],
            ['name'=>'to_date','label'=>'Plan To','type'=>'date','col'=>2],
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
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'Plan No'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Plan No','value'=>'plan_number','w'=>'14%'],
            ['label'=>'Plan Date','value'=>'plan_date','w'=>'9%'],
            ['label'=>'Project','value'=>fn($r)=>trim(($r->project_code?$r->project_code.' - ':'').($r->project_name??'')),'w'=>'24%'],
            ['label'=>'BOM','value'=>'bom_number','w'=>'12%'],
            ['label'=>'Status','value'=>fn($r)=>strtoupper((string)$r->status),'w'=>'8%'],
            ['label'=>'Items','align'=>'right','value'=>fn($r)=>(int)($r->item_count ?? 0),'w'=>'6%'],
            [
                'label'=>'Planned Qty','align'=>'right','w'=>'9%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->planned_qty ?? 0):number_format((float)($r->planned_qty ?? 0),3),
            ],
            [
                'label'=>'Planned Wt (kg)','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->planned_wt ?? 0):number_format((float)($r->planned_wt ?? 0),3),
            ],
            ['label'=>'Created','value'=>'created_at','w'=>'12%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'plan_date','direction'=>'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('production_plans as pp')
            ->leftJoin('projects as p','p.id','=','pp.project_id')
            ->leftJoin('boms as b','b.id','=','pp.bom_id')
            ->select([
                'pp.id',
                'pp.plan_number',
                'pp.plan_date',
                'pp.status',
                'pp.created_at',
                'p.code as project_code',
                'p.name as project_name',
                'b.bom_number',
                DB::raw('(select count(*) from production_plan_items i where i.production_plan_id = pp.id) as item_count'),
                DB::raw('(select COALESCE(SUM(i.planned_qty),0) from production_plan_items i where i.production_plan_id = pp.id) as planned_qty'),
                DB::raw('(select COALESCE(SUM(i.planned_weight_kg),0) from production_plan_items i where i.production_plan_id = pp.id) as planned_wt'),
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('pp.plan_date','>=',$filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('pp.plan_date','<=',$filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('pp.project_id', $filters['project_id']);
        }
        if (!empty($filters['bom_id'])) {
            $q->where('pp.bom_id', $filters['bom_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('pp.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where('pp.plan_number','like',"%{$term}%");
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(item_count),0) as items, COALESCE(SUM(planned_qty),0) as qty, COALESCE(SUM(planned_wt),0) as wt')
            ->first();

        return [
            ['label'=>'Plans','value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Items','value'=>(int)($row->items ?? 0)],
            ['label'=>'Planned Qty','value'=>number_format((float)($row->qty ?? 0),3)],
            ['label'=>'Planned Wt (kg)','value'=>number_format((float)($row->wt ?? 0),3)],
        ];
    }
}
