<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseBillRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'purchase-bill-register';
    }

    public function name(): string
    {
        return 'Purchase Bill Register';
    }

    public function module(): string
    {
        return 'Purchase';
    }

    public function description(): ?string
    {
        return 'Supplier bills/invoices with totals and status.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:parties,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id','code','name']);
        $suppliers = DB::table('parties')->where('is_supplier',1)->orderBy('name')->limit(500)->get(['id','name']);

        $statusOptions = DB::table('purchase_bills')
            ->where('company_id', $this->companyId())
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
                'name'=>'supplier_id','label'=>'Supplier','type'=>'select','col'=>3,
                'options'=>collect($suppliers)->map(fn($s)=>['value'=>$s->id,'label'=>$s->name])->all(),
            ],
            [
                'name'=>'status','label'=>'Status','type'=>'select','col'=>2,
                'options'=>collect($statusOptions)->map(fn($s)=>['value'=>$s,'label'=>strtoupper($s)])->all(),
            ],
            [
                'name'=>'q','label'=>'Search','type'=>'text','col'=>4,
                'placeholder'=>'Bill No / Ref / PO No',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['label'=>'Bill No','value'=>'bill_number','w'=>'14%'],
            ['label'=>'Bill Date','value'=>'bill_date','w'=>'9%'],
            ['label'=>'Supplier','value'=>'supplier_name','w'=>'20%'],
            ['label'=>'Project','value'=>fn($r)=>trim(($r->project_code?$r->project_code.' - ':'').($r->project_name??'')),'w'=>'20%'],
            ['label'=>'PO','value'=>'po_number','w'=>'12%'],
            ['label'=>'Due','value'=>'due_date','w'=>'9%'],
            ['label'=>'Status','value'=>fn($r)=>strtoupper((string)$r->status),'w'=>'8%'],
            [
                'label'=>'Tax','align'=>'right','w'=>'10%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->total_tax??0):number_format((float)($r->total_tax??0),2),
            ],
            [
                'label'=>'Total','align'=>'right','w'=>'12%',
                'value'=>fn($r,$forExport)=>$forExport?(float)($r->total_amount??0):number_format((float)($r->total_amount??0),2),
            ],
        ];
    }

    public function defaultSort(): array
    {
        return ['column'=>'bill_date','direction'=>'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('purchase_bills as b')
            ->leftJoin('parties as s','s.id','=','b.supplier_id')
            ->leftJoin('purchase_orders as po','po.id','=','b.purchase_order_id')
            ->leftJoin('projects as p', function ($join) {
                // Some bills don't store project_id directly; derive from PO when present.
                $join->on('p.id', '=', DB::raw('COALESCE(b.project_id, po.project_id)'));
            })
            ->where('b.company_id', $this->companyId())
            ->select([
                'b.id',
                'b.bill_number',
                'b.bill_date',
                'b.due_date',
                'b.reference_no',
                'b.status',
                'b.total_tax',
                'b.total_amount',
                's.name as supplier_name',
                'po.code as po_number',
                'p.code as project_code',
                'p.name as project_name',
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('b.bill_date','>=',$filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('b.bill_date','<=',$filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->whereRaw('COALESCE(b.project_id, po.project_id) = ?', [$filters['project_id']]);
        }
        if (!empty($filters['supplier_id'])) {
            $q->where('b.supplier_id', $filters['supplier_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('b.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function($sub) use($term){
                $sub->where('b.bill_number','like',"%{$term}%")
                    ->orWhere('b.reference_no','like',"%{$term}%")
                    ->orWhere('po.code','like',"%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_tax),0) as tax, COALESCE(SUM(total_amount),0) as tot')
            ->first();

        return [
            ['label'=>'Bills','value'=>(int)($row->cnt ?? 0)],
            ['label'=>'Total Tax','value'=>number_format((float)($row->tax ?? 0),2)],
            ['label'=>'Total Amount','value'=>number_format((float)($row->tot ?? 0),2)],
        ];
    }
}
