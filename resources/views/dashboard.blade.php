@extends('layouts.erp')

@section('title', 'Dashboard')

@section('page_header')
    <div>
        <h1 class="h5 mb-0">Dashboard</h1>
        <small class="text-muted">Live ERP command center across operations, finance, and execution.</small>
    </div>
@endsection

@section('content')
@php
    $has = fn (string $name) => \Illuminate\Support\Facades\Route::has($name);
@endphp

<ul class="nav nav-pills gap-2 mb-3 p-1 erp-filter-bar" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active rounded-pill px-3" data-bs-toggle="tab" data-bs-target="#tabOverview" type="button" role="tab">
            Overview
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link rounded-pill px-3" data-bs-toggle="tab" data-bs-target="#tabOperations" type="button" role="tab">
            Operations
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link rounded-pill px-3" data-bs-toggle="tab" data-bs-target="#tabFinance" type="button" role="tab">
            Finance
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tabOverview" role="tabpanel">
        <div class="row g-3 mb-3">
            <div class="col-xl-4">
                <div class="erp-surface h-100">
                    <div class="card-body d-flex flex-column h-100">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <h5 class="card-title mb-1">Welcome back</h5>
                                <p class="card-text small text-muted mb-0">
                                    Logged in as <span class="fw-semibold">{{ auth()->user()->name }}</span>.
                                </p>
                            </div>
                            <span class="badge rounded-pill text-bg-light" id="dashboardServerTime">Live</span>
                        </div>

                        <div class="small text-muted mb-3">
                            This dashboard now tracks commercial demand, procurement, inventory, execution, approvals, HR, and task pressure in one place.
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-auto">
                            @can('crm.lead.view')
                                @if($has('crm.leads.index'))
                                    <a href="{{ route('crm.leads.index') }}" class="btn btn-sm btn-outline-primary">Leads</a>
                                @endif
                            @endcan

                            @can('crm.quotation.view')
                                @if($has('crm.quotations.index'))
                                    <a href="{{ route('crm.quotations.index') }}" class="btn btn-sm btn-outline-secondary">Quotations</a>
                                @endif
                            @endcan

                            @can('project.project.view')
                                @if($has('projects.index'))
                                    <a href="{{ route('projects.index') }}" class="btn btn-sm btn-outline-secondary">Projects</a>
                                @endif
                            @endcan

                            @can('store.material_receipt.view')
                                @if($has('material-receipts.index'))
                                    <a href="{{ route('material-receipts.index') }}" class="btn btn-sm btn-outline-secondary">GRN</a>
                                @endif
                            @endcan

                            @can('tasks.view')
                                @if($has('tasks.index'))
                                    <a href="{{ route('tasks.index') }}" class="btn btn-sm btn-outline-secondary">Tasks</a>
                                @endif
                            @endcan

                            @if($has('my-approvals.index'))
                                <a href="{{ route('my-approvals.index') }}" class="btn btn-sm btn-outline-dark">My Approvals</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="row g-3">
                    @canany(['accounting.vouchers.view','accounting.reports.view'])
                    <div class="col-sm-6 col-xl-4">
                        <div class="erp-kpi h-100">
                            <div class="erp-kpi-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="erp-kpi-label">Net (MTD)</div>
                                        <div class="erp-kpi-value" id="kpiAccNet">—</div>
                                        <div class="erp-kpi-meta">Receipts minus payments</div>
                                    </div>
                                    <span class="erp-kpi-icon"><i class="bi bi-cash-coin"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <div class="erp-kpi h-100">
                            <div class="erp-kpi-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="erp-kpi-label">Cash / Bank Net</div>
                                        <div class="erp-kpi-value" id="kpiAccCash">—</div>
                                        <div class="erp-kpi-meta">Posted bank and cash balance</div>
                                    </div>
                                    <span class="erp-kpi-icon"><i class="bi bi-bank"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endcanany

                    @canany(['store.material_receipt.view','store.issue.view','store.stock.view'])
                    <div class="col-sm-6 col-xl-4">
                        <div class="erp-kpi h-100">
                            <div class="erp-kpi-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="erp-kpi-label">GRN (MTD)</div>
                                        <div class="erp-kpi-value" id="kpiStoreGrn">—</div>
                                        <div class="erp-kpi-meta">Material receipts this month</div>
                                    </div>
                                    <span class="erp-kpi-icon"><i class="bi bi-box-seam"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <div class="erp-kpi h-100">
                            <div class="erp-kpi-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="erp-kpi-label">Issues (MTD)</div>
                                        <div class="erp-kpi-value" id="kpiStoreIssue">—</div>
                                        <div class="erp-kpi-meta">Posted material issues</div>
                                    </div>
                                    <span class="erp-kpi-icon"><i class="bi bi-arrow-up-right-square"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endcanany

                    <div class="col-sm-6 col-xl-4">
                        <div class="erp-kpi h-100">
                            <div class="erp-kpi-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="erp-kpi-label">Pending Approvals</div>
                                        <div class="erp-kpi-value" id="kpiApprovalsPending">—</div>
                                        <div class="erp-kpi-meta">Your workflow queue</div>
                                    </div>
                                    <span class="erp-kpi-icon"><i class="bi bi-shield-check"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-6 col-xl-4">
                        <div class="erp-kpi h-100">
                            <div class="erp-kpi-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="erp-kpi-label">Tasks Due Today</div>
                                        <div class="erp-kpi-value" id="kpiTasksDueToday">—</div>
                                        <div class="erp-kpi-meta">Open tasks due today</div>
                                    </div>
                                    <span class="erp-kpi-icon"><i class="bi bi-list-check"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="row g-3">
                    @canany(['crm.lead.view','crm.quotation.view'])
                    <div class="col-md-6 col-xxl-4">
                        <div class="erp-surface h-100">
                            <div class="card-body">
                                <div class="text-uppercase small text-muted mb-2">CRM Pulse</div>
                                <div class="fw-semibold fs-5 mb-2"><span id="pulseCrmOpen">—</span> open leads</div>
                                <div class="d-flex justify-content-between small py-1 border-top">
                                    <span class="text-muted">Overdue follow-ups</span>
                                    <span class="fw-semibold" id="pulseCrmOverdue">—</span>
                                </div>
                                <div class="d-flex justify-content-between small py-1">
                                    <span class="text-muted">Accepted quotes (MTD)</span>
                                    <span class="fw-semibold" id="pulseCrmAccepted">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endcanany

                    @canany(['purchase.indent.view','purchase.rfq.view','purchase.po.view','purchase.bill.view'])
                    <div class="col-md-6 col-xxl-4">
                        <div class="erp-surface h-100">
                            <div class="card-body">
                                <div class="text-uppercase small text-muted mb-2">Purchase Pulse</div>
                                <div class="fw-semibold fs-5 mb-2"><span id="pulsePurchaseOpen">—</span> live purchase docs</div>
                                <div class="d-flex justify-content-between small py-1 border-top">
                                    <span class="text-muted">Open indents</span>
                                    <span class="fw-semibold" id="pulsePurchaseIndents">—</span>
                                </div>
                                <div class="d-flex justify-content-between small py-1">
                                    <span class="text-muted">Bills pending</span>
                                    <span class="fw-semibold" id="pulsePurchaseBills">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endcanany

                    @canany(['tasks.view','tasks.reports.view'])
                    <div class="col-md-6 col-xxl-4">
                        <div class="erp-surface h-100">
                            <div class="card-body">
                                <div class="text-uppercase small text-muted mb-2">Task Pulse</div>
                                <div class="fw-semibold fs-5 mb-2"><span id="pulseTasksOpen">—</span> open tasks</div>
                                <div class="d-flex justify-content-between small py-1 border-top">
                                    <span class="text-muted">Overdue</span>
                                    <span class="fw-semibold" id="pulseTasksOverdue">—</span>
                                </div>
                                <div class="d-flex justify-content-between small py-1">
                                    <span class="text-muted">Blocked</span>
                                    <span class="fw-semibold" id="pulseTasksBlocked">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endcanany

                    @canany(['hr.dashboard.view','hr.employee.view','hr.attendance.view','hr.leave.view','hr.payroll.view'])
                    <div class="col-md-6 col-xxl-4">
                        <div class="erp-surface h-100">
                            <div class="card-body">
                                <div class="text-uppercase small text-muted mb-2">HR Pulse</div>
                                <div class="fw-semibold fs-5 mb-2"><span id="pulseHrPresent">—</span> present today</div>
                                <div class="d-flex justify-content-between small py-1 border-top">
                                    <span class="text-muted">Active employees</span>
                                    <span class="fw-semibold" id="pulseHrEmployees">—</span>
                                </div>
                                <div class="d-flex justify-content-between small py-1">
                                    <span class="text-muted">Leave pending</span>
                                    <span class="fw-semibold" id="pulseHrLeavePending">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endcanany

                    @canany(['production.dpr.view','production.qc.perform','production.report.view'])
                    <div class="col-md-6 col-xxl-4">
                        <div class="erp-surface h-100">
                            <div class="card-body">
                                <div class="text-uppercase small text-muted mb-2">Production Pulse</div>
                                <div class="fw-semibold fs-5 mb-2"><span id="pulseProdApproved">—</span> approved DPRs (MTD)</div>
                                <div class="d-flex justify-content-between small py-1 border-top">
                                    <span class="text-muted">QC pending</span>
                                    <span class="fw-semibold" id="pulseProdQcPending">—</span>
                                </div>
                                <div class="d-flex justify-content-between small py-1">
                                    <span class="text-muted">Plans open</span>
                                    <span class="fw-semibold" id="pulseProdPlansOpen">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endcanany

                    @canany(['accounting.vouchers.view','accounting.reports.view'])
                    <div class="col-md-6 col-xxl-4">
                        <div class="erp-surface h-100">
                            <div class="card-body">
                                <div class="text-uppercase small text-muted mb-2">Finance Pulse</div>
                                <div class="fw-semibold fs-5 mb-2"><span id="pulseFinVouchers">—</span> posted vouchers (MTD)</div>
                                <div class="d-flex justify-content-between small py-1 border-top">
                                    <span class="text-muted">Receipts</span>
                                    <span class="fw-semibold" id="pulseFinReceipts">—</span>
                                </div>
                                <div class="d-flex justify-content-between small py-1">
                                    <span class="text-muted">GST net</span>
                                    <span class="fw-semibold" id="pulseFinGstNet">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endcanany
                </div>
            </div>

            <div class="col-xl-4">
                <div class="erp-surface h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 text-uppercase small text-muted">Attention Required</h6>
                            @if($has('my-approvals.index'))
                                <a href="{{ route('my-approvals.index') }}" class="btn btn-sm btn-outline-dark">Open Queue</a>
                            @endif
                        </div>
                        <div id="alertList" class="dashboard-alert-list">
                            <div class="text-muted small">Loading alert stack…</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tabOperations" role="tabpanel">
        <div class="row g-3">
            @canany(['store.material_receipt.view','store.issue.view','store.stock.view'])
            <div class="col-lg-7">
                <div class="erp-surface h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 text-uppercase small text-muted">Store: GRN vs Issues</h6>
                            <select id="storeDays" class="form-select form-select-sm" style="width:auto">
                                <option value="7">7 days</option>
                                <option value="30" selected>30 days</option>
                                <option value="90">90 days</option>
                            </select>
                        </div>
                        <canvas id="storeChart" height="130"></canvas>
                    </div>
                </div>
            </div>

            @canany(['store.stock.view','store.stock_item.view'])
            <div class="col-lg-5">
                <div class="erp-surface h-100">
                    <div class="card-body">
                        <h6 class="mb-2 text-uppercase small text-muted">Store: Stock Mix</h6>
                        <canvas id="stockMixChart" height="170"></canvas>
                        <div class="small text-muted mt-2">Top categories by available stock lines.</div>
                    </div>
                </div>
            </div>
            @endcanany
            @endcanany

            @canany(['production.dpr.view','production.report.view'])
            <div class="col-lg-7">
                <div class="erp-surface h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 text-uppercase small text-muted">Production: Approved DPR Trend</h6>
                            <select id="prodDays" class="form-select form-select-sm" style="width:auto">
                                <option value="7">7 days</option>
                                <option value="30" selected>30 days</option>
                                <option value="90">90 days</option>
                            </select>
                        </div>
                        <canvas id="prodChart" height="120"></canvas>
                    </div>
                </div>
            </div>
            @endcanany

            @canany(['purchase.indent.view','purchase.rfq.view','purchase.po.view','purchase.bill.view'])
            <div class="col-lg-5">
                <div class="erp-surface h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 text-uppercase small text-muted">Purchase Throughput</h6>
                            <select id="purchaseDays" class="form-select form-select-sm" style="width:auto">
                                <option value="7">7 days</option>
                                <option value="30" selected>30 days</option>
                                <option value="90">90 days</option>
                            </select>
                        </div>
                        <canvas id="purchaseChart" height="150"></canvas>
                    </div>
                </div>
            </div>
            @endcanany

            @canany(['crm.lead.view','crm.quotation.view'])
            <div class="col-lg-7">
                <div class="erp-surface h-100">
                    <div class="card-body">
                        <h6 class="mb-2 text-uppercase small text-muted">CRM: Open Pipeline by Stage</h6>
                        <canvas id="crmPipelineChart" height="130"></canvas>
                        <div class="small text-muted mt-2">Lead counts and pipeline value across active stages.</div>
                    </div>
                </div>
            </div>
            @endcanany

            @canany(['tasks.view','tasks.reports.view'])
            <div class="col-lg-5">
                <div class="erp-surface h-100">
                    <div class="card-body">
                        <h6 class="mb-2 text-uppercase small text-muted">Tasks: Open Work by Status</h6>
                        <canvas id="taskStatusChart" height="165"></canvas>
                        <div class="small text-muted mt-2">Visible open tasks grouped by active status.</div>
                    </div>
                </div>
            </div>
            @endcanany
        </div>
    </div>

    <div class="tab-pane fade" id="tabFinance" role="tabpanel">
        @canany(['accounting.vouchers.view','accounting.reports.view'])
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3">
                <div class="erp-kpi h-100">
                    <div class="erp-kpi-body">
                        <div class="erp-kpi-label">Receipts (MTD)</div>
                        <div class="erp-kpi-value" id="financeReceipts">—</div>
                        <div class="erp-kpi-meta">Posted receipt vouchers</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="erp-kpi h-100">
                    <div class="erp-kpi-body">
                        <div class="erp-kpi-label">Payments (MTD)</div>
                        <div class="erp-kpi-value" id="financePayments">—</div>
                        <div class="erp-kpi-meta">Posted payment vouchers</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="erp-kpi h-100">
                    <div class="erp-kpi-body">
                        <div class="erp-kpi-label">Vouchers (MTD)</div>
                        <div class="erp-kpi-value" id="financeVoucherCount">—</div>
                        <div class="erp-kpi-meta">Posted accounting documents</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="erp-kpi h-100">
                    <div class="erp-kpi-body">
                        <div class="erp-kpi-label">GST Net (MTD)</div>
                        <div class="erp-kpi-value" id="financeGstNet">—</div>
                        <div class="erp-kpi-meta">Output minus input GST</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-12">
                <div class="erp-surface">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 text-uppercase small text-muted">Cashflow (Receipts vs Payments)</h6>
                            <select id="cashflowDays" class="form-select form-select-sm" style="width:auto">
                                <option value="7">7 days</option>
                                <option value="30" selected>30 days</option>
                                <option value="90">90 days</option>
                            </select>
                        </div>
                        <canvas id="cashflowChart" height="95"></canvas>
                    </div>
                </div>
            </div>

            @can('accounting.reports.view')
            <div class="col-lg-6">
                <div class="erp-surface h-100">
                    <div class="card-body">
                        <h6 class="mb-2 text-uppercase small text-muted">GST Summary (MTD)</h6>
                        <canvas id="gstChart" height="145"></canvas>
                        <div class="small text-muted mt-2">Input vs Output GST totals for CGST, SGST, and IGST.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="erp-surface h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 text-uppercase small text-muted">Top Expenses (MTD)</h6>
                            <select id="expenseLimit" class="form-select form-select-sm" style="width:auto">
                                <option value="5">Top 5</option>
                                <option value="7" selected>Top 7</option>
                                <option value="10">Top 10</option>
                            </select>
                        </div>
                        <canvas id="expenseChart" height="145"></canvas>
                        <div class="small text-muted mt-2">Expense ledgers ranked by net debit this month.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="erp-surface h-100">
                    <div class="card-body">
                        <h6 class="mb-2 text-uppercase small text-muted">Voucher Mix (MTD)</h6>
                        <canvas id="voucherMixChart" height="185"></canvas>
                        <div class="small text-muted mt-2">Distribution of posted vouchers by type.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="erp-surface h-100">
                    <div class="card-body">
                        <div class="text-uppercase small text-muted mb-2">Finance Snapshot</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="dashboard-mini-stat">
                                    <div class="dashboard-mini-label">Input GST</div>
                                    <div class="dashboard-mini-value" id="financeInputGst">—</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="dashboard-mini-stat">
                                    <div class="dashboard-mini-label">Output GST</div>
                                    <div class="dashboard-mini-value" id="financeOutputGst">—</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="dashboard-mini-stat">
                                    <div class="dashboard-mini-label">Cash / Bank Net</div>
                                    <div class="dashboard-mini-value" id="financeCashNet">—</div>
                                </div>
                            </div>
                        </div>
                        <div class="small text-muted mt-3">
                            Use this tab as a daily control panel for cash movement, tax position, and accounting volume.
                        </div>
                    </div>
                </div>
            </div>
            @endcan
        </div>
        @endcanany
    </div>
</div>
@endsection

@push('styles')
<style>
.dashboard-alert-list .dashboard-alert-item + .dashboard-alert-item {
    margin-top: .75rem;
}
.dashboard-alert-item {
    border: 1px solid var(--bs-border-color);
    border-left-width: 4px;
    border-radius: .75rem;
    padding: .85rem 1rem;
    background: var(--bs-body-bg);
}
.dashboard-alert-item.tone-danger { border-left-color: var(--bs-danger); }
.dashboard-alert-item.tone-warning { border-left-color: var(--bs-warning); }
.dashboard-alert-item.tone-info { border-left-color: var(--bs-info); }
.dashboard-alert-item.tone-success { border-left-color: var(--bs-success); }
.dashboard-mini-stat {
    border: 1px solid var(--bs-border-color);
    border-radius: .9rem;
    padding: .95rem 1rem;
    height: 100%;
}
.dashboard-mini-label {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--bs-secondary-color);
    margin-bottom: .35rem;
}
.dashboard-mini-value {
    font-size: 1.35rem;
    font-weight: 700;
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let cashflowChart, storeChart, prodChart, gstChart, expenseChart, stockMixChart, crmPipelineChart, taskStatusChart, purchaseChart, voucherMixChart;

function fmtMoney(x) {
    if (x === null || x === undefined) return '—';
    const n = Number(x);
    if (Number.isNaN(n)) return '—';
    return n.toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function fmtInt(x) {
    if (x === null || x === undefined) return '—';
    const n = Number(x);
    if (Number.isNaN(n)) return '—';
    return n.toLocaleString('en-IN');
}

function setText(id, value, formatter = fmtInt) {
    const node = document.getElementById(id);
    if (!node) return;
    node.innerText = formatter(value);
}

function renderAlertList(alerts = []) {
    const container = document.getElementById('alertList');
    if (!container) return;

    if (!alerts.length) {
        container.innerHTML = '<div class="text-muted small">No urgent items are waiting right now.</div>';
        return;
    }

    container.innerHTML = alerts.map((alert) => {
        const urlOpen = alert.url ? `<a href="${alert.url}" class="stretched-link" aria-label="${alert.title}"></a>` : '';
        return `
            <div class="dashboard-alert-item tone-${alert.tone || 'warning'} position-relative">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="fw-semibold">${alert.title}</div>
                        <div class="small text-muted">${alert.meta || ''}</div>
                    </div>
                    <div class="fw-bold">${fmtInt(alert.value)}</div>
                </div>
                ${urlOpen}
            </div>
        `;
    }).join('');
}

const baseChartOptions = {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { display: true } },
    scales: { y: { beginAtZero: true } }
};

async function loadKpis() {
    const res = await fetch(`{{ route('dashboard.api.summary') }}`);
    const data = await res.json();

    const acc = data.kpis?.accounting;
    if (acc) {
        setText('kpiAccNet', acc.net_mtd, fmtMoney);
        setText('kpiAccCash', acc.cash_net, fmtMoney);
        setText('pulseFinVouchers', acc.voucher_count_mtd);
        setText('pulseFinReceipts', acc.receipts_mtd, fmtMoney);
        setText('pulseFinGstNet', acc.gst_net_mtd, fmtMoney);
        setText('financeReceipts', acc.receipts_mtd, fmtMoney);
        setText('financePayments', acc.payments_mtd, fmtMoney);
        setText('financeVoucherCount', acc.voucher_count_mtd);
        setText('financeGstNet', acc.gst_net_mtd, fmtMoney);
        setText('financeInputGst', acc.gst_input_mtd, fmtMoney);
        setText('financeOutputGst', acc.gst_output_mtd, fmtMoney);
        setText('financeCashNet', acc.cash_net, fmtMoney);
    }

    const store = data.kpis?.store;
    if (store) {
        setText('kpiStoreGrn', store.grn_mtd);
        setText('kpiStoreIssue', store.issues_mtd);
    }

    const approvals = data.kpis?.approvals;
    if (approvals) {
        setText('kpiApprovalsPending', approvals.pending);
    }

    const tasks = data.kpis?.tasks;
    if (tasks) {
        setText('kpiTasksDueToday', tasks.due_today);
        setText('pulseTasksOpen', tasks.open);
        setText('pulseTasksOverdue', tasks.overdue);
        setText('pulseTasksBlocked', tasks.blocked);
    }

    const crm = data.kpis?.crm;
    if (crm) {
        setText('pulseCrmOpen', crm.open_leads);
        setText('pulseCrmOverdue', crm.overdue_followups);
        setText('pulseCrmAccepted', crm.accepted_quotes_mtd);
    }

    const purchase = data.kpis?.purchase;
    if (purchase) {
        const openDocs = [purchase.indents_open, purchase.rfqs_open, purchase.pos_open]
            .map((value) => Number(value || 0))
            .reduce((sum, value) => sum + value, 0);
        setText('pulsePurchaseOpen', openDocs);
        setText('pulsePurchaseIndents', purchase.indents_open);
        setText('pulsePurchaseBills', purchase.bills_pending);
    }

    const hr = data.kpis?.hr;
    if (hr) {
        setText('pulseHrPresent', hr.present_today);
        setText('pulseHrEmployees', hr.active_employees);
        setText('pulseHrLeavePending', hr.leave_pending);
    }

    const production = data.kpis?.production;
    if (production) {
        setText('pulseProdApproved', production.approved_dpr_mtd);
        setText('pulseProdQcPending', production.qc_pending);
        setText('pulseProdPlansOpen', production.plans_open);
    }

    if (data.server_time) {
        const serverNode = document.getElementById('dashboardServerTime');
        if (serverNode) serverNode.innerText = data.server_time;
    }

    renderAlertList(data.alerts || []);
}

async function loadCashflow(days = 30) {
    const res = await fetch(`{{ route('dashboard.api.charts.cashflow') }}?days=${days}`);
    const data = await res.json();
    const ctx = document.getElementById('cashflowChart');
    if (!ctx) return;
    if (cashflowChart) cashflowChart.destroy();

    cashflowChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [
                { label: 'Receipts', data: (data.series?.receipts || []), borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.12)', tension: 0.35 },
                { label: 'Payments', data: (data.series?.payments || []), borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,.12)', tension: 0.35 },
            ]
        },
        options: baseChartOptions
    });
}

async function loadStore(days = 30) {
    const res = await fetch(`{{ route('dashboard.api.charts.store_grn_issue') }}?days=${days}`);
    const data = await res.json();
    const ctx = document.getElementById('storeChart');
    if (!ctx) return;
    if (storeChart) storeChart.destroy();

    storeChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [
                { label: 'GRN', data: (data.series?.grn || []), backgroundColor: '#0d6efd' },
                { label: 'Issues', data: (data.series?.issues || []), backgroundColor: '#fd7e14' },
            ]
        },
        options: baseChartOptions
    });
}

async function loadProd(days = 30) {
    const res = await fetch(`{{ route('dashboard.api.charts.production_dpr') }}?days=${days}`);
    const data = await res.json();
    const ctx = document.getElementById('prodChart');
    if (!ctx) return;
    if (prodChart) prodChart.destroy();

    prodChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [
                { label: 'Approved DPR', data: (data.series?.approved || []), borderColor: '#6f42c1', backgroundColor: 'rgba(111,66,193,.12)', tension: 0.35, fill: true },
            ]
        },
        options: baseChartOptions
    });
}

async function loadGst() {
    const res = await fetch(`{{ route('dashboard.api.charts.gst_summary') }}`);
    const data = await res.json();
    const ctx = document.getElementById('gstChart');
    if (!ctx) return;
    if (gstChart) gstChart.destroy();

    gstChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [
                { label: 'Input GST', data: (data.series?.input || []), backgroundColor: '#20c997' },
                { label: 'Output GST', data: (data.series?.output || []), backgroundColor: '#dc3545' },
            ]
        },
        options: baseChartOptions
    });
}

async function loadTopExpenses(limit = 7) {
    const res = await fetch(`{{ route('dashboard.api.charts.top_expenses') }}?limit=${limit}`);
    const data = await res.json();
    const ctx = document.getElementById('expenseChart');
    if (!ctx) return;
    if (expenseChart) expenseChart.destroy();

    expenseChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [
                { label: 'Amount', data: (data.series?.amounts || []), backgroundColor: '#198754' },
            ]
        },
        options: {
            ...baseChartOptions,
            plugins: { legend: { display: false } }
        }
    });
}

async function loadStockMix() {
    const res = await fetch(`{{ route('dashboard.api.charts.store_stock_mix') }}`);
    const data = await res.json();
    const ctx = document.getElementById('stockMixChart');
    if (!ctx) return;
    if (stockMixChart) stockMixChart.destroy();

    stockMixChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels || [],
            datasets: [
                {
                    label: 'Stock lines',
                    data: (data.series?.counts || []),
                    backgroundColor: ['#0d6efd', '#20c997', '#ffc107', '#fd7e14', '#6f42c1', '#198754', '#dc3545', '#6610f2', '#6c757d', '#0dcaf0']
                },
            ]
        },
        options: { responsive: true, plugins: { legend: { display: true } } }
    });
}

async function loadCrmPipeline() {
    const res = await fetch(`{{ route('dashboard.api.charts.crm_pipeline') }}`);
    const data = await res.json();
    const ctx = document.getElementById('crmPipelineChart');
    if (!ctx) return;
    if (crmPipelineChart) crmPipelineChart.destroy();

    crmPipelineChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [
                { label: 'Lead Count', data: (data.series?.counts || []), backgroundColor: '#0d6efd', yAxisID: 'y' },
                { label: 'Pipeline Value', data: (data.series?.values || []), backgroundColor: '#20c997', yAxisID: 'y1' },
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, position: 'left' },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
            }
        }
    });
}

async function loadTaskStatus() {
    const res = await fetch(`{{ route('dashboard.api.charts.task_status') }}`);
    const data = await res.json();
    const ctx = document.getElementById('taskStatusChart');
    if (!ctx) return;
    if (taskStatusChart) taskStatusChart.destroy();

    taskStatusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels || [],
            datasets: [
                {
                    data: (data.series?.counts || []),
                    backgroundColor: ['#0d6efd', '#ffc107', '#20c997', '#dc3545', '#6f42c1', '#6c757d', '#fd7e14']
                },
            ]
        },
        options: { responsive: true, plugins: { legend: { display: true } } }
    });
}

async function loadPurchase(days = 30) {
    const res = await fetch(`{{ route('dashboard.api.charts.purchase_throughput') }}?days=${days}`);
    const data = await res.json();
    const ctx = document.getElementById('purchaseChart');
    if (!ctx) return;
    if (purchaseChart) purchaseChart.destroy();

    purchaseChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [
                { label: 'Indents', data: (data.series?.indents || []), borderColor: '#0d6efd', tension: 0.3 },
                { label: 'RFQs', data: (data.series?.rfqs || []), borderColor: '#6f42c1', tension: 0.3 },
                { label: 'POs', data: (data.series?.pos || []), borderColor: '#fd7e14', tension: 0.3 },
                { label: 'Bills', data: (data.series?.bills || []), borderColor: '#198754', tension: 0.3 },
            ]
        },
        options: baseChartOptions
    });
}

async function loadVoucherMix() {
    const res = await fetch(`{{ route('dashboard.api.charts.voucher_mix') }}`);
    const data = await res.json();
    const ctx = document.getElementById('voucherMixChart');
    if (!ctx) return;
    if (voucherMixChart) voucherMixChart.destroy();

    voucherMixChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels || [],
            datasets: [
                {
                    data: (data.series?.counts || []),
                    backgroundColor: ['#0d6efd', '#20c997', '#fd7e14', '#dc3545', '#6f42c1', '#6c757d']
                },
            ]
        },
        options: { responsive: true, plugins: { legend: { display: true } } }
    });
}

document.getElementById('cashflowDays')?.addEventListener('change', (e) => loadCashflow(parseInt(e.target.value, 10)));
document.getElementById('storeDays')?.addEventListener('change', (e) => loadStore(parseInt(e.target.value, 10)));
document.getElementById('prodDays')?.addEventListener('change', (e) => loadProd(parseInt(e.target.value, 10)));
document.getElementById('expenseLimit')?.addEventListener('change', (e) => loadTopExpenses(parseInt(e.target.value, 10)));
document.getElementById('purchaseDays')?.addEventListener('change', (e) => loadPurchase(parseInt(e.target.value, 10)));

loadKpis();
loadCashflow(30);
loadStore(30);
loadStockMix();
loadProd(30);
loadGst();
loadTopExpenses(7);
loadCrmPipeline();
loadTaskStatus();
loadPurchase(30);
loadVoucherMix();
</script>
@endpush
