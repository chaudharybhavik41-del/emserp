<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('dashboard');
    }

    // ---------------------------------------------------------------------
    // API: KPI Summary
    // ---------------------------------------------------------------------
    public function apiSummary(Request $request)
    {
        $companyId = (int) config('accounting.default_company_id', 1);

        $cacheKey = 'dash:kpis:user:' . auth()->id() . ':company:' . $companyId;

        return Cache::remember($cacheKey, 60, function () use ($companyId) {

            $out = [
                'ok' => true,
                'server_time' => now()->toDateTimeString(),
                'kpis' => [
                    'accounting' => null,
                    'store' => null,
                    'production' => null,
                ],
            ];

            // ----------------------
            // Accounting KPIs
            // ----------------------
            if (auth()->user()?->can('accounting.vouchers.view') || auth()->user()?->can('accounting.reports.view')) {

                $fromMonth = now()->startOfMonth()->toDateString();
                $toToday   = now()->toDateString();

                $receipts = 0.0;
                $payments = 0.0;

                if (Schema::hasTable('vouchers')) {
                    $rows = DB::table('vouchers')
                        ->where('company_id', $companyId)
                        ->where('status', 'posted')
                        ->whereBetween(DB::raw('DATE(voucher_date)'), [$fromMonth, $toToday])
                        ->selectRaw("SUM(CASE WHEN voucher_type='receipt' THEN amount_base ELSE 0 END) as receipts")
                        ->selectRaw("SUM(CASE WHEN voucher_type='payment' THEN amount_base ELSE 0 END) as payments")
                        ->first();

                    $receipts = (float) ($rows->receipts ?? 0);
                    $payments = (float) ($rows->payments ?? 0);
                }

                $cashBalance = null;
                if (Schema::hasTable('accounts') && Schema::hasTable('voucher_lines') && Schema::hasTable('vouchers')) {
                    $cashBalance = (float) DB::table('voucher_lines as vl')
                        ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
                        ->join('accounts as a', 'a.id', '=', 'vl.account_id')
                        ->where('v.company_id', $companyId)
                        ->where('v.status', 'posted')
                        ->whereIn('a.type', ['bank', 'cash'])
                        ->selectRaw('COALESCE(SUM(vl.debit),0) - COALESCE(SUM(vl.credit),0) as net')
                        ->value('net');
                }

                $out['kpis']['accounting'] = [
                    'receipts_mtd' => round($receipts, 2),
                    'payments_mtd' => round($payments, 2),
                    'net_mtd'      => round($receipts - $payments, 2),
                    'cash_net'     => $cashBalance !== null ? round($cashBalance, 2) : null,
                ];
            }

            // ----------------------
            // Store KPIs
            // ----------------------
            if (auth()->user()?->can('store.material_receipt.view') || auth()->user()?->can('store.issue.view') || auth()->user()?->can('store.stock.view')) {

                $fromMonth = now()->startOfMonth()->toDateString();
                $toToday   = now()->toDateString();

                $grnCount = null;
                if (Schema::hasTable('material_receipts')) {
                    $grnCount = (int) DB::table('material_receipts')
                        ->whereBetween(DB::raw('DATE(receipt_date)'), [$fromMonth, $toToday])
                        ->count();
                }

                $issueCount = null;
                if (Schema::hasTable('store_issues')) {
                    $issueCount = (int) DB::table('store_issues')
                        ->where('status', 'posted')
                        ->whereBetween(DB::raw('DATE(issue_date)'), [$fromMonth, $toToday])
                        ->count();
                }

                $stockLines = null;
                if (Schema::hasTable('store_stock_items')) {
                    $stockLines = (int) DB::table('store_stock_items')
                        ->where(function ($q) {
                            $q->where('weight_kg_available', '>', 0)
                              ->orWhere('qty_pcs_available', '>', 0);
                        })
                        ->count();
                }

                $out['kpis']['store'] = [
                    'grn_mtd' => $grnCount,
                    'issues_mtd' => $issueCount,
                    'stock_lines_available' => $stockLines,
                ];
            }

            // ----------------------
            // Production KPIs
            // ----------------------
            if (auth()->user()?->can('production.dpr.view') || auth()->user()?->can('production.qc.perform') || auth()->user()?->can('production.report.view')) {

                $fromMonth = now()->startOfMonth()->toDateString();
                $toToday   = now()->toDateString();

                $dprApproved = null;
                if (Schema::hasTable('production_dprs')) {
                    $dprApproved = (int) DB::table('production_dprs')
                        ->where('status', 'approved')
                        ->whereBetween(DB::raw('DATE(dpr_date)'), [$fromMonth, $toToday])
                        ->count();
                }

                $qcPending = null;
                if (Schema::hasTable('production_qc_checks')) {
                    $qcPending = (int) DB::table('production_qc_checks')
                        ->where('result', 'pending')
                        ->count();
                }

                $out['kpis']['production'] = [
                    'approved_dpr_mtd' => $dprApproved,
                    'qc_pending' => $qcPending,
                ];
            }

            return response()->json($out);
        });
    }

    // ---------------------------------------------------------------------
    // API: Accounting Chart - Cashflow Receipts vs Payments (line)
    // ---------------------------------------------------------------------
    public function chartCashflow(Request $request)
    {
        abort_unless(auth()->user()?->can('accounting.vouchers.view') || auth()->user()?->can('accounting.reports.view'), 403);

        $companyId = (int) config('accounting.default_company_id', 1);
        $days = max(7, min(90, (int) $request->get('days', 30)));

        $cacheKey = "dash:chart:cashflow:company:{$companyId}:days:{$days}";

        return Cache::remember($cacheKey, 60, function () use ($companyId, $days) {

            if (!Schema::hasTable('vouchers')) {
                return response()->json(['labels' => [], 'series' => ['receipts' => [], 'payments' => []]]);
            }

            $from = now()->subDays($days)->toDateString();
            $to   = now()->toDateString();

            $rows = DB::table('vouchers')
                ->where('company_id', $companyId)
                ->where('status', 'posted')
                ->whereBetween(DB::raw('DATE(voucher_date)'), [$from, $to])
                ->selectRaw('DATE(voucher_date) as dt')
                ->selectRaw("SUM(CASE WHEN voucher_type='receipt' THEN amount_base ELSE 0 END) as receipts")
                ->selectRaw("SUM(CASE WHEN voucher_type='payment' THEN amount_base ELSE 0 END) as payments")
                ->groupBy('dt')
                ->orderBy('dt')
                ->get();

            $labels = [];
            $receipts = [];
            $payments = [];

            foreach ($rows as $r) {
                $labels[] = (string) $r->dt;
                $receipts[] = (float) $r->receipts;
                $payments[] = (float) $r->payments;
            }

            return response()->json([
                'labels' => $labels,
                'series' => [
                    'receipts' => $receipts,
                    'payments' => $payments,
                ],
            ]);
        });
    }

    // ---------------------------------------------------------------------
    // API: Store Chart - GRN vs Issues (bar)
    // ---------------------------------------------------------------------
    public function chartStoreGrnVsIssue(Request $request)
    {
        abort_unless(
            auth()->user()?->can('store.material_receipt.view') ||
            auth()->user()?->can('store.issue.view') ||
            auth()->user()?->can('store.stock.view'),
            403
        );

        $days = max(7, min(90, (int) $request->get('days', 30)));
        $cacheKey = "dash:chart:store:grn_issue:days:{$days}:user:" . auth()->id();

        return Cache::remember($cacheKey, 60, function () use ($days) {

            $from = now()->subDays($days)->toDateString();
            $to   = now()->toDateString();

            $labels = [];
            $grn = [];
            $issues = [];

            for ($i = $days; $i >= 0; $i--) {
                $d = now()->subDays($i)->toDateString();
                $labels[] = $d;
                $grn[$d] = 0;
                $issues[$d] = 0;
            }

            if (Schema::hasTable('material_receipts')) {
                $grnRows = DB::table('material_receipts')
                    ->whereBetween(DB::raw('DATE(receipt_date)'), [$from, $to])
                    ->selectRaw('DATE(receipt_date) as dt, COUNT(*) as cnt')
                    ->groupBy('dt')
                    ->get();

                foreach ($grnRows as $r) {
                    $grn[(string) $r->dt] = (int) $r->cnt;
                }
            }

            if (Schema::hasTable('store_issues')) {
                $issueRows = DB::table('store_issues')
                    ->where('status', 'posted')
                    ->whereBetween(DB::raw('DATE(issue_date)'), [$from, $to])
                    ->selectRaw('DATE(issue_date) as dt, COUNT(*) as cnt')
                    ->groupBy('dt')
                    ->get();

                foreach ($issueRows as $r) {
                    $issues[(string) $r->dt] = (int) $r->cnt;
                }
            }

            $grnSeries = [];
            $issueSeries = [];
            foreach ($labels as $d) {
                $grnSeries[] = (int) ($grn[$d] ?? 0);
                $issueSeries[] = (int) ($issues[$d] ?? 0);
            }

            return response()->json([
                'labels' => $labels,
                'series' => [
                    'grn' => $grnSeries,
                    'issues' => $issueSeries,
                ],
            ]);
        });
    }

    // ---------------------------------------------------------------------
    // API: Production Chart - DPR Approved per day (line)
    // ---------------------------------------------------------------------
    public function chartProductionDpr(Request $request)
    {
        abort_unless(auth()->user()?->can('production.dpr.view') || auth()->user()?->can('production.report.view'), 403);

        $days = max(7, min(90, (int) $request->get('days', 30)));
        $cacheKey = "dash:chart:production:dpr:days:{$days}:user:" . auth()->id();

        return Cache::remember($cacheKey, 60, function () use ($days) {

            if (!Schema::hasTable('production_dprs')) {
                return response()->json(['labels' => [], 'series' => ['approved' => []]]);
            }

            $from = now()->subDays($days)->toDateString();
            $to   = now()->toDateString();

            $rows = DB::table('production_dprs')
                ->where('status', 'approved')
                ->whereBetween(DB::raw('DATE(dpr_date)'), [$from, $to])
                ->selectRaw('DATE(dpr_date) as dt, COUNT(*) as cnt')
                ->groupBy('dt')
                ->orderBy('dt')
                ->get();

            $map = [];
            foreach ($rows as $r) {
                $map[(string) $r->dt] = (int) $r->cnt;
            }

            $labels = [];
            $series = [];

            for ($i = $days; $i >= 0; $i--) {
                $d = now()->subDays($i)->toDateString();
                $labels[] = $d;
                $series[] = (int) ($map[$d] ?? 0);
            }

            return response()->json([
                'labels' => $labels,
                'series' => [
                    'approved' => $series,
                ],
            ]);
        });
    }

    // ---------------------------------------------------------------------
    // API: Finance - GST Summary (MTD Input vs Output)
    // ---------------------------------------------------------------------
    public function chartGstSummary(Request $request)
    {
        abort_unless(auth()->user()?->can('accounting.reports.view'), 403);

        $companyId = (int) config('accounting.default_company_id', 1);
        $cacheKey = "dash:chart:gst_summary:mtd:company:{$companyId}";

        return Cache::remember($cacheKey, 120, function () use ($companyId) {

            // needs: accounts + vouchers + voucher_lines
            if (!Schema::hasTable('accounts') || !Schema::hasTable('vouchers') || !Schema::hasTable('voucher_lines')) {
                return response()->json(['labels' => [], 'series' => []]);
            }

            // Resolve GST account ids from config (same pattern as GST reports)
            $codes = [
                'input_cgst'  => (string) config('accounting.gst.input_cgst_account_code'),
                'input_sgst'  => (string) config('accounting.gst.input_sgst_account_code'),
                'input_igst'  => (string) config('accounting.gst.input_igst_account_code'),
                'output_cgst' => (string) config('accounting.gst.cgst_output_account_code'),
                'output_sgst' => (string) config('accounting.gst.sgst_output_account_code'),
                'output_igst' => (string) config('accounting.gst.igst_output_account_code'),
            ];

            $accIds = [];
            foreach ($codes as $k => $code) {
                $code = trim($code);
                if ($code === '') continue;

                $id = DB::table('accounts')
                    ->where('company_id', $companyId)
                    ->where('code', $code)
                    ->value('id');

                if ($id) $accIds[$k] = (int) $id;
            }

            if (empty($accIds)) {
                return response()->json(['labels' => [], 'series' => []]);
            }

            $from = now()->startOfMonth()->toDateString();
            $to   = now()->toDateString();

            $sumLine = function (?int $accountId, string $mode) use ($companyId, $from, $to) {
                if (!$accountId) return 0.0;

                // input GST normally debit; output GST normally credit
                $field = $mode === 'input' ? 'debit' : 'credit';

                return (float) DB::table('voucher_lines as vl')
                    ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
                    ->where('v.company_id', $companyId)
                    ->where('v.status', 'posted')
                    ->whereBetween(DB::raw('DATE(v.voucher_date)'), [$from, $to])
                    ->where('vl.account_id', $accountId)
                    ->sum('vl.' . $field);
            };

            $inputCgst  = $sumLine($accIds['input_cgst'] ?? null, 'input');
            $inputSgst  = $sumLine($accIds['input_sgst'] ?? null, 'input');
            $inputIgst  = $sumLine($accIds['input_igst'] ?? null, 'input');

            $outputCgst = $sumLine($accIds['output_cgst'] ?? null, 'output');
            $outputSgst = $sumLine($accIds['output_sgst'] ?? null, 'output');
            $outputIgst = $sumLine($accIds['output_igst'] ?? null, 'output');

            return response()->json([
                'labels' => ['CGST', 'SGST', 'IGST'],
                'series' => [
                    'input'  => [round($inputCgst, 2), round($inputSgst, 2), round($inputIgst, 2)],
                    'output' => [round($outputCgst, 2), round($outputSgst, 2), round($outputIgst, 2)],
                ],
            ]);
        });
    }

    // ---------------------------------------------------------------------
    // API: Finance - Top Expense Ledgers (MTD) (bar)
    // ---------------------------------------------------------------------
    public function chartTopExpenses(Request $request)
    {
        abort_unless(auth()->user()?->can('accounting.reports.view'), 403);

        $companyId = (int) config('accounting.default_company_id', 1);
        $limit = max(5, min(12, (int) $request->get('limit', 7)));

        $cacheKey = "dash:chart:top_expenses:mtd:company:{$companyId}:limit:{$limit}";

        return Cache::remember($cacheKey, 120, function () use ($companyId, $limit) {

            if (!Schema::hasTable('accounts') || !Schema::hasTable('account_groups') || !Schema::hasTable('voucher_lines') || !Schema::hasTable('vouchers')) {
                return response()->json(['labels' => [], 'series' => ['amounts' => []]]);
            }

            $from = now()->startOfMonth()->toDateString();
            $to   = now()->toDateString();

            // Expense amount = debit - credit on expense-nature groups
            $rows = DB::table('voucher_lines as vl')
                ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
                ->join('accounts as a', 'a.id', '=', 'vl.account_id')
                ->join('account_groups as g', 'g.id', '=', 'a.account_group_id')
                ->where('v.company_id', $companyId)
                ->where('v.status', 'posted')
                ->whereBetween(DB::raw('DATE(v.voucher_date)'), [$from, $to])
                ->where('g.nature', 'expense')
                ->groupBy('vl.account_id', 'a.name')
                ->selectRaw('a.name as name')
                ->selectRaw('SUM(vl.debit - vl.credit) as amt')
                ->orderByDesc('amt')
                ->limit($limit)
                ->get();

            $labels = [];
            $amounts = [];

            foreach ($rows as $r) {
                $labels[] = (string) $r->name;
                $amounts[] = round((float) $r->amt, 2);
            }

            return response()->json([
                'labels' => $labels,
                'series' => [
                    'amounts' => $amounts,
                ],
            ]);
        });
    }

    // ---------------------------------------------------------------------
    // API: Store - Stock Mix by Category (doughnut)
    // ---------------------------------------------------------------------
    public function chartStockMixByCategory(Request $request)
    {
        abort_unless(auth()->user()?->can('store.stock.view') || auth()->user()?->can('store.stock_item.view'), 403);

        $cacheKey = "dash:chart:store:stock_mix:user:" . auth()->id();

        return Cache::remember($cacheKey, 120, function () {

            if (!Schema::hasTable('store_stock_items') || !Schema::hasTable('items')) {
                return response()->json(['labels' => [], 'series' => ['counts' => []]]);
            }

            // Prefer material_categories if present
            $hasCategories = Schema::hasTable('material_categories') && Schema::hasColumn('items', 'material_category_id');

            if ($hasCategories) {
                $rows = DB::table('store_stock_items as s')
                    ->join('items as i', 'i.id', '=', 's.item_id')
                    ->leftJoin('material_categories as c', 'c.id', '=', 'i.material_category_id')
                    ->where(function ($q) {
                        $q->where('s.weight_kg_available', '>', 0)
                          ->orWhere('s.qty_pcs_available', '>', 0);
                    })
                    ->groupBy(DB::raw("COALESCE(c.name,'Uncategorized')"))
                    ->selectRaw("COALESCE(c.name,'Uncategorized') as name")
                    ->selectRaw('COUNT(*) as cnt')
                    ->orderByDesc('cnt')
                    ->limit(10)
                    ->get();
            } else {
                // fallback: top 10 items by available stock lines
                $rows = DB::table('store_stock_items as s')
                    ->join('items as i', 'i.id', '=', 's.item_id')
                    ->where(function ($q) {
                        $q->where('s.weight_kg_available', '>', 0)
                          ->orWhere('s.qty_pcs_available', '>', 0);
                    })
                    ->groupBy('i.name')
                    ->selectRaw('i.name as name')
                    ->selectRaw('COUNT(*) as cnt')
                    ->orderByDesc('cnt')
                    ->limit(10)
                    ->get();
            }

            $labels = [];
            $counts = [];

            foreach ($rows as $r) {
                $labels[] = (string) $r->name;
                $counts[] = (int) $r->cnt;
            }

            return response()->json([
                'labels' => $labels,
                'series' => [
                    'counts' => $counts,
                ],
            ]);
        });
    }
}
