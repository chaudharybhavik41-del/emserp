<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialVendorReturnRegisterReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'stores-vendor-return-register';
    }

    public function name(): string
    {
        return 'Vendor Return Register';
    }

    public function module(): string
    {
        return 'Stores';
    }

    public function description(): ?string
    {
        return 'Material returned to supplier/client against GRN, with totals and accounting voucher linkage.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'to_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'material_type' => ['nullable', 'string', 'in:all,supplier,client'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);

        // A combined party list for Supplier/Client
        $parties = DB::table('parties')
            ->where(function ($q) {
                $q->where('is_supplier', 1)->orWhere('is_client', 1);
            })
            ->orderBy('name')
            ->limit(800)
            ->get(['id', 'name', 'is_supplier', 'is_client']);

        return [
            ['name' => 'from_date', 'label' => 'From Date', 'type' => 'date', 'col' => 2],
            ['name' => 'to_date', 'label' => 'To Date', 'type' => 'date', 'col' => 2],
            [
                'name' => 'project_id', 'label' => 'Project', 'type' => 'select', 'col' => 3,
                'options' => collect($projects)->map(fn ($p) => [
                    'value' => $p->id,
                    'label' => trim(($p->code ? $p->code . ' - ' : '') . $p->name),
                ])->values()->all(),
            ],
            [
                'name' => 'to_party_id', 'label' => 'Party (Supplier/Client)', 'type' => 'select', 'col' => 3,
                'options' => collect($parties)->map(function ($p) {
                    $tags = [];
                    if ($p->is_supplier) {
                        $tags[] = 'Supplier';
                    }
                    if ($p->is_client) {
                        $tags[] = 'Client';
                    }
                    $suffix = $tags ? (' [' . implode('/', $tags) . ']') : '';
                    return ['value' => $p->id, 'label' => $p->name . $suffix];
                })->values()->all(),
            ],
            [
                'name' => 'material_type', 'label' => 'Type', 'type' => 'select', 'col' => 2, 'default' => 'all',
                'options' => [
                    ['value' => 'all', 'label' => 'All'],
                    ['value' => 'supplier', 'label' => 'Supplier (Own Material)'],
                    ['value' => 'client', 'label' => 'Client Material'],
                ],
            ],
            ['name' => 'q', 'label' => 'Search', 'type' => 'text', 'placeholder' => 'Return No / GRN No / Party', 'col' => 3],
        ];
    }

    public function columns(): array
    {
        return [
            ['label' => 'Return No', 'value' => 'vendor_return_number'],
            ['label' => 'Return Date', 'value' => 'return_date'],
            ['label' => 'GRN No', 'value' => 'grn_number'],
            [
                'label' => 'Type',
                'value' => fn ($r) => ((int) ($r->is_client_material ?? 0) === 1) ? 'Client' : 'Supplier',
            ],
            ['label' => 'Party', 'value' => 'party_name'],
            [
                'label' => 'Project',
                'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')),
            ],
            ['label' => 'Total Pcs', 'value' => 'total_pcs', 'align' => 'right'],
            ['label' => 'Total Wt (kg)', 'value' => fn ($r) => number_format((float) ($r->total_wt ?? 0), 3), 'align' => 'right'],
            ['label' => 'Voucher No', 'value' => 'voucher_no'],
            ['label' => 'Voucher Status', 'value' => 'voucher_status'],
            ['label' => 'Reason', 'value' => 'reason'],
        ];
    }

    public function query(array $filters): EloquentBuilder|QueryBuilder
    {
        $q = DB::table('material_vendor_returns as vr')
            ->join('material_receipts as mr', 'mr.id', '=', 'vr.material_receipt_id')
            ->leftJoin('projects as p', 'p.id', '=', 'vr.project_id')
            ->leftJoin('parties as party', 'party.id', '=', 'vr.to_party_id')
            ->leftJoin('vouchers as v', 'v.id', '=', 'vr.voucher_id')
            ->select([
                'vr.id',
                'vr.vendor_return_number',
                'vr.return_date',
                'vr.reason',
                'mr.receipt_number as grn_number',
                'mr.is_client_material',
                'party.name as party_name',
                'p.code as project_code',
                'p.name as project_name',
                'v.voucher_no as voucher_no',
                'v.status as voucher_status',
                DB::raw('(SELECT COALESCE(SUM(l.returned_qty_pcs),0) FROM material_vendor_return_lines l WHERE l.material_vendor_return_id = vr.id) as total_pcs'),
                DB::raw('(SELECT COALESCE(SUM(l.returned_weight_kg),0) FROM material_vendor_return_lines l WHERE l.material_vendor_return_id = vr.id) as total_wt'),
            ]);

        if (!empty($filters['from_date'])) {
            $q->whereDate('vr.return_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $q->whereDate('vr.return_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('vr.project_id', (int) $filters['project_id']);
        }
        if (!empty($filters['to_party_id'])) {
            $q->where('vr.to_party_id', (int) $filters['to_party_id']);
        }

        $materialType = $filters['material_type'] ?? 'all';
        if ($materialType === 'supplier') {
            $q->where('mr.is_client_material', 0);
        } elseif ($materialType === 'client') {
            $q->where('mr.is_client_material', 1);
        }

        if (!empty($filters['q'])) {
            $term = '%' . trim($filters['q']) . '%';
            $q->where(function ($qq) use ($term) {
                $qq->where('vr.vendor_return_number', 'like', $term)
                    ->orWhere('mr.receipt_number', 'like', $term)
                    ->orWhere('party.name', 'like', $term);
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
            ['label' => 'Vendor Returns', 'value' => (int) ($row->cnt ?? 0)],
            ['label' => 'Total Pcs', 'value' => (int) ($row->pcs ?? 0)],
            ['label' => 'Total Wt (kg)', 'value' => number_format((float) ($row->wt ?? 0), 3)],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'return_date', 'direction' => 'desc'];
    }
}
