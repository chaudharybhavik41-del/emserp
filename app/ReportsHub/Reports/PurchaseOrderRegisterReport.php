<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'purchase-order-register';
    }

    public function name(): string
    {
        return 'Purchase Order Register';
    }

    public function module(): string
    {
        return 'Purchase';
    }

    public function description(): ?string
    {
        return 'Purchase orders by project/vendor with amount totals.';
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
        $suppliers = DB::table('parties')->where('is_supplier', 1)->orderBy('name')->limit(600)->get(['id', 'name']);

        $statusOptions = DB::table('purchase_orders')
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->all();

        return [
            ['name' => 'from_date', 'label' => 'PO From', 'type' => 'date', 'col' => 2],
            ['name' => 'to_date', 'label' => 'PO To', 'type' => 'date', 'col' => 2],
            [
                'name' => 'project_id', 'label' => 'Project', 'type' => 'select', 'col' => 3,
                'options' => collect($projects)->map(fn ($p) => [
                    'value' => $p->id,
                    'label' => trim(($p->code ? $p->code . ' - ' : '') . $p->name),
                ])->all(),
            ],
            [
                'name' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'col' => 3,
                'options' => collect($suppliers)->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            ],
            [
                'name' => 'status', 'label' => 'Status', 'type' => 'select', 'col' => 2,
                'options' => collect($statusOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ],
            ['name' => 'q', 'label' => 'Search', 'type' => 'text', 'col' => 4, 'placeholder' => 'PO No / Vendor / RFQ / Indent'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'PO No', 'value' => 'po_number', 'w' => '14%'],
            ['label' => 'PO Date', 'value' => 'po_date', 'w' => '9%'],
            ['label' => 'Project', 'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'w' => '22%'],
            ['label' => 'Supplier', 'value' => 'supplier_name', 'w' => '16%'],
            ['label' => 'Indent', 'value' => 'indent_code', 'w' => '10%'],
            ['label' => 'RFQ', 'value' => 'rfq_code', 'w' => '10%'],
            ['label' => 'Status', 'value' => fn ($r) => strtoupper((string) $r->status), 'w' => '8%'],
            [
                'label' => 'Amount', 'align' => 'right', 'w' => '11%',
                'value' => fn ($r, $forExport) => $forExport ? (float) ($r->total_amount ?? 0) : number_format((float) ($r->total_amount ?? 0), 2),
            ],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'po_date', 'direction' => 'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('purchase_orders as po')
            ->leftJoin('parties as s', 's.id', '=', 'po.vendor_party_id')
            ->leftJoin('projects as p', 'p.id', '=', 'po.project_id')
            ->leftJoin('purchase_indents as pi', 'pi.id', '=', 'po.purchase_indent_id')
            ->leftJoin('purchase_rfqs as r', 'r.id', '=', 'po.purchase_rfq_id')
            ->select([
                'po.id',
                'po.code as po_number',
                'po.po_date',
                'po.expected_delivery_date',
                'po.status',
                'po.total_amount',
                's.name as supplier_name',
                'p.code as project_code',
                'p.name as project_name',
                'pi.code as indent_code',
                'r.code as rfq_code',
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('po.po_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('po.po_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('po.project_id', $filters['project_id']);
        }
        if (!empty($filters['supplier_id'])) {
            $q->where('po.vendor_party_id', $filters['supplier_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('po.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($sub) use ($term) {
                $sub->where('po.code', 'like', "%{$term}%")
                    ->orWhere('s.name', 'like', "%{$term}%")
                    ->orWhere('pi.code', 'like', "%{$term}%")
                    ->orWhere('r.code', 'like', "%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as tot')
            ->first();

        return [
            ['label' => 'POs', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Total Amount', 'value' => number_format((float) ($row->tot ?? 0), 2)],
        ];
    }
}
