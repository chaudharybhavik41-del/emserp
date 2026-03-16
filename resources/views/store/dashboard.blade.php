@extends('layouts.erp')

@section('title', 'Store Dashboard')

@section('page_header')
    <div>
        <h1 class="h5 mb-0">Store</h1>
        <small class="text-muted">Overview of receipts, stock movement, requisitions, returns and gate passes.</small>
    </div>
@endsection

@push('styles')
<style>
    .store-hero {
        position: relative;
        overflow: hidden;
        padding: 1.4rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 1.35rem;
        background:
            radial-gradient(circle at top right, rgba(20, 184, 166, 0.12), transparent 28%),
            linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(240, 249, 255, 0.92));
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.06);
    }

    .store-hero::after {
        content: "";
        position: absolute;
        inset: auto -4rem -5rem auto;
        width: 12rem;
        height: 12rem;
        border-radius: 999px;
        background: rgba(37, 99, 235, 0.08);
        filter: blur(4px);
    }

    .store-eyebrow {
        margin-bottom: 0.45rem;
        color: #0f766e;
        font-size: 0.74rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .store-hero-title {
        margin-bottom: 0.45rem;
        font-size: clamp(1.45rem, 1.1rem + 1vw, 2.2rem);
        font-weight: 700;
        letter-spacing: -0.03em;
        color: #0f172a;
    }

    .store-hero-copy {
        max-width: 44rem;
        margin-bottom: 1rem;
        color: #475569;
    }

    .store-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
    }

    .store-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.5rem 0.8rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.82);
        border: 1px solid rgba(148, 163, 184, 0.18);
        color: #0f172a;
        font-size: 0.84rem;
    }

    .store-chip strong {
        font-size: 0.96rem;
    }

    .store-quick-panel {
        position: relative;
        z-index: 1;
        height: 100%;
        padding: 1rem;
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid rgba(148, 163, 184, 0.2);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .store-kpi {
        position: relative;
        overflow: hidden;
    }

    .store-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.85rem;
    }

    .store-kpi .erp-kpi-body {
        position: relative;
        display: block;
        padding: 1.1rem 1.15rem 1.05rem 1.4rem;
    }

    .store-kpi .erp-kpi-body > div {
        width: 100%;
        text-align: center;
    }

    .store-kpi .erp-kpi-icon {
        position: absolute;
        top: 0.95rem;
        right: 0.95rem;
        width: 2.2rem;
        height: 2.2rem;
    }

    .store-kpi::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 5px;
        background: var(--store-accent, #2563eb);
    }

    .store-kpi--blue { --store-accent: #2563eb; }
    .store-kpi--teal { --store-accent: #0f766e; }
    .store-kpi--amber { --store-accent: #d97706; }
    .store-kpi--slate { --store-accent: #475569; }

    .store-panel {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.96));
    }

    .store-kpi-main {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 0.15rem;
    }

    .store-kpi .erp-kpi-value {
        margin: 0;
    }

    .store-kpi .erp-kpi-meta-row {
        margin-top: 0;
        justify-content: center;
    }

    .store-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.95rem;
    }

    .store-section-kicker {
        color: #0f766e;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .store-section-title {
        margin: 0.1rem 0 0;
        font-size: 1rem;
        font-weight: 650;
        color: #0f172a;
    }

    [data-bs-theme="dark"] .store-hero {
        background:
            radial-gradient(circle at top right, rgba(45, 212, 191, 0.18), transparent 28%),
            linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(15, 118, 110, 0.2));
        border-color: rgba(255, 255, 255, 0.08);
    }

    [data-bs-theme="dark"] .store-eyebrow,
    [data-bs-theme="dark"] .store-section-kicker {
        color: #5eead4;
    }

    [data-bs-theme="dark"] .store-hero-title,
    [data-bs-theme="dark"] .store-section-title,
    [data-bs-theme="dark"] .store-chip {
        color: #f8fafc;
    }

    [data-bs-theme="dark"] .store-hero-copy {
        color: #cbd5e1;
    }

    [data-bs-theme="dark"] .store-chip,
    [data-bs-theme="dark"] .store-quick-panel {
        background: rgba(15, 23, 42, 0.55);
        border-color: rgba(255, 255, 255, 0.08);
    }

    [data-bs-theme="dark"] .store-panel {
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.94), rgba(22, 26, 31, 0.96));
    }

    @media (max-width: 1199.98px) {
        .store-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .store-kpi-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
    <div class="store-hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <div class="store-eyebrow">Store Control Deck</div>
                <div class="store-hero-title">Movement, readiness and exceptions in one view.</div>
                <div class="store-hero-copy">
                    Use this surface to track today’s inbound and outbound flow, spot stock exceptions early, and jump straight into the next store action.
                </div>
                <div class="store-chip-row">
                    <span class="store-chip"><i class="bi bi-box-arrow-in-down"></i> GRNs <strong>{{ $stats['grn_today'] ?? '—' }}</strong></span>
                    <span class="store-chip"><i class="bi bi-arrow-up-right"></i> Issues <strong>{{ $stats['issues_today'] ?? '—' }}</strong></span>
                    <span class="store-chip"><i class="bi bi-arrow-return-left"></i> Returns <strong>{{ $stats['returns_today'] ?? '—' }}</strong></span>
                    <span class="store-chip"><i class="bi bi-scissors"></i> Remnants <strong>{{ $stats['remnants_count'] ?? '—' }}</strong></span>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="store-quick-panel">
                    <div class="store-section-kicker">Readiness</div>
                    <h3 class="h6 mb-2">Where the team should focus next</h3>
                    <div class="small text-muted mb-3">QC backlog, open requisitions and gate-pass closures usually drive the next set of store actions.</div>
                    <div class="d-grid gap-2">
                        @can('store.material_receipt.create')
                            <a href="{{ route('material-receipts.create') }}" class="btn btn-primary btn-sm">Create GRN</a>
                        @endcan
                        @can('store.issue.create')
                            <a href="{{ route('store-issues.create') }}" class="btn btn-outline-secondary btn-sm">Issue Material</a>
                        @endcan
                        @can('store.requisition.view')
                            <a href="{{ route('store-requisitions.index') }}" class="btn btn-outline-secondary btn-sm">Review Requisitions</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="store-kpi-grid">
                @can('store.material_receipt.view')
                    <div class="erp-kpi store-kpi store-kpi--blue">
                        <div class="erp-kpi-body">
                            <div>
                                <div class="erp-kpi-label">GRNs Today</div>
                                <div class="store-kpi-main">
                                    <div class="erp-kpi-value">{{ $stats['grn_today'] ?? '—' }}</div>
                                    <div class="erp-kpi-meta-row">
                                        <span class="erp-kpi-pill">Created today</span>
                                    </div>
                                </div>
                            </div>
                            <span class="erp-kpi-icon"><i class="bi bi-box-seam"></i></span>
                        </div>
                    </div>

                    <div class="erp-kpi store-kpi store-kpi--teal">
                        <div class="erp-kpi-body">
                            <div>
                                <div class="erp-kpi-label">Pending QC</div>
                                <div class="store-kpi-main">
                                    <div class="erp-kpi-value">{{ $stats['grn_qc_pending'] ?? '—' }}</div>
                                    <div class="erp-kpi-meta-row">
                                        <span class="erp-kpi-pill">Waiting for QC</span>
                                    </div>
                                </div>
                            </div>
                            <span class="erp-kpi-icon"><i class="bi bi-clipboard-check"></i></span>
                        </div>
                    </div>
                @endcan

                @can('store.requisition.view')
                    <div class="erp-kpi store-kpi store-kpi--amber">
                        <div class="erp-kpi-body">
                            <div>
                                <div class="erp-kpi-label">Open Requisitions</div>
                                <div class="store-kpi-main">
                                    <div class="erp-kpi-value">{{ $stats['requisitions_open'] ?? '—' }}</div>
                                    <div class="erp-kpi-meta-row">
                                        <span class="erp-kpi-pill">Still open</span>
                                    </div>
                                </div>
                            </div>
                            <span class="erp-kpi-icon"><i class="bi bi-journal-text"></i></span>
                        </div>
                    </div>
                @endcan

                @can('store.issue.view')
                    <div class="erp-kpi store-kpi store-kpi--slate">
                        <div class="erp-kpi-body">
                            <div>
                                <div class="erp-kpi-label">Issues Today</div>
                                <div class="store-kpi-main">
                                    <div class="erp-kpi-value">{{ $stats['issues_today'] ?? '—' }}</div>
                                    <div class="erp-kpi-meta-row">
                                        <span class="erp-kpi-pill">Posted today</span>
                                    </div>
                                </div>
                            </div>
                            <span class="erp-kpi-icon"><i class="bi bi-arrow-up-right-circle"></i></span>
                        </div>
                    </div>
                @endcan

                @can('store.return.view')
                    <div class="erp-kpi store-kpi store-kpi--teal">
                        <div class="erp-kpi-body">
                            <div>
                                <div class="erp-kpi-label">Returns Today</div>
                                <div class="store-kpi-main">
                                    <div class="erp-kpi-value">{{ $stats['returns_today'] ?? '—' }}</div>
                                    <div class="erp-kpi-meta-row">
                                        <span class="erp-kpi-pill">Booked today</span>
                                    </div>
                                </div>
                            </div>
                            <span class="erp-kpi-icon"><i class="bi bi-arrow-return-left"></i></span>
                        </div>
                    </div>
                @endcan

                @can('store.gatepass.view')
                    <div class="erp-kpi store-kpi store-kpi--blue">
                        <div class="erp-kpi-body">
                            <div>
                                <div class="erp-kpi-label">Open Gate Passes</div>
                                <div class="store-kpi-main">
                                    <div class="erp-kpi-value">{{ $stats['gatepasses_open'] ?? '—' }}</div>
                                    <div class="erp-kpi-meta-row">
                                        <span class="erp-kpi-pill">Need closure</span>
                                    </div>
                                </div>
                            </div>
                            <span class="erp-kpi-icon"><i class="bi bi-truck"></i></span>
                        </div>
                    </div>
                @endcan

                @can('store.stock.view')
                    <div class="erp-kpi store-kpi store-kpi--slate">
                        <div class="erp-kpi-body">
                            <div>
                                <div class="erp-kpi-label">Stock Items</div>
                                <div class="store-kpi-main">
                                    <div class="erp-kpi-value">{{ $stats['stock_items'] ?? '—' }}</div>
                                    <div class="erp-kpi-meta-row">
                                        <span class="erp-kpi-pill">Inventory lines</span>
                                    </div>
                                </div>
                            </div>
                            <span class="erp-kpi-icon"><i class="bi bi-grid-3x3-gap"></i></span>
                        </div>
                    </div>

                    <div class="erp-kpi store-kpi store-kpi--amber">
                        <div class="erp-kpi-body">
                            <div>
                                <div class="erp-kpi-label">Available Stock</div>
                                <div class="store-kpi-main">
                                    <div class="erp-kpi-value">
                                        @if(isset($stats['stock_weight_kg']))
                                            {{ number_format($stats['stock_weight_kg'], 2) }}
                                        @else
                                            —
                                        @endif
                                        <span class="erp-kpi-unit">kg</span>
                                    </div>
                                    <div class="erp-kpi-meta-row">
                                        <span class="erp-kpi-pill">Available weight</span>
                                    </div>
                                </div>
                            </div>
                            <span class="erp-kpi-icon"><i class="bi bi-boxes"></i></span>
                        </div>
                    </div>

                    <div class="erp-kpi store-kpi store-kpi--teal">
                        <div class="erp-kpi-body">
                            <div>
                                <div class="erp-kpi-label">Remnant Pieces</div>
                                <div class="store-kpi-main">
                                    <div class="erp-kpi-value">{{ $stats['remnants_count'] ?? '—' }}</div>
                                    <div class="erp-kpi-meta-row">
                                        <span class="erp-kpi-pill">Reusable stock</span>
                                    </div>
                                </div>
                            </div>
                            <span class="erp-kpi-icon"><i class="bi bi-scissors"></i></span>
                        </div>
                    </div>

                    <div class="erp-kpi store-kpi store-kpi--blue">
                        <div class="erp-kpi-body">
                            <div>
                                <div class="erp-kpi-label">Stock Adjustments</div>
                                <div class="store-kpi-main">
                                    <div class="erp-kpi-value">{{ $stats['adjustments_this_month'] ?? '—' }}</div>
                                    <div class="erp-kpi-meta-row">
                                        <span class="erp-kpi-pill">This month</span>
                                    </div>
                                </div>
                            </div>
                            <span class="erp-kpi-icon"><i class="bi bi-sliders2"></i></span>
                        </div>
                    </div>
                @endcan
            </div>
        </div>

        <div class="col-lg-4">
            <div class="erp-surface store-panel h-100 p-3 p-lg-4">
                <div class="store-section-head">
                    <div>
                        <div class="store-section-kicker">Actions</div>
                        <div class="store-section-title">Quick Actions</div>
                    </div>
                </div>
                <ul class="erp-action-list small">
                    @can('store.material_receipt.create')
                        <li><a href="{{ route('material-receipts.create') }}" class="erp-action-link"><span>Create GRN / Material Receipt</span><span class="text-primary">Go</span></a></li>
                    @endcan
                    @can('store.stock_item.view')
                        <li><a href="{{ route('store-stock-items.index') }}" class="erp-action-link"><span>Store Stock &amp; Remnants</span><span class="text-primary">View</span></a></li>
                    @endcan
                    @can('store.requisition.create')
                        <li><a href="{{ route('store-requisitions.create') }}" class="erp-action-link"><span>Create Store Requisition</span><span class="text-primary">Go</span></a></li>
                    @endcan
                    @can('store.issue.create')
                        <li><a href="{{ route('store-issues.create') }}" class="erp-action-link"><span>Create Store Issue</span><span class="text-primary">Go</span></a></li>
                    @endcan
                    @can('store.return.create')
                        <li><a href="{{ route('store-returns.create') }}" class="erp-action-link"><span>Create Store Return</span><span class="text-primary">Go</span></a></li>
                    @endcan
                    @can('store.stock.adjustment.view')
                        <li><a href="{{ route('store-stock-adjustments.index') ?? '#' }}" class="erp-action-link"><span>Stock Adjustments</span><span class="text-primary">View</span></a></li>
                    @endcan
                    @can('store.gatepass.view')
                        <li><a href="{{ route('gate-passes.index') ?? '#' }}" class="erp-action-link"><span>Gate Passes</span><span class="text-primary">View</span></a></li>
                    @endcan
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6 erp-stack">
            <div class="erp-surface store-panel p-3 p-lg-4">
                <div class="store-section-head">
                    <div>
                        <div class="store-section-kicker">Inbound</div>
                        <div class="store-section-title">Recent GRNs</div>
                    </div>
                </div>
                @if($recentGrns->isEmpty())
                    <div class="erp-empty-state py-4"><div class="small mb-0">No GRNs recorded yet.</div></div>
                @else
                    <div class="erp-table-card">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Receipt #</th>
                                        <th>Project</th>
                                        <th>Supplier</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentGrns as $grn)
                                        <tr>
                                            <td>{{ optional($grn->receipt_date)->format('d-m-Y') }}</td>
                                            <td><a href="{{ route('material-receipts.show', $grn) }}">{{ $grn->receipt_number ?? $grn->id }}</a></td>
                                            <td>{{ $grn->project?->code ?? '—' }}</td>
                                            <td>{{ $grn->supplier?->name ?? '—' }}</td>
                                            <td class="text-capitalize">{{ $grn->status ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <div class="erp-surface store-panel p-3 p-lg-4">
                <div class="store-section-head">
                    <div>
                        <div class="store-section-kicker">Demand</div>
                        <div class="store-section-title">Open Requisitions</div>
                    </div>
                </div>
                @if($recentRequisitions->isEmpty())
                    <div class="erp-empty-state py-4"><div class="small mb-0">No open requisitions.</div></div>
                @else
                    <div class="erp-table-card">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Req #</th>
                                        <th>Project</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentRequisitions as $req)
                                        <tr>
                                            <td>{{ optional($req->requisition_date)->format('d-m-Y') }}</td>
                                            <td><a href="{{ route('store-requisitions.show', $req) }}">{{ $req->requisition_number ?? $req->id }}</a></td>
                                            <td>{{ $req->project?->code ?? '—' }}</td>
                                            <td class="text-capitalize">{{ $req->status ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-6 erp-stack">
            <div class="erp-surface store-panel p-3 p-lg-4">
                <div class="store-section-head">
                    <div>
                        <div class="store-section-kicker">Outbound</div>
                        <div class="store-section-title">Recent Store Issues</div>
                    </div>
                </div>
                @if($recentIssues->isEmpty())
                    <div class="erp-empty-state py-4"><div class="small mb-0">No issues recorded yet.</div></div>
                @else
                    <div class="erp-table-card">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Issue #</th>
                                        <th>Project</th>
                                        <th>Contractor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentIssues as $issue)
                                        <tr>
                                            <td>{{ optional($issue->issue_date)->format('d-m-Y') }}</td>
                                            <td><a href="{{ route('store-issues.show', $issue) }}">{{ $issue->issue_number ?? $issue->id }}</a></td>
                                            <td>{{ $issue->project?->code ?? '—' }}</td>
                                            <td>{{ $issue->contractor?->name ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <div class="erp-surface store-panel p-3 p-lg-4">
                <div class="store-section-head">
                    <div>
                        <div class="store-section-kicker">Exceptions</div>
                        <div class="store-section-title">Returns &amp; Gate Passes</div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <h6 class="small text-muted mb-2">Recent Returns</h6>
                        @if($recentReturns->isEmpty())
                            <div class="erp-empty-state py-4"><div class="small">No returns recorded.</div></div>
                        @else
                            <div class="erp-table-card">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Return #</th>
                                                <th>Project</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($recentReturns as $return)
                                                <tr>
                                                    <td>{{ optional($return->return_date)->format('d-m-Y') }}</td>
                                                    <td><a href="{{ route('store-returns.show', $return) }}">{{ $return->return_number ?? $return->id }}</a></td>
                                                    <td>{{ $return->project?->code ?? '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12">
                        <h6 class="small text-muted mb-2">Open Gate Passes</h6>
                        @if($openGatePasses->isEmpty())
                            <div class="erp-empty-state py-4"><div class="small">No open gate passes.</div></div>
                        @else
                            <div class="erp-table-card">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>#</th>
                                                <th>Project</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($openGatePasses as $gp)
                                                <tr>
                                                    <td>{{ optional($gp->gatepass_date)->format('d-m-Y') }}</td>
                                                    <td><a href="{{ route('gate-passes.show', $gp) }}">{{ $gp->gatepass_number }}</a></td>
                                                    <td>{{ $gp->project?->code ?? '—' }}</td>
                                                    <td class="text-capitalize">{{ $gp->status }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
