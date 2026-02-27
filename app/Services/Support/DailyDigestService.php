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
     * Build digest data for a given date.
     */
    public function build(Carbon $date): array
    {
        $date = $date->copy()->startOfDay();

        return [
            'date'         => $date->toDateString(),
            'generated_at' => now(),
            'store'        => $this->storeSection($date),
            'production'   => $this->productionSection($date),
            'crm'          => $this->crmSection($date),
            'purchase'     => $this->purchaseSection($date),
            'payments'     => $this->paymentsSection($date),
        ];
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
        if (Schema::hasTable('material_receipts') && Schema::hasTable('material_receipt_lines')) {
            $out['inward']['grn_count'] = (int) DB::table('material_receipts')
                ->whereDate('receipt_date', $date)
                ->count();

            $out['inward']['line_count'] = (int) DB::table('material_receipt_lines as l')
                ->join('material_receipts as r', 'r.id', '=', 'l.material_receipt_id')
                ->whereDate('r.receipt_date', $date)
                ->count();

            // Value is derived from linked PO item net_amount, prorated by received qty.
            if (Schema::hasTable('purchase_order_items') && Schema::hasColumn('material_receipt_lines', 'purchase_order_item_id')) {
                $val = DB::table('material_receipt_lines as l')
                    ->join('material_receipts as r', 'r.id', '=', 'l.material_receipt_id')
                    ->leftJoin('purchase_order_items as poi', 'poi.id', '=', 'l.purchase_order_item_id')
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
            Schema::hasTable('store_issues') &&
            Schema::hasTable('store_issue_lines') &&
            Schema::hasTable('store_stock_items') &&
            Schema::hasTable('material_receipt_lines')
        ) {
            $out['issue']['issue_count'] = (int) DB::table('store_issues')
                ->whereDate('issue_date', $date)
                ->count();

            $out['issue']['line_count'] = (int) DB::table('store_issue_lines as l')
                ->join('store_issues as i', 'i.id', '=', 'l.store_issue_id')
                ->whereDate('i.issue_date', $date)
                ->count();

            if (Schema::hasTable('purchase_order_items')) {
                $val = DB::table('store_issue_lines as l')
                    ->join('store_issues as i', 'i.id', '=', 'l.store_issue_id')
                    ->join('store_stock_items as ssi', 'ssi.id', '=', 'l.store_stock_item_id')
                    ->join('material_receipt_lines as mrl', 'mrl.id', '=', 'ssi.material_receipt_line_id')
                    ->leftJoin('purchase_order_items as poi', 'poi.id', '=', 'mrl.purchase_order_item_id')
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

        if (!Schema::hasTable('production_dprs') || !Schema::hasTable('production_dpr_lines')) {
            return $out;
        }

        $out['dpr_count'] = (int) DB::table('production_dprs')
            ->whereDate('dpr_date', $date)
            ->whereIn('status', ['submitted', 'approved'])
            ->count();

        // Overall totals
        $tot = DB::table('production_dpr_lines as dl')
            ->join('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
            ->whereDate('d.dpr_date', $date)
            ->whereIn('d.status', ['submitted', 'approved'])
            ->selectRaw('COALESCE(SUM(dl.qty),0) as qty_total, COALESCE(SUM(dl.minutes_spent),0) as mins_total')
            ->first();

        $out['qty_total'] = (float) ($tot?->qty_total ?? 0);
        $out['mins_total'] = (int) ($tot?->mins_total ?? 0);

        // Project-wise breakdown (top 10 by qty)
        if (Schema::hasTable('production_plans') && Schema::hasTable('projects')) {
            $rows = DB::table('production_dpr_lines as dl')
                ->join('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
                ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
                ->join('projects as pr', 'pr.id', '=', 'p.project_id')
                ->whereDate('d.dpr_date', $date)
                ->whereIn('d.status', ['submitted', 'approved'])
                ->groupBy('pr.id', 'pr.code', 'pr.name')
                ->select(
                    'pr.id',
                    'pr.code',
                    'pr.name',
                    DB::raw('COUNT(DISTINCT d.id) as dpr_count'),
                    DB::raw('COALESCE(SUM(dl.qty),0) as qty_total'),
                    DB::raw('COALESCE(SUM(dl.minutes_spent),0) as mins_total'),
                    DB::raw('COALESCE(SUM(CASE WHEN dl.is_completed = 1 THEN 1 ELSE 0 END),0) as completed_steps')
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

        if (Schema::hasTable('crm_leads')) {
            $out['leads_created'] = (int) DB::table('crm_leads')
                ->whereDate('lead_date', $date)
                ->count();
        }

        if (Schema::hasTable('crm_lead_activities')) {
            $out['activities_logged'] = (int) DB::table('crm_lead_activities')
                ->whereDate('created_at', $date)
                ->count();

            $out['activities_completed'] = (int) DB::table('crm_lead_activities')
                ->whereNotNull('done_at')
                ->whereDate('done_at', $date)
                ->count();
        }

        if (Schema::hasTable('crm_quotations')) {
            $out['quotations_created'] = (int) DB::table('crm_quotations')
                ->whereDate('created_at', $date)
                ->count();

            $out['quotations_created_value'] = (float) DB::table('crm_quotations')
                ->whereDate('created_at', $date)
                ->sum('grand_total');

            $out['quotations_sent'] = (int) DB::table('crm_quotations')
                ->whereNotNull('sent_at')
                ->whereDate('sent_at', $date)
                ->count();

            $out['quotations_sent_value'] = (float) DB::table('crm_quotations')
                ->whereNotNull('sent_at')
                ->whereDate('sent_at', $date)
                ->sum('grand_total');
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

        if (!Schema::hasTable('purchase_indents')) {
            return $out;
        }

        // Total open indents (not closed/rejected)
        $out['open_indents'] = (int) DB::table('purchase_indents')
            ->whereNotIn('status', ['rejected', 'closed'])
            ->count();

        $hasProcStatus = Schema::hasColumn('purchase_indents', 'procurement_status');

        // Approved but still not fully ordered/closed
        $qApproved = DB::table('purchase_indents')
            ->where('status', 'approved');

        if ($hasProcStatus) {
            $qApproved->whereNotIn('procurement_status', ['ordered', 'closed', 'cancelled']);
        }

        $out['approved_pending_proc'] = (int) $qApproved->count();

        // Group by procurement_status
        if ($hasProcStatus) {
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

        if (Schema::hasColumn('purchase_indents', 'required_by_date')) {
            $overdueQuery = DB::table('purchase_indents as pi')
                ->leftJoin('projects as pr', 'pr.id', '=', 'pi.project_id')
                ->where('pi.status', 'approved')
                ->whereNotNull('pi.required_by_date')
                ->whereDate('pi.required_by_date', '<', $today);

            if ($hasProcStatus) {
                $overdueQuery->whereNotIn('pi.procurement_status', ['ordered', 'closed', 'cancelled']);
            }

            $rows = $overdueQuery
                ->orderBy('pi.required_by_date')
                ->limit(10)
                ->get([
                    'pi.id',
                    'pi.code',
                    'pi.required_by_date',
                    'pi.procurement_status',
                    'pr.code as project_code',
                    'pr.name as project_name',
                ]);

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
        if (Schema::hasTable('purchase_bills')) {
            $base = DB::table('purchase_bills')
                ->where('status', 'posted')
                ->whereNotNull('due_date');

            $out['supplier']['overdue_count'] = (int) (clone $base)
                ->whereDate('due_date', '<', $today)
                ->count();

            $out['supplier']['overdue_value'] = (float) (clone $base)
                ->whereDate('due_date', '<', $today)
                ->sum('total_amount');

            $out['supplier']['due_soon_count'] = (int) (clone $base)
                ->whereDate('due_date', '>=', $today)
                ->whereDate('due_date', '<=', $dueSoonEnd)
                ->count();

            $out['supplier']['due_soon_value'] = (float) (clone $base)
                ->whereDate('due_date', '>=', $today)
                ->whereDate('due_date', '<=', $dueSoonEnd)
                ->sum('total_amount');
        }

        // Client bills (receivables)
        if (Schema::hasTable('client_ra_bills')) {
            $base = DB::table('client_ra_bills')
                ->where('status', 'posted')
                ->whereNotNull('due_date');

            $amountCol = Schema::hasColumn('client_ra_bills', 'receivable_amount')
                ? 'receivable_amount'
                : (Schema::hasColumn('client_ra_bills', 'total_amount') ? 'total_amount' : null);

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
}
