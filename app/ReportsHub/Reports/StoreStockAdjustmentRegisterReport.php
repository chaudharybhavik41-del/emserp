<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreStockAdjustmentRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'stores-stock-adjustment-register';
    }

    public function name(): string
    {
        return 'Stock Adjustment Register';
    }

    public function module(): string
    {
        return 'Stores';
    }

    public function description(): ?string
    {
        return 'Stock adjustments (opening/physical/transfer/etc) with line counts and quantities.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable','date'],
            'to_date' => ['nullable','date','after_or_equal:from_date'],
            'project_id' => ['nullable','integer','exists:projects,id'],
            'adjustment_type' => ['nullable','string','max:30'],
            'accounting_status' => ['nullable','string','max:30'],
            'q' => ['nullable','string','max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id','code','name']);

        $types = DB::table('store_stock_adjustments')
            ->select('adjustment_type')
            ->distinct()
            ->orderBy('adjustment_type')
            ->pluck('adjustment_type')
            ->filter()
            ->values()
            ->all();

        $acctStatuses = DB::table('store_stock_adjustments')
            ->select('accounting_status')
            ->distinct()
            ->orderBy('accounting_status')
            ->pluck('accounting_status')
            ->filter()
            ->values()
            ->all();

        return [
            ['name'=>'from_date','label'=>'From Date','type'=>'date','col'=>2],
            ['name'=>'to_date','label'=>'To Date','type'=>'date','col'=>2],
            [
                'name'=>'project_id','label'=>'Project','type'=>'select','col'=>4,
                'options'=>collect($projects)->map(fn($p)=>['value'=>$p->id,'label'=>trim(($p->code?$p->code.' - ':'').$p->name)])->all(),
            ],
            [
                'name'=>'adjustment_type','label'=>'Type','type'=>'select','col'=>2,
                'options'=>collect($types)->map(fn($t)=>['value'=>$t,'label'=>strtoupper($t)])->all(),
            ],
            [
                'name'=>'accounting_status','label'=>'Accounting','type'=>'select','col'=>2,
                'options'=>collect($acctStatuses)->map(fn($s)=>['value'=>$s,'label'=>strtoupper($s)])->all(),
            ],
            ['name'=>'q','label'=>'Search','type'=>'text','col'=>4,'placeholder'=>'Ref / Reason / Remarks'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Date','value'=>'adjustment_date','w'=>'9%'],
            ['label'=>'Type','value'=>fn($r)=>strtoupper((string)$r->adjustment_type),'w'=>'10%'],
            ['label'=>'Reference','value'=>'reference_number','w'=>'14%'],
            ['label'=>'Project','value'=>fn($r)=>trim(($r->project_code?$r->project_code.' - ':'').($r->project_name??'')),'w'=>'22%'],
            ['label'=>'Reason','value'=>'reason','w'=>'18%'],
            ['label'=>'Lines','align'=>'right','w'=>'6%','value'=>fn($r)=>(int)($r->line_count ?? 0)],
            [
                'label'=>'Qty','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->total_qty ?? 0):number_format((float)($r->total_qty ?? 0),3),
            ],
            ['label'=>'Accounting','value'=>fn($r)=>strtoupper((string)$r->accounting_status),'w'=>'9%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'adjustment_date','direction'=>'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('store_stock_adjustments as a')
            ->leftJoin('projects as p','p.id','=','a.project_id')
            ->select([
                'a.id',
                'a.adjustment_date',
                'a.adjustment_type',
                'a.reference_number',
                'a.reason',
                'a.remarks',
                'a.accounting_status',
                'p.code as project_code',
                'p.name as project_name',
                DB::raw('(select count(*) from store_stock_adjustment_lines l where l.store_stock_adjustment_id = a.id) as line_count'),
                DB::raw('(select COALESCE(SUM(l.quantity),0) from store_stock_adjustment_lines l where l.store_stock_adjustment_id = a.id) as total_qty'),
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('a.adjustment_date','>=',$filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('a.adjustment_date','<=',$filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('a.project_id', $filters['project_id']);
        }
        if (!empty($filters['adjustment_type'])) {
            $q->where('a.adjustment_type', $filters['adjustment_type']);
        }
        if (!empty($filters['accounting_status'])) {
            $q->where('a.accounting_status', $filters['accounting_status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function($sub) use($term){
                $sub->where('a.reference_number','like',"%{$term}%")
                    ->orWhere('a.reason','like',"%{$term}%")
                    ->orWhere('a.remarks','like',"%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(line_count),0) as lines, COALESCE(SUM(total_qty),0) as qty')
            ->first();

        return [
            ['label'=>'Adjustments','value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Lines','value'=>(int)($row->lines ?? 0)],
            ['label'=>'Qty','value'=>number_format((float)($row->qty ?? 0),3)],
        ];
    }
}
