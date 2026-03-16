<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Hr\HrAttendance;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrLeaveApplication;
use App\Models\Hr\HrPayrollPeriod;
use App\Models\Tasks\Task;
use App\Services\Store\LowStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
            [$fromMonth, $toToday] = $this->monthDateRange();

            $out = [
                'ok' => true,
                'server_time' => now()->toDateTimeString(),
                'kpis' => [
                    'accounting' => null,
                    'store' => null,
                    'production' => null,
                    'crm' => null,
                    'purchase' => null,
                    'tasks' => null,
                    'hr' => null,
                    'approvals' => null,
                ],
                'alerts' => [],
            ];

            // ----------------------
            // Accounting KPIs
            // ----------------------
            if ($this->canAny(['accounting.vouchers.view', 'accounting.reports.view'])) {

                $receipts = 0.0;
                $payments = 0.0;
                $voucherCount = 0;

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

                    $voucherCount = (int) DB::table('vouchers')
                        ->where('company_id', $companyId)
                        ->where('status', 'posted')
                        ->whereBetween(DB::raw('DATE(voucher_date)'), [$fromMonth, $toToday])
                        ->count();
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

                $gstTotals = $this->canAny(['accounting.reports.view'])
                    ? $this->resolveGstTotals($companyId, $fromMonth, $toToday)
                    : ['input_total' => null, 'output_total' => null, 'net_total' => null];

                $out['kpis']['accounting'] = [
                    'receipts_mtd' => round($receipts, 2),
                    'payments_mtd' => round($payments, 2),
                    'net_mtd'      => round($receipts - $payments, 2),
                    'cash_net'     => $cashBalance !== null ? round($cashBalance, 2) : null,
                    'voucher_count_mtd' => $voucherCount,
                    'gst_input_mtd' => $gstTotals['input_total'],
                    'gst_output_mtd' => $gstTotals['output_total'],
                    'gst_net_mtd' => $gstTotals['net_total'],
                ];
            }

            // ----------------------
            // Store KPIs
            // ----------------------
            if ($this->canAny(['store.material_receipt.view', 'store.issue.view', 'store.stock.view'])) {

                $grnCount = null;
                $grnQcPending = null;
                if (Schema::hasTable('material_receipts')) {
                    $grnCount = (int) DB::table('material_receipts')
                        ->whereBetween(DB::raw('DATE(receipt_date)'), [$fromMonth, $toToday])
                        ->count();

                    if (Schema::hasColumn('material_receipts', 'status')) {
                        $grnQcPending = (int) DB::table('material_receipts')
                            ->where('status', 'qc_pending')
                            ->count();
                    }
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

                $lowStock = null;
                if (Schema::hasTable('store_reorder_levels') && Schema::hasTable('store_stock_items')) {
                    $levels = DB::table('store_reorder_levels')
                        ->where('is_active', true)
                        ->select('id', 'item_id', 'brand', 'project_id', 'min_qty', 'target_qty')
                        ->get();

                    if ($levels->isNotEmpty()) {
                        $rows = app(LowStockService::class)->buildLowStockRows($levels);
                        $lowStock = collect($rows)->where('is_low', true)->count();
                    } else {
                        $lowStock = 0;
                    }
                }

                $out['kpis']['store'] = [
                    'grn_mtd' => $grnCount,
                    'issues_mtd' => $issueCount,
                    'stock_lines_available' => $stockLines,
                    'grn_qc_pending' => $grnQcPending,
                    'low_stock' => $lowStock,
                ];
            }

            // ----------------------
            // Production KPIs
            // ----------------------
            if ($this->canAny(['production.dpr.view', 'production.qc.perform', 'production.report.view'])) {

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

                $plansOpen = null;
                if (Schema::hasTable('production_plans') && Schema::hasColumn('production_plans', 'status')) {
                    $plansOpen = (int) DB::table('production_plans')
                        ->whereNotIn('status', ['cancelled', 'closed', 'completed'])
                        ->count();
                }

                $out['kpis']['production'] = [
                    'approved_dpr_mtd' => $dprApproved,
                    'qc_pending' => $qcPending,
                    'plans_open' => $plansOpen,
                ];
            }

            // ----------------------
            // CRM KPIs
            // ----------------------
            if ($this->canAny(['crm.lead.view', 'crm.quotation.view'])) {
                $openLeads = null;
                $overdueFollowups = null;
                $quotationsMtd = null;
                $acceptedQuotesMtd = null;

                if (Schema::hasTable('crm_leads')) {
                    $openLeads = (int) DB::table('crm_leads')
                        ->where('status', 'open')
                        ->count();
                }

                if (Schema::hasTable('crm_lead_activities')) {
                    $overdueFollowups = (int) DB::table('crm_lead_activities')
                        ->whereNull('done_at')
                        ->whereNotNull('due_at')
                        ->where('due_at', '<', now())
                        ->count();
                }

                if (Schema::hasTable('crm_quotations')) {
                    $quotationsMtd = (int) DB::table('crm_quotations')
                        ->whereBetween(DB::raw('DATE(created_at)'), [$fromMonth, $toToday])
                        ->count();

                    $acceptedQuotesMtd = (int) DB::table('crm_quotations')
                        ->where('status', 'accepted')
                        ->whereBetween(DB::raw('DATE(accepted_at)'), [$fromMonth, $toToday])
                        ->count();
                }

                $out['kpis']['crm'] = [
                    'open_leads' => $openLeads,
                    'overdue_followups' => $overdueFollowups,
                    'quotations_mtd' => $quotationsMtd,
                    'accepted_quotes_mtd' => $acceptedQuotesMtd,
                ];
            }

            // ----------------------
            // Purchase KPIs
            // ----------------------
            if ($this->canAny(['purchase.indent.view', 'purchase.rfq.view', 'purchase.po.view', 'purchase.bill.view'])) {
                $out['kpis']['purchase'] = [
                    'indents_open' => $this->countOpenStatusRows('purchase_indents'),
                    'rfqs_open' => $this->countOpenStatusRows('purchase_rfqs'),
                    'pos_open' => $this->countOpenStatusRows('purchase_orders'),
                    'bills_pending' => $this->countPendingPostingRows('purchase_bills'),
                ];
            }

            // ----------------------
            // Task KPIs
            // ----------------------
            if ($this->canAny(['tasks.view', 'tasks.reports.view']) && Schema::hasTable('tasks') && Schema::hasTable('task_lists')) {
                $tasks = $this->visibleTaskSnapshot();

                $out['kpis']['tasks'] = [
                    'open' => $tasks->where('is_open', true)->count(),
                    'overdue' => $tasks->where('is_overdue', true)->count(),
                    'due_today' => $tasks->where('is_due_today', true)->count(),
                    'blocked' => $tasks->where('is_blocked', true)->count(),
                ];
            }

            // ----------------------
            // HR KPIs
            // ----------------------
            if ($this->canAny(['hr.dashboard.view', 'hr.employee.view', 'hr.attendance.view', 'hr.leave.view', 'hr.payroll.view'])) {
                $activeEmployees = Schema::hasTable((new HrEmployee())->getTable()) ? HrEmployee::query()->whereNull('date_of_leaving')->count() : null;
                $presentToday = Schema::hasTable((new HrAttendance())->getTable())
                    ? HrAttendance::query()
                        ->whereDate('attendance_date', today())
                        ->whereIn('status', ['present', 'late', 'half_day'])
                        ->count()
                    : null;
                $leavePending = Schema::hasTable('hr_leave_applications')
                    ? HrLeaveApplication::query()->pending()->count()
                    : null;
                $payrollOpen = Schema::hasTable((new HrPayrollPeriod())->getTable())
                    ? HrPayrollPeriod::query()->where('status', '!=', 'paid')->count()
                    : null;

                $out['kpis']['hr'] = [
                    'active_employees' => $activeEmployees,
                    'present_today' => $presentToday,
                    'leave_pending' => $leavePending,
                    'payroll_open' => $payrollOpen,
                ];
            }

            // ----------------------
            // Approval KPIs
            // ----------------------
            if (Schema::hasTable('approval_requests') && Schema::hasTable('approval_steps')) {
                $out['kpis']['approvals'] = [
                    'pending' => ApprovalRequest::query()->pendingForApprover(auth()->user())->count(),
                ];
            }

            $out['alerts'] = $this->buildAlerts($out['kpis']);

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
    // API: CRM - Open Pipeline by Stage (bar)
    // ---------------------------------------------------------------------
    public function chartCrmPipeline(Request $request)
    {
        abort_unless($this->canAny(['crm.lead.view', 'crm.quotation.view']), 403);

        $cacheKey = 'dash:chart:crm_pipeline:user:' . auth()->id();

        return Cache::remember($cacheKey, 120, function () {
            if (!Schema::hasTable('crm_leads')) {
                return response()->json(['labels' => [], 'series' => ['counts' => [], 'values' => []]]);
            }

            $hasStage = Schema::hasColumn('crm_leads', 'lead_stage_id') && Schema::hasTable('crm_lead_stages');

            $rows = DB::table('crm_leads as l')
                ->when($hasStage, function ($query) {
                    $query->leftJoin('crm_lead_stages as s', 's.id', '=', 'l.lead_stage_id')
                        ->groupBy(DB::raw("COALESCE(s.name, 'Unstaged')"))
                        ->selectRaw("COALESCE(s.name, 'Unstaged') as stage_name");
                }, function ($query) {
                    $query->groupBy('l.status')
                        ->selectRaw('COALESCE(l.status, ? ) as stage_name', ['Open']);
                })
                ->where('l.status', 'open')
                ->selectRaw('COUNT(*) as lead_count')
                ->selectRaw('COALESCE(SUM(l.expected_value), 0) as expected_value')
                ->orderByDesc('lead_count')
                ->limit(8)
                ->get();

            return response()->json([
                'labels' => $rows->pluck('stage_name')->all(),
                'series' => [
                    'counts' => $rows->map(fn ($row) => (int) $row->lead_count)->all(),
                    'values' => $rows->map(fn ($row) => round((float) $row->expected_value, 2))->all(),
                ],
            ]);
        });
    }

    // ---------------------------------------------------------------------
    // API: Tasks - Visible Open Tasks by Status (doughnut)
    // ---------------------------------------------------------------------
    public function chartTaskStatus(Request $request)
    {
        abort_unless($this->canAny(['tasks.view', 'tasks.reports.view']), 403);

        $cacheKey = 'dash:chart:task_status:user:' . auth()->id();

        return Cache::remember($cacheKey, 60, function () {
            if (!Schema::hasTable('tasks') || !Schema::hasTable('task_lists')) {
                return response()->json(['labels' => [], 'series' => ['counts' => []]]);
            }

            $tasks = $this->visibleTaskSnapshot()->where('is_open', true);
            $grouped = $tasks->groupBy(fn (array $task) => $task['status_name'] ?: 'Open');

            $labels = [];
            $counts = [];

            foreach ($grouped->sortByDesc(fn ($group) => $group->count()) as $statusName => $group) {
                $labels[] = (string) $statusName;
                $counts[] = $group->count();
            }

            return response()->json([
                'labels' => $labels,
                'series' => [
                    'counts' => $counts,
                ],
            ]);
        });
    }

    // ---------------------------------------------------------------------
    // API: Purchase - Document Throughput (line)
    // ---------------------------------------------------------------------
    public function chartPurchaseThroughput(Request $request)
    {
        abort_unless($this->canAny(['purchase.indent.view', 'purchase.rfq.view', 'purchase.po.view', 'purchase.bill.view']), 403);

        $days = max(7, min(90, (int) $request->get('days', 30)));
        $cacheKey = 'dash:chart:purchase_throughput:user:' . auth()->id() . ':days:' . $days;

        return Cache::remember($cacheKey, 60, function () use ($days) {
            $labels = [];
            $series = [
                'indents' => [],
                'rfqs' => [],
                'pos' => [],
                'bills' => [],
            ];

            $rangeDates = [];
            for ($i = $days; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $labels[] = $date;
                $rangeDates[] = $date;
            }

            $maps = [
                'indents' => $this->dailyCountMap('purchase_indents', 'created_at', $rangeDates),
                'rfqs' => $this->dailyCountMap('purchase_rfqs', 'rfq_date', $rangeDates, 'created_at'),
                'pos' => $this->dailyCountMap('purchase_orders', 'po_date', $rangeDates, 'created_at'),
                'bills' => $this->dailyCountMap('purchase_bills', 'bill_date', $rangeDates, 'created_at'),
            ];

            foreach ($rangeDates as $date) {
                foreach ($series as $key => $values) {
                    $series[$key][] = (int) ($maps[$key][$date] ?? 0);
                }
            }

            return response()->json([
                'labels' => $labels,
                'series' => $series,
            ]);
        });
    }

    // ---------------------------------------------------------------------
    // API: Finance - Voucher Mix (MTD) (doughnut)
    // ---------------------------------------------------------------------
    public function chartVoucherMix(Request $request)
    {
        abort_unless($this->canAny(['accounting.vouchers.view', 'accounting.reports.view']), 403);

        $companyId = (int) config('accounting.default_company_id', 1);
        [$fromMonth, $toToday] = $this->monthDateRange();
        $cacheKey = 'dash:chart:voucher_mix:company:' . $companyId . ':user:' . auth()->id();

        return Cache::remember($cacheKey, 120, function () use ($companyId, $fromMonth, $toToday) {
            if (!Schema::hasTable('vouchers')) {
                return response()->json(['labels' => [], 'series' => ['counts' => []]]);
            }

            $rows = DB::table('vouchers')
                ->where('company_id', $companyId)
                ->where('status', 'posted')
                ->whereBetween(DB::raw('DATE(voucher_date)'), [$fromMonth, $toToday])
                ->groupBy('voucher_type')
                ->selectRaw("COALESCE(voucher_type, 'journal') as voucher_type")
                ->selectRaw('COUNT(*) as cnt')
                ->orderByDesc('cnt')
                ->get();

            return response()->json([
                'labels' => $rows->map(fn ($row) => ucfirst((string) $row->voucher_type))->all(),
                'series' => [
                    'counts' => $rows->map(fn ($row) => (int) $row->cnt)->all(),
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

    protected function canAny(array $permissions): bool
    {
        $user = auth()->user();

        foreach ($permissions as $permission) {
            if ($user?->can($permission)) {
                return true;
            }
        }

        return false;
    }

    protected function monthDateRange(): array
    {
        return [now()->startOfMonth()->toDateString(), now()->toDateString()];
    }

    protected function resolveGstTotals(int $companyId, string $from, string $to): array
    {
        if (!Schema::hasTable('accounts') || !Schema::hasTable('voucher_lines') || !Schema::hasTable('vouchers')) {
            return ['input_total' => null, 'output_total' => null, 'net_total' => null];
        }

        $codes = [
            'input_cgst'  => (string) config('accounting.gst.input_cgst_account_code'),
            'input_sgst'  => (string) config('accounting.gst.input_sgst_account_code'),
            'input_igst'  => (string) config('accounting.gst.input_igst_account_code'),
            'output_cgst' => (string) config('accounting.gst.cgst_output_account_code'),
            'output_sgst' => (string) config('accounting.gst.sgst_output_account_code'),
            'output_igst' => (string) config('accounting.gst.igst_output_account_code'),
        ];

        $accountIds = [];
        foreach ($codes as $key => $code) {
            $code = trim($code);
            if ($code === '') {
                continue;
            }

            $id = DB::table('accounts')
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->value('id');

            if ($id) {
                $accountIds[$key] = (int) $id;
            }
        }

        $sumAccountField = function (?int $accountId, string $field) use ($companyId, $from, $to): float {
            if (!$accountId) {
                return 0.0;
            }

            return (float) DB::table('voucher_lines as vl')
                ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
                ->where('v.company_id', $companyId)
                ->where('v.status', 'posted')
                ->whereBetween(DB::raw('DATE(v.voucher_date)'), [$from, $to])
                ->where('vl.account_id', $accountId)
                ->sum('vl.' . $field);
        };

        $inputTotal = $sumAccountField($accountIds['input_cgst'] ?? null, 'debit')
            + $sumAccountField($accountIds['input_sgst'] ?? null, 'debit')
            + $sumAccountField($accountIds['input_igst'] ?? null, 'debit');

        $outputTotal = $sumAccountField($accountIds['output_cgst'] ?? null, 'credit')
            + $sumAccountField($accountIds['output_sgst'] ?? null, 'credit')
            + $sumAccountField($accountIds['output_igst'] ?? null, 'credit');

        return [
            'input_total' => round($inputTotal, 2),
            'output_total' => round($outputTotal, 2),
            'net_total' => round($outputTotal - $inputTotal, 2),
        ];
    }

    protected function countOpenStatusRows(string $table): ?int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'status')) {
            return null;
        }

        return (int) DB::table($table)
            ->whereNotIn('status', ['paid', 'completed', 'closed', 'cancelled', 'rejected', 'reversed'])
            ->count();
    }

    protected function countPendingPostingRows(string $table): ?int
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        if (Schema::hasColumn($table, 'status')) {
            return (int) DB::table($table)
                ->whereNotIn('status', ['posted', 'paid', 'cancelled', 'reversed'])
                ->count();
        }

        return null;
    }

    protected function visibleTaskSnapshot()
    {
        return Task::query()
            ->visibleToUser(auth()->user())
            ->notArchived()
            ->with('status:id,name,is_closed')
            ->get(['id', 'status_id', 'due_date', 'is_blocked'])
            ->map(function (Task $task) {
                $statusClosed = (bool) ($task->status?->is_closed ?? $task->completed_at !== null);

                return [
                    'id' => $task->id,
                    'status_name' => $task->status?->name,
                    'is_open' => !$statusClosed,
                    'is_overdue' => !$statusClosed && $task->due_date && $task->due_date->lt(now()->startOfDay()),
                    'is_due_today' => !$statusClosed && $task->due_date && $task->due_date->isToday(),
                    'is_blocked' => (bool) $task->is_blocked,
                ];
            });
    }

    protected function buildAlerts(array $kpis): array
    {
        $alerts = [];

        $push = function (string $title, ?int $value, string $meta, ?string $routeName = null, string $tone = 'warning') use (&$alerts) {
            if ($value === null || $value <= 0) {
                return;
            }

            $alerts[] = [
                'title' => $title,
                'value' => $value,
                'meta' => $meta,
                'url' => $routeName && Route::has($routeName) ? route($routeName) : null,
                'tone' => $tone,
            ];
        };

        $push('Approvals waiting', $kpis['approvals']['pending'] ?? null, 'Documents pending your action', 'my-approvals.index', 'danger');
        $push('Overdue tasks', $kpis['tasks']['overdue'] ?? null, 'Task follow-up is slipping', 'tasks.index', 'danger');
        $push('Overdue CRM follow-ups', $kpis['crm']['overdue_followups'] ?? null, 'Sales follow-ups need attention', 'crm.dashboard', 'warning');
        $push('Low stock items', $kpis['store']['low_stock'] ?? null, 'Reorder thresholds breached', 'store-low-stock.index', 'warning');
        $push('GRN QC pending', $kpis['store']['grn_qc_pending'] ?? null, 'Receipts waiting for quality clearance', 'material-receipts.index', 'warning');
        $push('Production QC pending', $kpis['production']['qc_pending'] ?? null, 'Checks pending in production', 'production.production-qc.index', 'warning');
        $push('Leave approvals pending', $kpis['hr']['leave_pending'] ?? null, 'HR approvals waiting', 'hr.leave-applications.index', 'info');

        usort($alerts, fn (array $a, array $b) => ($b['value'] <=> $a['value']));

        return array_slice($alerts, 0, 6);
    }

    protected function dailyCountMap(string $table, string $primaryDateColumn, array $rangeDates, ?string $fallbackDateColumn = null): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $dateColumn = Schema::hasColumn($table, $primaryDateColumn)
            ? $primaryDateColumn
            : ($fallbackDateColumn && Schema::hasColumn($table, $fallbackDateColumn) ? $fallbackDateColumn : null);

        if (!$dateColumn) {
            return [];
        }

        $rows = DB::table($table)
            ->whereBetween(DB::raw('DATE(' . $dateColumn . ')'), [$rangeDates[0], end($rangeDates)])
            ->selectRaw('DATE(' . $dateColumn . ') as dt')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('dt')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->dt] = (int) $row->cnt;
        }

        return $map;
    }
}
