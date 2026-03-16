<?php

namespace App\Services\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the "Daily Digest" dataset (yesterday's summary).
 *
 * Design goal: keep this service resilient even if some tables/modules
 * are not enabled in an installation.
 */
class DailyDigestService
{
    /**
     * Cache schema capability checks to avoid repeated metadata queries.
     *
     * @var array<string, bool>
     */
    protected array $tableExists = [];

    /**
     * @var array<string, array<string, bool>>
     */
    protected array $columnExists = [];

    /**
     * Build digest data for a given date.
     */
    public function build(Carbon $date): array
    {
        $date = $date->copy()->startOfDay();

        $digest = [
            'date'         => $date->toDateString(),
            'generated_at' => now(),
            'store'        => $this->storeSection($date),
            'production'   => $this->productionSection($date),
            'crm'          => $this->crmSection($date),
            'purchase'     => $this->purchaseSection($date),
            'payments'     => $this->paymentsSection($date),
        ];

        $digest['insights'] = $this->insightsSection($digest);

        return $digest;
    }

    protected function storeSection(Carbon $date): array
    {
        $out = [
            'inward' => [
                'grn_count'   => 0,
                'line_count'  => 0,
                'value_total' => 0.0,
            ],
            'issue' => [
                'issue_count' => 0,
                'line_count'  => 0,
                'value_total' => 0.0,
            ],
        ];

        // Inward (GRN)
        if ($this->hasTable('material_receipts') && $this->hasTable('material_receipt_lines')) {
            $grnBase = DB::table('material_receipts')
                ->whereDate('receipt_date', $date);

            if ($this->hasColumn('material_receipts', 'status')) {
                $grnBase->where('status', 'qc_passed');
            }

            $out['inward']['grn_count'] = (int) (clone $grnBase)
                ->count();

            $out['inward']['line_count'] = (int) DB::table('material_receipt_lines as l')
                ->join('material_receipts as r', 'r.id', '=', 'l.material_receipt_id')
                ->when(
                    $this->hasColumn('material_receipts', 'status'),
                    fn ($query) => $query->where('r.status', 'qc_passed')
                )
                ->whereDate('r.receipt_date', $date)
                ->count();

            // Value is derived from linked PO item net_amount, prorated by received qty.
            if ($this->hasTable('purchase_order_items') && $this->hasColumn('material_receipt_lines', 'purchase_order_item_id')) {
                $val = DB::table('material_receipt_lines as l')
                    ->join('material_receipts as r', 'r.id', '=', 'l.material_receipt_id')
                    ->leftJoin('purchase_order_items as poi', 'poi.id', '=', 'l.purchase_order_item_id')
                    ->when(
                        $this->hasColumn('material_receipts', 'status'),
                        fn ($query) => $query->where('r.status', 'qc_passed')
                    )
                    ->whereDate('r.receipt_date', $date)
                    ->selectRaw(
                        "SUM(\n" .
                        "  CASE\n" .
                        "    WHEN poi.id IS NULL THEN 0\n" .
                        "    WHEN COALESCE(poi.qty_pcs,0) > 0 THEN (COALESCE(poi.net_amount, poi.amount, 0) / NULLIF(poi.qty_pcs,0)) * COALESCE(l.qty_pcs,0)\n" .
                        "    WHEN COALESCE(poi.quantity,0) > 0 THEN (COALESCE(poi.net_amount, poi.amount, 0) / NULLIF(poi.quantity,0)) * COALESCE(l.received_weight_kg,0)\n" .
                        "    ELSE 0\n" .
                        "  END\n" .
                        ") AS total_value"
                    )
                    ->value('total_value');

                $out['inward']['value_total'] = (float) ($val ?? 0);
            }
        }

        // Issues
        if (
            $this->hasTable('store_issues') &&
            $this->hasTable('store_issue_lines') &&
            $this->hasTable('store_stock_items') &&
            $this->hasTable('material_receipt_lines')
        ) {
            $issueBase = DB::table('store_issues')
                ->whereDate('issue_date', $date);

            if ($this->hasColumn('store_issues', 'status')) {
                $issueBase->where('status', 'posted');
            }

            $out['issue']['issue_count'] = (int) (clone $issueBase)
                ->count();

            $out['issue']['line_count'] = (int) DB::table('store_issue_lines as l')
                ->join('store_issues as i', 'i.id', '=', 'l.store_issue_id')
                ->when(
                    $this->hasColumn('store_issues', 'status'),
                    fn ($query) => $query->where('i.status', 'posted')
                )
                ->whereDate('i.issue_date', $date)
                ->count();

            if ($this->hasTable('purchase_order_items')) {
                $val = DB::table('store_issue_lines as l')
                    ->join('store_issues as i', 'i.id', '=', 'l.store_issue_id')
                    ->join('store_stock_items as ssi', 'ssi.id', '=', 'l.store_stock_item_id')
                    ->join('material_receipt_lines as mrl', 'mrl.id', '=', 'ssi.material_receipt_line_id')
                    ->leftJoin('purchase_order_items as poi', 'poi.id', '=', 'mrl.purchase_order_item_id')
                    ->when(
                        $this->hasColumn('store_issues', 'status'),
                        fn ($query) => $query->where('i.status', 'posted')
                    )
                    ->whereDate('i.issue_date', $date)
                    ->selectRaw(
                        "SUM(\n" .
                        "  CASE\n" .
                        "    WHEN poi.id IS NULL THEN 0\n" .
                        "    WHEN COALESCE(poi.qty_pcs,0) > 0 THEN (COALESCE(poi.net_amount, poi.amount, 0) / NULLIF(poi.qty_pcs,0)) * COALESCE(l.issued_qty_pcs,0)\n" .
                        "    WHEN COALESCE(poi.quantity,0) > 0 THEN (COALESCE(poi.net_amount, poi.amount, 0) / NULLIF(poi.quantity,0)) * COALESCE(l.issued_weight_kg,0)\n" .
                        "    ELSE 0\n" .
                        "  END\n" .
                        ") AS total_value"
                    )
                    ->value('total_value');

                $out['issue']['value_total'] = (float) ($val ?? 0);
            }
        }

        return $out;
    }

    protected function productionSection(Carbon $date): array
    {
        $out = [
            'dpr_count'  => 0,
            'projects'   => [],
            'qty_total'  => 0.0,
            'mins_total' => 0,
        ];

        if (!$this->hasTable('production_dprs') || !$this->hasTable('production_dpr_lines')) {
            return $out;
        }

        $dprBase = DB::table('production_dprs')
            ->whereDate('dpr_date', $date);

        if ($this->hasColumn('production_dprs', 'status')) {
            $dprBase->whereIn('status', ['submitted', 'approved']);
        }

        $out['dpr_count'] = (int) (clone $dprBase)->count();

        $qtyExpr = $this->hasColumn('production_dpr_lines', 'qty')
            ? 'COALESCE(SUM(dl.qty),0)'
            : '0';
        $minsExpr = $this->hasColumn('production_dpr_lines', 'minutes_spent')
            ? 'COALESCE(SUM(dl.minutes_spent),0)'
            : '0';

        // Overall totals
        $tot = DB::table('production_dpr_lines as dl')
            ->join('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
            ->whereDate('d.dpr_date', $date)
            ->when(
                $this->hasColumn('production_dprs', 'status'),
                fn ($query) => $query->whereIn('d.status', ['submitted', 'approved'])
            )
            ->selectRaw("{$qtyExpr} as qty_total, {$minsExpr} as mins_total")
            ->first();

        $out['qty_total'] = (float) ($tot?->qty_total ?? 0);
        $out['mins_total'] = (int) ($tot?->mins_total ?? 0);

        // Project-wise breakdown (top 10 by qty)
        if (
            $this->hasTable('production_plans') &&
            $this->hasTable('projects') &&
            $this->hasColumn('production_dprs', 'production_plan_id') &&
            $this->hasColumn('production_plans', 'project_id')
        ) {
            $completedExpr = $this->hasColumn('production_dpr_lines', 'is_completed')
                ? 'COALESCE(SUM(CASE WHEN dl.is_completed = 1 THEN 1 ELSE 0 END),0)'
                : '0';

            $rows = DB::table('production_dpr_lines as dl')
                ->join('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
                ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
                ->join('projects as pr', 'pr.id', '=', 'p.project_id')
                ->whereDate('d.dpr_date', $date)
                ->when(
                    $this->hasColumn('production_dprs', 'status'),
                    fn ($query) => $query->whereIn('d.status', ['submitted', 'approved'])
                )
                ->groupBy('pr.id', 'pr.code', 'pr.name')
                ->select(
                    'pr.id',
                    'pr.code',
                    'pr.name',
                    DB::raw('COUNT(DISTINCT d.id) as dpr_count'),
                    DB::raw("{$qtyExpr} as qty_total"),
                    DB::raw("{$minsExpr} as mins_total"),
                    DB::raw("{$completedExpr} as completed_steps")
                )
                ->orderByDesc('qty_total')
                ->limit(10)
                ->get();

            $out['projects'] = $rows->map(function ($r) {
                return [
                    'project_id'       => (int) $r->id,
                    'project_code'     => (string) $r->code,
                    'project_name'     => (string) $r->name,
                    'dpr_count'        => (int) $r->dpr_count,
                    'qty_total'        => (float) $r->qty_total,
                    'mins_total'       => (int) $r->mins_total,
                    'completed_steps'  => (int) $r->completed_steps,
                ];
            })->all();
        }

        return $out;
    }

    protected function crmSection(Carbon $date): array
    {
        $out = [
            'leads_created'             => 0,
            'activities_logged'         => 0,
            'activities_completed'      => 0,
            'quotations_created'        => 0,
            'quotations_created_value'  => 0.0,
            'quotations_sent'           => 0,
            'quotations_sent_value'     => 0.0,
        ];

        $leadDateColumn = $this->firstExistingColumn('crm_leads', ['lead_date', 'created_at']);
        if ($leadDateColumn) {
            $out['leads_created'] = (int) DB::table('crm_leads')
                ->whereDate($leadDateColumn, $date)
                ->count();
        }

        if ($this->hasTable('crm_lead_activities') && $this->hasColumn('crm_lead_activities', 'created_at')) {
            $out['activities_logged'] = (int) DB::table('crm_lead_activities')
                ->whereDate('created_at', $date)
                ->count();

            if ($this->hasColumn('crm_lead_activities', 'done_at')) {
                $out['activities_completed'] = (int) DB::table('crm_lead_activities')
                    ->whereNotNull('done_at')
                    ->whereDate('done_at', $date)
                    ->count();
            }
        }

        if ($this->hasTable('crm_quotations')) {
            $quotationAmountColumn = $this->firstExistingColumn('crm_quotations', ['grand_total', 'total_amount']);

            if ($this->hasColumn('crm_quotations', 'created_at')) {
                $createdBase = DB::table('crm_quotations')
                    ->whereDate('created_at', $date);

                $out['quotations_created'] = (int) (clone $createdBase)->count();
                if ($quotationAmountColumn) {
                    $out['quotations_created_value'] = (float) (clone $createdBase)->sum($quotationAmountColumn);
                }
            }

            if ($this->hasColumn('crm_quotations', 'sent_at')) {
                $sentBase = DB::table('crm_quotations')
                    ->whereNotNull('sent_at')
                    ->whereDate('sent_at', $date);

                $out['quotations_sent'] = (int) (clone $sentBase)->count();
                if ($quotationAmountColumn) {
                    $out['quotations_sent_value'] = (float) (clone $sentBase)->sum($quotationAmountColumn);
                }
            }
        }

        return $out;
    }

    protected function purchaseSection(Carbon $date): array
    {
        $out = [
            'open_indents'            => 0,
            'approved_pending_proc'   => 0,
            'by_procurement_status'   => [],
            'overdue_required_by'     => [],
        ];

        if (!$this->hasTable('purchase_indents')) {
            return $out;
        }

        $hasStatus = $this->hasColumn('purchase_indents', 'status');

        // Total open indents (not closed/rejected)
        $openIndentBase = DB::table('purchase_indents');
        if ($hasStatus) {
            $openIndentBase->whereNotIn('status', ['rejected', 'closed']);
        }
        $out['open_indents'] = (int) $openIndentBase->count();

        $hasProcStatus = $this->hasColumn('purchase_indents', 'procurement_status');

        // Approved but still not fully ordered/closed
        if ($hasStatus) {
            $qApproved = DB::table('purchase_indents')
                ->where('status', 'approved');

            if ($hasProcStatus) {
                $qApproved->whereNotIn('procurement_status', ['ordered', 'closed', 'cancelled']);
            }

            $out['approved_pending_proc'] = (int) $qApproved->count();
        }

        // Group by procurement_status
        if ($hasStatus && $hasProcStatus) {
            $rows = DB::table('purchase_indents')
                ->where('status', 'approved')
                ->groupBy('procurement_status')
                ->select('procurement_status', DB::raw('COUNT(*) as c'))
                ->orderByDesc('c')
                ->get();

            $out['by_procurement_status'] = $rows->map(function ($r) {
                $st = (string) ($r->procurement_status ?? 'open');

                return [
                    // Keep both keys for backward/forward compatibility
                    'procurement_status' => $st,
                    'status'             => $st,
                    'count'              => (int) ($r->c ?? 0),
                ];
            })->all();
        }

        // Overdue required-by indents (as of today)
        $today = now()->startOfDay();

        if ($this->hasColumn('purchase_indents', 'required_by_date')) {
            $hasProjectsTable = $this->hasTable('projects');
            $hasProjectId = $this->hasColumn('purchase_indents', 'project_id');

            $overdueQuery = DB::table('purchase_indents as pi')
                ->whereNotNull('pi.required_by_date')
                ->whereDate('pi.required_by_date', '<', $today);

            if ($hasStatus) {
                $overdueQuery->where('pi.status', 'approved');
            }

            if ($hasProjectsTable && $hasProjectId) {
                $overdueQuery->leftJoin('projects as pr', 'pr.id', '=', 'pi.project_id');
            }

            if ($hasProcStatus) {
                $overdueQuery->whereNotIn('pi.procurement_status', ['ordered', 'closed', 'cancelled']);
            }

            $rows = $overdueQuery
                ->orderBy('pi.required_by_date')
                ->limit(10)
                ->get(array_filter([
                    'pi.id',
                    'pi.code',
                    'pi.required_by_date',
                    $hasProcStatus ? 'pi.procurement_status' : null,
                    ($hasProjectsTable && $hasProjectId) ? 'pr.code as project_code' : null,
                    ($hasProjectsTable && $hasProjectId) ? 'pr.name as project_name' : null,
                ]));

            $out['overdue_required_by'] = $rows->map(function ($r) {
                return [
                    'id'                => (int) $r->id,
                    'code'              => (string) $r->code,
                    'required_by_date'  => $r->required_by_date,
                    'procurement_status'=> (string) ($r->procurement_status ?? 'open'),
                    'project_code'      => (string) ($r->project_code ?? ''),
                    'project_name'      => (string) ($r->project_name ?? ''),
                ];
            })->all();
        }

        return $out;
    }

    protected function paymentsSection(Carbon $date): array
    {
        // Payment reminders are evaluated relative to "today" (when the digest is sent),
        // not relative to the digest date.
        $today = now()->startOfDay();
        $dueSoonEnd = $today->copy()->addDays(7);

        $out = [
            'supplier' => [
                'overdue_count' => 0,
                'overdue_value' => 0.0,
                'due_soon_count' => 0,
                'due_soon_value' => 0.0,
            ],
            'client' => [
                'overdue_count' => 0,
                'overdue_value' => 0.0,
                'due_soon_count' => 0,
                'due_soon_value' => 0.0,
            ],
        ];

        // Supplier bills
        if ($this->hasTable('purchase_bills') && $this->hasColumn('purchase_bills', 'due_date')) {
            $supplierAmountColumn = $this->firstExistingColumn('purchase_bills', ['total_amount', 'amount']);
            $base = DB::table('purchase_bills')
                ->whereNotNull('due_date');

            if ($this->hasColumn('purchase_bills', 'status')) {
                $base->where('status', 'posted');
            }

            $out['supplier']['overdue_count'] = (int) (clone $base)
                ->whereDate('due_date', '<', $today)
                ->count();

            if ($supplierAmountColumn) {
                $out['supplier']['overdue_value'] = (float) (clone $base)
                    ->whereDate('due_date', '<', $today)
                    ->sum($supplierAmountColumn);
            }

            $out['supplier']['due_soon_count'] = (int) (clone $base)
                ->whereDate('due_date', '>=', $today)
                ->whereDate('due_date', '<=', $dueSoonEnd)
                ->count();

            if ($supplierAmountColumn) {
                $out['supplier']['due_soon_value'] = (float) (clone $base)
                    ->whereDate('due_date', '>=', $today)
                    ->whereDate('due_date', '<=', $dueSoonEnd)
                    ->sum($supplierAmountColumn);
            }
        }

        // Client bills (receivables)
        if ($this->hasTable('client_ra_bills') && $this->hasColumn('client_ra_bills', 'due_date')) {
            $base = DB::table('client_ra_bills')
                ->whereNotNull('due_date');

            if ($this->hasColumn('client_ra_bills', 'status')) {
                $base->where('status', 'posted');
            }

            $amountCol = $this->firstExistingColumn('client_ra_bills', ['receivable_amount', 'total_amount']);

            if ($amountCol) {
                $out['client']['overdue_count'] = (int) (clone $base)
                    ->whereDate('due_date', '<', $today)
                    ->count();

                $out['client']['overdue_value'] = (float) (clone $base)
                    ->whereDate('due_date', '<', $today)
                    ->sum($amountCol);

                $out['client']['due_soon_count'] = (int) (clone $base)
                    ->whereDate('due_date', '>=', $today)
                    ->whereDate('due_date', '<=', $dueSoonEnd)
                    ->count();

                $out['client']['due_soon_value'] = (float) (clone $base)
                    ->whereDate('due_date', '>=', $today)
                    ->whereDate('due_date', '<=', $dueSoonEnd)
                    ->sum($amountCol);
            }
        }

        return $out;
    }

    protected function insightsSection(array $digest): array
    {
        $storeInward = (float) ($digest['store']['inward']['value_total'] ?? 0);
        $storeIssue = (float) ($digest['store']['issue']['value_total'] ?? 0);
        $dprCount = (int) ($digest['production']['dpr_count'] ?? 0);
        $qtyTotal = (float) ($digest['production']['qty_total'] ?? 0);
        $minsTotal = (float) ($digest['production']['mins_total'] ?? 0);
        $quotesCreated = (int) ($digest['crm']['quotations_created'] ?? 0);
        $quotesSent = (int) ($digest['crm']['quotations_sent'] ?? 0);
        $quotesCreatedValue = (float) ($digest['crm']['quotations_created_value'] ?? 0);
        $approvedPendingProc = (int) ($digest['purchase']['approved_pending_proc'] ?? 0);
        $overdueIndents = is_array($digest['purchase']['overdue_required_by'] ?? null)
            ? count($digest['purchase']['overdue_required_by'])
            : 0;
        $supplierOverdueCount = (int) ($digest['payments']['supplier']['overdue_count'] ?? 0);
        $supplierOverdueValue = (float) ($digest['payments']['supplier']['overdue_value'] ?? 0);
        $clientOverdueCount = (int) ($digest['payments']['client']['overdue_count'] ?? 0);
        $clientOverdueValue = (float) ($digest['payments']['client']['overdue_value'] ?? 0);

        $avgQtyPerDpr = $dprCount > 0 ? round($qtyTotal / $dprCount, 2) : 0.0;
        $avgMinutesPerDpr = $dprCount > 0 ? round($minsTotal / $dprCount, 1) : 0.0;
        $minutesPerQty = $qtyTotal > 0 ? round($minsTotal / $qtyTotal, 2) : 0.0;
        $quoteSendRate = $quotesCreated > 0 ? round(($quotesSent / $quotesCreated) * 100, 1) : 0.0;
        $avgQuoteValue = $quotesCreated > 0 ? round($quotesCreatedValue / $quotesCreated, 2) : 0.0;
        $netStoreMovement = round($storeInward - $storeIssue, 2);
        $workingCapitalPressure = round($supplierOverdueValue - $clientOverdueValue, 2);

        $alerts = [];
        $actions = [];

        if ($dprCount === 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Production updates missing',
                'message' => 'No DPR was submitted or approved for this digest date.',
            ];
            $actions[] = 'Check whether shop-floor reporting was missed or delayed.';
        }

        if ($overdueIndents > 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Procurement backlog',
                'message' => $overdueIndents . ' approved indent(s) are already past required-by date.',
            ];
            $actions[] = 'Escalate oldest overdue indents and confirm vendor/PO status.';
        }

        if ($supplierOverdueCount > 0) {
            $alerts[] = [
                'level' => 'danger',
                'title' => 'Supplier dues overdue',
                'message' => $supplierOverdueCount . ' supplier bill(s) are overdue, worth ₹ ' . number_format($supplierOverdueValue, 2) . '.',
            ];
            $actions[] = 'Prioritize supplier payments that can block material flow or dispatches.';
        }

        if ($clientOverdueCount > 0) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Client collections pending',
                'message' => $clientOverdueCount . ' receivable(s) are overdue, worth ₹ ' . number_format($clientOverdueValue, 2) . '.',
            ];
            $actions[] = 'Push collection follow-ups on overdue client receivables.';
        }

        if ($quotesCreated > 0 && $quotesSent === 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Quotations created but not sent',
                'message' => 'Commercial team created ' . $quotesCreated . ' quotation(s) but none were marked as sent.',
            ];
            $actions[] = 'Review quotation approval and dispatch bottlenecks.';
        }

        if (empty($alerts)) {
            $alerts[] = [
                'level' => 'success',
                'title' => 'Stable operating day',
                'message' => 'No major operational exceptions were detected from the digest metrics.',
            ];
        }

        return [
            'headline' => [
                'title' => 'Executive Summary',
                'message' => $this->headlineMessage(
                    overdueIndents: $overdueIndents,
                    supplierOverdueCount: $supplierOverdueCount,
                    clientOverdueCount: $clientOverdueCount,
                    dprCount: $dprCount,
                    quotesCreated: $quotesCreated,
                    quotesSent: $quotesSent
                ),
            ],
            'scorecard' => [
                'store_net_value' => $netStoreMovement,
                'avg_qty_per_dpr' => $avgQtyPerDpr,
                'avg_minutes_per_dpr' => $avgMinutesPerDpr,
                'minutes_per_qty' => $minutesPerQty,
                'quote_send_rate' => $quoteSendRate,
                'avg_quote_value' => $avgQuoteValue,
                'approved_pending_proc' => $approvedPendingProc,
                'working_capital_pressure' => $workingCapitalPressure,
            ],
            'alerts' => array_slice($alerts, 0, 5),
            'actions' => array_values(array_unique($actions)),
        ];
    }

    protected function headlineMessage(
        int $overdueIndents,
        int $supplierOverdueCount,
        int $clientOverdueCount,
        int $dprCount,
        int $quotesCreated,
        int $quotesSent
    ): string {
        if ($supplierOverdueCount > 0 || $overdueIndents > 0) {
            return 'Primary focus is cash and procurement risk containment.';
        }

        if ($dprCount === 0) {
            return 'Primary focus is production reporting discipline and shop-floor visibility.';
        }

        if ($quotesCreated > 0 && $quotesSent === 0) {
            return 'Primary focus is converting prepared quotations into outbound commercial movement.';
        }

        if ($clientOverdueCount > 0) {
            return 'Primary focus is accelerating receivable collection without impacting ongoing supply.';
        }

        return 'Operations appear balanced across store, production, procurement, and collections.';
    }

    protected function hasTable(string $table): bool
    {
        return $this->tableExists[$table] ??= Schema::hasTable($table);
    }

    protected function hasColumn(string $table, string $column): bool
    {
        return $this->columnExists[$table][$column]
            ??= $this->hasTable($table) && Schema::hasColumn($table, $column);
    }

    protected function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if ($this->hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }
}
