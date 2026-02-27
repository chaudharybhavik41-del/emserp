@extends('layouts.erp')

@section('title', 'Dashboard')

@section('page_header')
    <div>
        <h1 class="h5 mb-0">Dashboard</h1>
        <small class="text-muted">Interactive KPIs & trends across modules.</small>
    </div>
@endsection

@section('content')
@php
    $has = fn(string $name) => \Illuminate\Support\Facades\Route::has($name);
@endphp

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabOverview" type="button" role="tab">
            Overview
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabOperations" type="button" role="tab">
            Operations
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabFinance" type="button" role="tab">
            Finance
        </button>
    </li>
</ul>

<div class="tab-content">

    {{-- ========================================================= --}}
    {{-- OVERVIEW --}}
    {{-- ========================================================= --}}
    <div class="tab-pane fade show active" id="tabOverview" role="tabpanel">
        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-1">Welcome back</h5>
                        <p class="card-text small text-muted mb-3">
                            You are logged in as <span class="fw-semibold">{{ auth()->user()->name }}</span>.
                        </p>

                        <div class="d-flex flex-wrap gap-2">
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
                                    <a href="{{ route('material-receipts.index') }}" class="btn btn-sm btn-outline-dark">GRN</a>
                                @endif
                            @endcan

                            @can('accounting.vouchers.view')
                                @if($has('accounting.vouchers.index'))
                                    <a href="{{ route('accounting.vouchers.index') }}" class="btn btn-sm btn-outline-dark">Vouchers</a>
                                @endif
                            @endcan
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="row g-3">
                    @canany(['accounting.vouchers.view','accounting.reports.view'])
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-muted small text-uppercase">Net (MTD)</div>
                                        <div class="h4 mb-0" id="kpiAccNet">—</div>
                                        <div class="small text-muted">Receipts − Payments</div>
                                    </div>
                                    <i class="bi bi-cash-coin fs-4 text-muted"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-muted small text-uppercase">Cash/Bank Net</div>
                                        <div class="h4 mb-0" id="kpiAccCash">—</div>
                                        <div class="small text-muted">Ledger net (posted)</div>
                                    </div>
                                    <i class="bi bi-bank fs-4 text-muted"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endcanany

                    @canany(['store.material_receipt.view','store.issue.view','store.stock.view'])
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-muted small text-uppercase">GRN (MTD)</div>
                                        <div class="h4 mb-0" id="kpiStoreGrn">—</div>
                                        <div class="small text-muted">Material receipts</div>
                                    </div>
                                    <i class="bi bi-box-seam fs-4 text-muted"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-muted small text-uppercase">Issues (MTD)</div>
                                        <div class="h4 mb-0" id="kpiStoreIssue">—</div>
                                        <div class="small text-muted">Store issues posted</div>
                                    </div>
                                    <i class="bi bi-arrow-up-right-square fs-4 text-muted"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endcanany
                </div>
            </div>
        </div>

        <div class="text-muted small">Tip: Explore Operations and Finance tabs for charts.</div>
    </div>

    {{-- ========================================================= --}}
    {{-- OPERATIONS --}}
    {{-- ========================================================= --}}
    <div class="tab-pane fade" id="tabOperations" role="tabpanel">
        <div class="row g-3">
            @canany(['store.material_receipt.view','store.issue.view','store.stock.view'])
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
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
                <div class="card border-0 shadow-sm h-100">
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
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 text-uppercase small text-muted">Production: Approved DPR Trend</h6>
                            <select id="prodDays" class="form-select form-select-sm" style="width:auto">
                                <option value="7">7 days</option>
                                <option value="30" selected>30 days</option>
                                <option value="90">90 days</option>
                            </select>
                        </div>
                        <canvas id="prodChart" height="110"></canvas>
                    </div>
                </div>
            </div>
            @endcanany
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- FINANCE --}}
    {{-- ========================================================= --}}
    <div class="tab-pane fade" id="tabFinance" role="tabpanel">
        @canany(['accounting.vouchers.view','accounting.reports.view'])
        <div class="row g-3">
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 text-uppercase small text-muted">Cashflow (Receipts vs Payments)</h6>
                            <select id="cashflowDays" class="form-select form-select-sm" style="width:auto">
                                <option value="7">7 days</option>
                                <option value="30" selected>30 days</option>
                                <option value="90">90 days</option>
                            </select>
                        </div>
                        <canvas id="cashflowChart" height="90"></canvas>
                    </div>
                </div>
            </div>

            @can('accounting.reports.view')
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="mb-2 text-uppercase small text-muted">GST Summary (MTD)</h6>
                        <canvas id="gstChart" height="140"></canvas>
                        <div class="small text-muted mt-2">Input vs Output GST totals for CGST/SGST/IGST.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 text-uppercase small text-muted">Top Expenses (MTD)</h6>
                            <select id="expenseLimit" class="form-select form-select-sm" style="width:auto">
                                <option value="5">Top 5</option>
                                <option value="7" selected>Top 7</option>
                                <option value="10">Top 10</option>
                            </select>
                        </div>
                        <canvas id="expenseChart" height="140"></canvas>
                        <div class="small text-muted mt-2">Expense ledgers ranked by (Debit − Credit) in this month.</div>
                    </div>
                </div>
            </div>
            @endcan
        </div>
        @endcanany
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let cashflowChart, storeChart, prodChart, gstChart, expenseChart, stockMixChart;

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

async function loadKpis() {
    const res = await fetch(`{{ route('dashboard.api.summary') }}`);
    const data = await res.json();

    const acc = data.kpis?.accounting;
    if (acc) {
        document.getElementById('kpiAccNet') && (document.getElementById('kpiAccNet').innerText = fmtMoney(acc.net_mtd));
        document.getElementById('kpiAccCash') && (document.getElementById('kpiAccCash').innerText = fmtMoney(acc.cash_net));
    }

    const store = data.kpis?.store;
    if (store) {
        document.getElementById('kpiStoreGrn') && (document.getElementById('kpiStoreGrn').innerText = fmtInt(store.grn_mtd));
        document.getElementById('kpiStoreIssue') && (document.getElementById('kpiStoreIssue').innerText = fmtInt(store.issues_mtd));
    }
}

async function loadCashflow(days=30) {
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
                { label: 'Receipts', data: (data.series?.receipts || []) },
                { label: 'Payments', data: (data.series?.payments || []) },
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

async function loadStore(days=30) {
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
                { label: 'GRN', data: (data.series?.grn || []) },
                { label: 'Issues', data: (data.series?.issues || []) },
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

async function loadProd(days=30) {
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
                { label: 'Approved DPR', data: (data.series?.approved || []) },
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: true } }
        }
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
                { label: 'Input GST', data: (data.series?.input || []) },
                { label: 'Output GST', data: (data.series?.output || []) },
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

async function loadTopExpenses(limit=7) {
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
                { label: 'Amount', data: (data.series?.amounts || []) },
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
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
                { label: 'Stock lines', data: (data.series?.counts || []) },
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true } }
        }
    });
}

// Wire dropdowns
document.getElementById('cashflowDays')?.addEventListener('change', (e) => loadCashflow(parseInt(e.target.value, 10)));
document.getElementById('storeDays')?.addEventListener('change', (e) => loadStore(parseInt(e.target.value, 10)));
document.getElementById('prodDays')?.addEventListener('change', (e) => loadProd(parseInt(e.target.value, 10)));
document.getElementById('expenseLimit')?.addEventListener('change', (e) => loadTopExpenses(parseInt(e.target.value, 10)));

// Initial load
loadKpis();
loadCashflow(30);
loadStore(30);
loadStockMix();
loadProd(30);
loadGst();
loadTopExpenses(7);
</script>
@endpush
