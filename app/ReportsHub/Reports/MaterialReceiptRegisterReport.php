<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialReceiptRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'stores-material-receipt-register';
    }

    public function name(): string
    {
        return 'Material Receipt Register';
    }

    public function module(): string
    {
        return 'Stores';
    }

    public function description(): ?string
    {
        return 'Material receipts (GRN) with supplier/client, project and quantity/weight totals.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:parties,id'],
            'client_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'material_type' => ['nullable', 'string', 'in:all,supplier,client'],
            'status' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);
        $suppliers = DB::table('parties')->where('is_supplier', 1)->orderBy('name')->limit(600)->get(['id', 'name']);
        $clients = DB::table('parties')->where('is_client', 1)->orderBy('name')->limit(600)->get(['id', 'name']);

        $statusOptions = DB::table('material_receipts')
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->all();

        return [
            ['name' => 'from_date', 'label' => 'From Date', 'type' => 'date', 'col' => 2],
            ['name' => 'to_date', 'label' => 'To Date', 'type' => 'date', 'col' => 2],
            [
                'name' => 'project_id', 'label' => 'Project', 'type' => 'select', 'col' => 3,
                'options' => collect($projects)->map(fn ($p) => [
                    'value' => $p->id,
                    'label' => trim(($p->code ? $p->code . ' - ' : '') . $p->name),
                ])->all(),
            ],
            [
                'name' => 'material_type', 'label' => 'Material Type', 'type' => 'select', 'col' => 2,
                'options' => [
                    ['value' => 'all', 'label' => 'ALL'],
                    ['value' => 'supplier', 'label' => 'SUPPLIER'],
                    ['value' => 'client', 'label' => 'CLIENT'],
                ],
            ],
            [
                'name' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'col' => 3,
                'options' => collect($suppliers)->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            ],
            [
                'name' => 'client_party_id', 'label' => 'Client', 'type' => 'select', 'col' => 3,
                'options' => collect($clients)->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            ],
            [
                'name' => 'status', 'label' => 'Status', 'type' => 'select', 'col' => 2,
                'options' => collect($statusOptions)->map(fn ($s) => ['value' => $s, 'label' => strtoupper($s)])->all(),
            ],
            ['name' => 'q', 'label' => 'Search', 'type' => 'text', 'col' => 4, 'placeholder' => 'GRN No / Invoice No / PO No'],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'GRN No', 'value' => 'receipt_number', 'w' => '12%'],
            ['label' => 'Date', 'value' => 'receipt_date', 'w' => '9%'],
            ['label' => 'Type', 'value' => fn ($r) => ((int) ($r->is_client_material ?? 0) === 1) ? 'CLIENT' : 'SUPPLIER', 'w' => '8%'],
            ['label' => 'Party', 'value' => fn ($r) => ((int) ($r->is_client_material ?? 0) === 1) ? ($r->client_name ?? '') : ($r->supplier_name ?? ''), 'w' => '18%'],
            ['label' => 'Project', 'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'w' => '20%'],
            ['label' => 'PO No', 'value' => fn ($r) => $r->po_number_display ?? '', 'w' => '11%'],
            ['label' => 'Invoice', 'value' => 'invoice_number', 'w' => '10%'],
            ['label' => 'Status', 'value' => fn ($r) => strtoupper((string) $r->status), 'w' => '8%'],
            ['label' => 'Pcs', 'align' => 'right', 'value' => fn ($r) => (int) ($r->total_pcs ?? 0), 'w' => '6%'],
            [
                'label' => 'Wt (kg)', 'align' => 'right', 'w' => '8%',
                'value' => fn ($r, $forExport) => $forExport ? (float) ($r->total_wt ?? 0) : number_format((float) ($r->total_wt ?? 0), 3),
            ],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'receipt_date', 'direction' => 'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $q = DB::table('material_receipts as mr')
            ->leftJoin('projects as p', 'p.id', '=', 'mr.project_id')
            ->leftJoin('parties as s', 's.id', '=', 'mr.supplier_id')
            ->leftJoin('parties as c', 'c.id', '=', 'mr.client_party_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'mr.purchase_order_id')
            ->select([
                'mr.id',
                'mr.receipt_number',
                'mr.receipt_date',
                'mr.is_client_material',
                'mr.invoice_number',
                'mr.po_number',
                'mr.status',
                's.name as supplier_name',
                'c.name as client_name',
                'p.code as project_code',
                'p.name as project_name',
                DB::raw('COALESCE(mr.po_number, po.code) as po_number_display'),
                DB::raw('(select COALESCE(SUM(l.qty_pcs),0) from material_receipt_lines l where l.material_receipt_id = mr.id) as total_pcs'),
                DB::raw('(select COALESCE(SUM(l.received_weight_kg),0) from material_receipt_lines l where l.material_receipt_id = mr.id) as total_wt'),
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('mr.receipt_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('mr.receipt_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('mr.project_id', $filters['project_id']);
        }
        if (!empty($filters['supplier_id'])) {
            $q->where('mr.supplier_id', $filters['supplier_id']);
        }
        if (!empty($filters['client_party_id'])) {
            $q->where('mr.client_party_id', $filters['client_party_id']);
        }
        if (!empty($filters['material_type']) && $filters['material_type'] !== 'all') {
            $q->where('mr.is_client_material', $filters['material_type'] === 'client' ? 1 : 0);
        }
        if (!empty($filters['status'])) {
            $q->where('mr.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($sub) use ($term) {
                $sub->where('mr.receipt_number', 'like', "%{$term}%")
                    ->orWhere('mr.invoice_number', 'like', "%{$term}%")
                    ->orWhere('mr.po_number', 'like', "%{$term}%")
                    ->orWhere('po.code', 'like', "%{$term}%");
            });
        }

        return $q;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_pcs),0) as pcs, COALESCE(SUM(total_wt),0) as wt')
            ->first();

        return [
            ['label' => 'Receipts', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Total Pcs', 'value' => (int) ($row->pcs ?? 0)],
            ['label' => 'Total Wt (kg)', 'value' => number_format((float) ($row->wt ?? 0), 3)],
        ];
    }
}
