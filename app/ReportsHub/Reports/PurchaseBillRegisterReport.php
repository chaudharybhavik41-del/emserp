<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);
        $suppliers = DB::table('parties')
            ->where('is_supplier', 1)
            ->orWhere('is_contractor', 1)
            ->orderBy('name')->limit(500)
            ->get(['id', 'name']);

        $pbStatus = DB::table('purchase_bills')
            ->where('company_id', $this->companyId())
            ->distinct()
            ->pluck('status');
        $raStatus = DB::table('subcontractor_ra_bills')
            ->where('company_id', $this->companyId())
            ->distinct()
            ->pluck('status');
        $statusOptions = $pbStatus->concat($raStatus)->unique()->filter()->values()->all();

        return [
            ['name' => 'from_date', 'label' => 'From Date', 'type' => 'date', 'col' => 2],
            ['name' => 'to_date', 'label' => 'To Date', 'type' => 'date', 'col' => 2],
            [
                'name' => 'project_id',
                'label' => 'Project',
                'type' => 'select',
                'col' => 3,
                'options' => collect($projects)->map(fn($p) => ['value' => $p->id, 'label' => trim(($p->code ? $p->code . ' - ' : '') . $p->name)])->all(),
            ],
            [
                'name' => 'supplier_id',
                'label' => 'Supplier/Contractor',
                'type' => 'select',
                'col' => 3,
                'options' => collect($suppliers)->map(fn($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            ],
            [
                'name' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'col' => 2,
                'options' => array_merge([['value' => 'all', 'label' => 'ALL']], collect($statusOptions)->map(fn($s) => ['value' => $s, 'label' => strtoupper($s)])->all()),
                'value' => (string) ($request->has('status') && $request->get('status') !== '' ? $request->get('status') : 'posted'),
            ],
            [
                'name' => 'q',
                'label' => 'Search',
                'type' => 'text',
                'col' => 4,
                'placeholder' => 'Bill No / Ref / PO No',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            [
                'label' => 'Invoice No',
                'value' => function ($r, $forExport) {
                    $val = $r->bill_number;
                    if ($r->reference_no) {
                        $prefix = $r->source_type === 'subcontractor_ra_bill' ? 'Bill: ' : '';
                        $val .= ($forExport ? ' | ' : "\n") . $prefix . $r->reference_no;
                    }
                    return $val;
                },
                'w' => '14%'
            ],
            ['label' => 'Post Date', 'value' => fn($r) => ($r->posting_date ?: $r->bill_date) ? Carbon::parse($r->posting_date ?: $r->bill_date)->format('d-m-Y') : '', 'w' => '9%'],
            ['label' => 'Bill Date', 'value' => fn($r) => $r->bill_date ? Carbon::parse($r->bill_date)->format('d-m-Y') : '', 'w' => '9%'],
            ['label' => 'Supplier/Contractor', 'value' => 'supplier_name', 'w' => '20%'],
            ['label' => 'Project', 'value' => fn($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'w' => '20%'],
            ['label' => 'PO', 'value' => 'po_number', 'w' => '12%'],
            ['label' => 'Due', 'value' => 'due_date', 'w' => '9%'],
            ['label' => 'Status', 'value' => fn($r) => strtoupper((string) $r->status), 'w' => '8%'],
            [
                'label' => 'Tax',
                'align' => 'right',
                'w' => '10%',
                'value' => fn($r, $forExport) => $forExport ? (float) ($r->total_tax ?? 0) : number_format((float) ($r->total_tax ?? 0), 2),
            ],
            [
                'label' => 'Total',
                'align' => 'right',
                'w' => '12%',
                'value' => fn($r, $forExport) => $forExport ? (float) ($r->total_amount ?? 0) : number_format((float) ($r->total_amount ?? 0), 2),
            ],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'bill_date', 'direction' => 'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q1 = DB::table('purchase_bills as b')
            ->leftJoin('parties as s', 's.id', '=', 'b.supplier_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'b.purchase_order_id')
            ->leftJoin('projects as p', function ($join) {
                $join->on('p.id', '=', DB::raw('COALESCE(b.project_id, po.project_id)'));
            })
            ->where('b.company_id', $this->companyId())
            ->select([
                'b.id',
                'b.bill_number',
                'b.bill_date',
                'b.posting_date',
                'b.due_date',
                'b.reference_no',
                'b.status',
                DB::raw('COALESCE(b.total_tax, 0) + COALESCE(b.tds_amount, 0) as total_tax'),
                'b.total_amount',
                'b.created_at',
                's.name as supplier_name',
                'po.code as po_number',
                'p.code as project_code',
                'p.name as project_name',
                'b.supplier_id',
                DB::raw('COALESCE(b.project_id, po.project_id) as derived_project_id'),
                DB::raw("'purchase_bill' as source_type"),
            ]);

        $q2 = DB::table('subcontractor_ra_bills as b')
            ->leftJoin('parties as s', 's.id', '=', 'b.subcontractor_id')
            ->leftJoin('subcontractor_work_orders as wo', 'wo.id', '=', 'b.work_order_id')
            ->leftJoin('projects as p', 'p.id', '=', 'b.project_id')
            ->where('b.company_id', $this->companyId())
            ->select([
                'b.id',
                'b.ra_number as bill_number',
                'b.bill_date',
                'b.posting_date',
                'b.due_date',
                'b.bill_number as reference_no',
                'b.status',
                DB::raw('COALESCE(b.total_gst, 0) + COALESCE(b.tds_amount, 0) as total_tax'),
                DB::raw('COALESCE(b.total_amount, 0) + COALESCE(b.tds_amount, 0) as total_amount'),
                'b.created_at',
                's.name as supplier_name',
                'wo.work_order_number as po_number',
                'p.code as project_code',
                'p.name as project_name',
                'b.subcontractor_id as supplier_id',
                'b.project_id as derived_project_id',
                DB::raw("'subcontractor_ra_bill' as source_type"),
            ]);

        // Wrap them in a subquery so we can apply filters to the combined result
        $query = DB::table(DB::raw("({$q1->toSql()} UNION {$q2->toSql()}) AS combined"))
            ->mergeBindings($q1)
            ->mergeBindings($q2);

        if (!empty($filters['from_date'])) {
            $query->whereDate(DB::raw('COALESCE(posting_date, bill_date)'), '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate(DB::raw('COALESCE(posting_date, bill_date)'), '<=', $filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $query->where('derived_project_id', $filters['project_id']);
        }
        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }
        $status = $filters['status'] ?? 'posted';
        if ($status === '') {
            $status = 'posted';
        }
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function ($sub) use ($term) {
                $sub->where('bill_number', 'like', "%{$term}%")
                    ->orWhere('reference_no', 'like', "%{$term}%")
                    ->orWhere('po_number', 'like', "%{$term}%");
            });
        }

        return $query;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_tax),0) as tax, COALESCE(SUM(total_amount),0) as tot')
            ->first();

        return [
            ['label' => 'Bills', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Total Tax', 'value' => number_format((float) ($row->tax ?? 0), 2)],
            ['label' => 'Total Amount', 'value' => number_format((float) ($row->tot ?? 0), 2)],
        ];
    }
}
