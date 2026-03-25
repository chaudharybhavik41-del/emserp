<?php

namespace App\ReportsHub\Reports;

use App\ReportsHub\BaseTabularReport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StoreIssueAccountingReport extends BaseTabularReport
{
    public function key(): string
    {
        return 'stores-issue-accounting';
    }

    public function name(): string
    {
        return 'Store Issue Accounting';
    }

    public function module(): string
    {
        return 'Stores';
    }

    public function description(): ?string
    {
        return 'Line-wise accounting qty, rate, amount, posting status, and voucher reference for store issues.';
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'accounting_status' => ['nullable', 'string', 'max:30'],
            'cost_source' => ['nullable', 'string', 'max:30'],
            'issue_purpose' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function filters(Request $request): array
    {
        $projects = DB::table('projects')->orderBy('code')->orderBy('name')->limit(300)->get(['id', 'code', 'name']);

        $items = DB::table('items as i')
            ->join('store_issue_lines as sil', 'sil.item_id', '=', 'i.id')
            ->select('i.id', 'i.code', 'i.name')
            ->distinct()
            ->orderBy('i.name')
            ->limit(500)
            ->get();

        $accountingStatuses = DB::table('store_issues')
            ->select('accounting_status')
            ->distinct()
            ->orderBy('accounting_status')
            ->pluck('accounting_status')
            ->filter()
            ->values()
            ->all();

        $filters = [
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
                'name' => 'item_id', 'label' => 'Item', 'type' => 'select', 'col' => 3,
                'options' => collect($items)->map(fn ($i) => [
                    'value' => $i->id,
                    'label' => trim(($i->code ? $i->code . ' - ' : '') . $i->name),
                ])->all(),
            ],
            [
                'name' => 'accounting_status', 'label' => 'Accounting', 'type' => 'select', 'col' => 2,
                'options' => collect($accountingStatuses)->map(fn ($status) => [
                    'value' => $status,
                    'label' => strtoupper((string) $status),
                ])->all(),
            ],
            [
                'name' => 'cost_source', 'label' => 'Cost Source', 'type' => 'select', 'col' => 2,
                'options' => [
                    ['value' => 'invoice', 'label' => 'INVOICE'],
                    ['value' => 'opening', 'label' => 'OPENING'],
                    ['value' => 'unvalued', 'label' => 'UNVALUED'],
                ],
            ],
        ];

        if (Schema::hasColumn('store_issues', 'issue_purpose')) {
            $purposeOptions = DB::table('store_issues')
                ->select('issue_purpose')
                ->distinct()
                ->orderBy('issue_purpose')
                ->pluck('issue_purpose')
                ->filter()
                ->values()
                ->all();

            $filters[] = [
                'name' => 'issue_purpose', 'label' => 'Purpose', 'type' => 'select', 'col' => 2,
                'options' => collect($purposeOptions)->map(fn ($purpose) => [
                    'value' => $purpose,
                    'label' => strtoupper(str_replace('_', ' ', (string) $purpose)),
                ])->all(),
            ];
        }

        $filters[] = [
            'name' => 'q',
            'label' => 'Search',
            'type' => 'text',
            'col' => 4,
            'placeholder' => 'Issue / voucher / requisition / item / remarks',
        ];

        return $filters;
    }

    public function columns(): array
    {
        return [
            ['label' => 'Issue No', 'value' => 'issue_number', 'width' => '11%'],
            ['label' => 'Date', 'value' => 'issue_date', 'width' => '8%'],
            ['label' => 'Voucher', 'value' => 'voucher_no', 'width' => '10%'],
            ['label' => 'Project', 'value' => fn ($r) => trim(($r->project_code ? $r->project_code . ' - ' : '') . ($r->project_name ?? '')), 'width' => '16%'],
            ['label' => 'Item', 'value' => fn ($r) => trim(($r->item_code ? $r->item_code . ' - ' : '') . ($r->item_name ?? '')), 'width' => '18%'],
            [
                'label' => 'Qty',
                'align' => 'right',
                'width' => '7%',
                'value' => fn ($r, $forExport) => $forExport ? (float) ($r->issue_qty ?? 0) : number_format((float) ($r->issue_qty ?? 0), 3),
            ],
            [
                'label' => 'Rate',
                'align' => 'right',
                'width' => '8%',
                'value' => fn ($r, $forExport) => $forExport ? round((float) ($r->accounting_rate ?? 0), 4) : number_format((float) ($r->accounting_rate ?? 0), 4),
            ],
            [
                'label' => 'Amount',
                'align' => 'right',
                'width' => '9%',
                'value' => fn ($r, $forExport) => $forExport ? round((float) ($r->accounting_amount ?? 0), 2) : number_format((float) ($r->accounting_amount ?? 0), 2),
            ],
            ['label' => 'Cost Source', 'value' => fn ($r) => strtoupper((string) ($r->cost_source ?? '')), 'width' => '7%'],
            ['label' => 'Accounting', 'value' => fn ($r) => strtoupper((string) ($r->accounting_status ?? '')), 'width' => '8%'],
            ['label' => 'Remarks', 'value' => 'remarks', 'width' => '12%'],
        ];
    }

    public function defaultSort(): array
    {
        return ['column' => 'issue_date', 'direction' => 'desc'];
    }

    public function query(array $filters): QueryBuilder
    {
        $issueQtyExpr = $this->issueQtyExpression();
        $baseQtyExpr = $this->grnBaseQtyExpression('mrl');
        $otherBaseQtyExpr = $this->grnBaseQtyExpression('mrl2');
        $directBasicExpr = $this->directPostedBasicExpression();
        $legacyBasicExpr = $this->legacyPostedBasicExpression($baseQtyExpr, $otherBaseQtyExpr);
        $postedBasicExpr = '((' . $directBasicExpr . ') + (' . $legacyBasicExpr . '))';
        $rateExpr = $this->accountingRateExpression($postedBasicExpr, $baseQtyExpr);
        $amountExpr = 'ROUND((' . $issueQtyExpr . ') * (' . $rateExpr . '), 2)';
        $costSourceExpr = $this->costSourceExpression($postedBasicExpr, $baseQtyExpr);

        $query = DB::table('store_issue_lines as sil')
            ->join('store_issues as si', 'si.id', '=', 'sil.store_issue_id')
            ->leftJoin('store_stock_items as ssi', 'ssi.id', '=', 'sil.store_stock_item_id')
            ->leftJoin('material_receipt_lines as mrl', 'mrl.id', '=', 'ssi.material_receipt_line_id')
            ->leftJoin('items as it', 'it.id', '=', 'sil.item_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'sil.uom_id')
            ->leftJoin('projects as p', 'p.id', '=', 'si.project_id')
            ->leftJoin('store_requisitions as sr', 'sr.id', '=', 'si.store_requisition_id')
            ->leftJoin('vouchers as v', 'v.id', '=', 'si.voucher_id')
            ->select([
                'sil.id as issue_line_id',
                'si.id as issue_id',
                'si.issue_number',
                'si.issue_date',
                'si.status',
                'si.accounting_status',
                'si.remarks',
                'si.voucher_id',
                'v.voucher_no',
                'sr.requisition_number',
                'it.code as item_code',
                'it.name as item_name',
                'u.code as uom_code',
                'p.code as project_code',
                'p.name as project_name',
                DB::raw($issueQtyExpr . ' as issue_qty'),
                DB::raw($baseQtyExpr . ' as grn_base_qty'),
                DB::raw($postedBasicExpr . ' as posted_basic_total'),
                DB::raw($rateExpr . ' as accounting_rate'),
                DB::raw($amountExpr . ' as accounting_amount'),
                DB::raw($costSourceExpr . ' as cost_source'),
            ]);

        if (Schema::hasColumn('store_issues', 'issue_purpose')) {
            $query->addSelect('si.issue_purpose');
        } else {
            $query->addSelect(DB::raw("'' as issue_purpose"));
        }

        $query->where('si.status', 'posted')
            ->where(function ($sub) {
                $sub->whereNull('ssi.is_client_material')
                    ->orWhere('ssi.is_client_material', false);
            });

        if (! empty($filters['from_date'])) {
            $query->whereDate('si.issue_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('si.issue_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('si.project_id', $filters['project_id']);
        }

        if (! empty($filters['item_id'])) {
            $query->where('sil.item_id', $filters['item_id']);
        }

        if (! empty($filters['accounting_status'])) {
            $query->where('si.accounting_status', $filters['accounting_status']);
        }

        if (! empty($filters['issue_purpose']) && Schema::hasColumn('store_issues', 'issue_purpose')) {
            $query->where('si.issue_purpose', $filters['issue_purpose']);
        }

        if (! empty($filters['cost_source'])) {
            $query->whereRaw($costSourceExpr . ' = ?', [$filters['cost_source']]);
        }

        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function ($sub) use ($term) {
                $sub->where('si.issue_number', 'like', "%{$term}%")
                    ->orWhere('v.voucher_no', 'like', "%{$term}%")
                    ->orWhere('sr.requisition_number', 'like', "%{$term}%")
                    ->orWhere('it.code', 'like', "%{$term}%")
                    ->orWhere('it.name', 'like', "%{$term}%")
                    ->orWhere('si.remarks', 'like', "%{$term}%");
            });
        }

        return $query->orderByDesc('si.issue_date')
            ->orderByDesc('si.id')
            ->orderBy('sil.id');
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        $row = $this->wrapForTotals($query)
            ->selectRaw('COUNT(*) as line_count, COUNT(DISTINCT issue_id) as issue_count, COALESCE(SUM(issue_qty),0) as total_qty, COALESCE(SUM(accounting_amount),0) as total_amount')
            ->first();

        return [
            ['label' => 'Issues', 'value' => (int) ($row->issue_count ?? 0)],
            ['label' => 'Lines', 'value' => (int) ($row->line_count ?? 0)],
            ['label' => 'Total Qty', 'value' => number_format((float) ($row->total_qty ?? 0), 3)],
            ['label' => 'Total Amount', 'value' => number_format((float) ($row->total_amount ?? 0), 2)],
        ];
    }

    protected function issueQtyExpression(): string
    {
        return "CASE
            WHEN COALESCE(sil.issued_weight_kg, 0) > 0 THEN COALESCE(sil.issued_weight_kg, 0)
            ELSE COALESCE(sil.issued_qty_pcs, 0)
        END";
    }

    protected function grnBaseQtyExpression(string $alias): string
    {
        return "CASE
            WHEN COALESCE({$alias}.received_weight_kg, 0) > 0 THEN COALESCE({$alias}.received_weight_kg, 0)
            WHEN COALESCE({$alias}.qty_pcs, 0) > 0 THEN COALESCE({$alias}.qty_pcs, 0)
            ELSE 0
        END";
    }

    protected function directPostedBasicExpression(): string
    {
        return "(SELECT COALESCE(SUM(pbl.basic_amount), 0)
            FROM purchase_bill_lines pbl
            INNER JOIN purchase_bills pb ON pb.id = pbl.purchase_bill_id
            WHERE pb.status = 'posted'
              AND pbl.material_receipt_line_id = ssi.material_receipt_line_id)";
    }

    protected function legacyPostedBasicExpression(string $baseQtyExpr, string $otherBaseQtyExpr): string
    {
        return "CASE
            WHEN ssi.material_receipt_line_id IS NULL OR mrl.id IS NULL THEN 0
            ELSE (
                SELECT COALESCE(SUM(pbl2.basic_amount), 0)
                FROM purchase_bill_lines pbl2
                INNER JOIN purchase_bills pb2 ON pb2.id = pbl2.purchase_bill_id
                WHERE pb2.status = 'posted'
                  AND pbl2.material_receipt_line_id IS NULL
                  AND pbl2.material_receipt_id = mrl.material_receipt_id
                  AND pbl2.item_id = mrl.item_id
                  AND COALESCE(pbl2.uom_id, 0) = COALESCE(mrl.uom_id, 0)
                  AND ABS(pbl2.qty - ({$baseQtyExpr})) < 0.0001
                  AND NOT EXISTS (
                      SELECT 1
                      FROM material_receipt_lines mrl2
                      WHERE mrl2.material_receipt_id = mrl.material_receipt_id
                        AND mrl2.id <> mrl.id
                        AND mrl2.item_id = mrl.item_id
                        AND COALESCE(mrl2.uom_id, 0) = COALESCE(mrl.uom_id, 0)
                        AND ABS(({$otherBaseQtyExpr}) - ({$baseQtyExpr})) < 0.0001
                  )
            )
        END";
    }

    protected function accountingRateExpression(string $postedBasicExpr, string $baseQtyExpr): string
    {
        return "CASE
            WHEN ssi.material_receipt_line_id IS NULL AND COALESCE(ssi.opening_unit_rate, 0) > 0 THEN COALESCE(ssi.opening_unit_rate, 0)
            WHEN ({$postedBasicExpr}) > 0 AND ({$baseQtyExpr}) > 0 THEN (({$postedBasicExpr}) / NULLIF(({$baseQtyExpr}), 0))
            ELSE 0
        END";
    }

    protected function costSourceExpression(string $postedBasicExpr, string $baseQtyExpr): string
    {
        return "CASE
            WHEN ssi.material_receipt_line_id IS NULL AND COALESCE(ssi.opening_unit_rate, 0) > 0 THEN 'opening'
            WHEN ({$postedBasicExpr}) > 0 AND ({$baseQtyExpr}) > 0 THEN 'invoice'
            ELSE 'unvalued'
        END";
    }
}
