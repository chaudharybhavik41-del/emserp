@extends('layouts.erp')

@section('title', 'Store Dashboard')

{{-- Page header used by layouts.erp --}}
@section('page_header')
    <div>
        <h1 class="h5 mb-0">Store</h1>
        <small class="text-muted">
            Overview of GRNs, stock, issues, returns and gate passes.
        </small>
    </div>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        {{-- KPI cards --}}
        <div class="col-lg-8">
            <div class="row g-3">

                @can('store.material_receipt.view')
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body py-3">
                                <div class="small text-muted">GRNs Today</div>
                                <div class="h4 mb-0">
                                    {{ $stats['grn_today'] ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body py-3">
                                <div class="small text-muted">GRNs Pending QC</div>
                                <div class="h4 mb-0">
                                    {{ $stats['grn_qc_pending'] ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endcan

                @can('store.requisition.view')
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body py-3">
                                <div class="small text-muted">Open Requisitions</div>
                                <div class="h4 mb-0">
                                    {{ $stats['requisitions_open'] ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endcan

                @can('store.issue.view')
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body py-3">
                                <div class="small text-muted">Issues Today</div>
                                <div class="h4 mb-0">
                                    {{ $stats['issues_today'] ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endcan

                @can('store.return.view')
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body py-3">
                                <div class="small text-muted">Returns Today</div>
                                <div class="h4 mb-0">
                                    {{ $stats['returns_today'] ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endcan

                @can('store.gatepass.view')
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body py-3">
                                <div class="small text-muted">Open Gate Passes</div>
                                <div class="h4 mb-0">
                                    {{ $stats['gatepasses_open'] ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endcan

                @can('store.stock.view')
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body py-3">
                                <div class="small text-muted">Stock Items</div>
                                <div class="h4 mb-0">
                                    {{ $stats['stock_items'] ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body py-3">
                                <div class="small text-muted">Available Stock (kg)</div>
                                <div class="h4 mb-0">
                                    @if(isset($stats['stock_weight_kg']))
                                        {{ number_format($stats['stock_weight_kg'], 2) }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body py-3">
                                <div class="small text-muted">Remnant Pieces</div>
                                <div class="h4 mb-0">
                                    {{ $stats['remnants_count'] ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body py-3">
                                <div class="small text-muted">Stock Adjustments (This Month)</div>
                                <div class="h4 mb-0">
                                    {{ $stats['adjustments_this_month'] ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endcan
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-title mb-2 text-uppercase small text-muted">Quick Actions</h6>

                    <ul class="list-unstyled small mb-0">

                        @can('store.material_receipt.create')
                            <li class="mb-1 d-flex justify-content-between align-items-center">
                                <span>Create GRN / Material Receipt</span>
                                <a href="{{ route('material-receipts.create') }}"
                                   class="link-primary text-decoration-none">
                                    Go
                                </a>
                            </li>
                        @endcan

                        @can('store.stock_item.view')
                            <li class="mb-1 d-flex justify-content-between align-items-center">
                                <span>Store Stock &amp; Remnants</span>
                                <a href="{{ route('store-stock-items.index') }}"
                                   class="link-primary text-decoration-none">
                                    View
                                </a>
                            </li>
                        @endcan

                        @can('store.requisition.create')
                            <li class="mb-1 d-flex justify-content-between align-items-center">
                                <span>Create Store Requisition</span>
                                <a href="{{ route('store-requisitions.create') }}"
                                   class="link-primary text-decoration-none">
                                    Go
                                </a>
                            </li>
                        @endcan

                        @can('store.issue.create')
                            <li class="mb-1 d-flex justify-content-between align-items-center">
                                <span>Create Store Issue</span>
                                <a href="{{ route('store-issues.create') }}"
                                   class="link-primary text-decoration-none">
                                    Go
                                </a>
                            </li>
                        @endcan

                        @can('store.return.create')
                            <li class="mb-1 d-flex justify-content-between align-items-center">
                                <span>Create Store Return</span>
                                <a href="{{ route('store-returns.create') }}"
                                   class="link-primary text-decoration-none">
                                    Go
                                </a>
                            </li>
                        @endcan

                        @can('store.stock.adjustment.view')
                            <li class="mb-1 d-flex justify-content-between align-items-center">
                                <span>Stock Adjustments</span>
                                <a href="{{ route('store-stock-adjustments.index') ?? '#' }}"
                                   class="link-primary text-decoration-none">
                                    View
                                </a>
                            </li>
                        @endcan

                        @can('store.gatepass.view')
                            <li class="mb-1 d-flex justify-content-between align-items-center">
                                <span>Gate Passes</span>
                                <a href="{{ route('gate-passes.index') ?? '#' }}"
                                   class="link-primary text-decoration-none">
                                    View
                                </a>
                            </li>
                        @endcan

                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Activity lists --}}
    <div class="row g-3">
        <div class="col-md-6">
            {{-- Recent GRNs --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="card-title mb-2 text-uppercase small text-muted">Recent GRNs</h6>

                    @if($recentGrns->isEmpty())
                        <p class="small text-muted mb-0">No GRNs recorded yet.</p>
                    @else
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
                                        <td>
                                            <a href="{{ route('material-receipts.show', $grn) }}">
                                                {{ $grn->receipt_number ?? $grn->id }}
                                            </a>
                                        </td>
                                        <td>{{ $grn->project?->code ?? '—' }}</td>
                                        <td>{{ $grn->supplier?->name ?? '—' }}</td>
                                        <td class="text-capitalize">{{ $grn->status ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Open Requisitions --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title mb-2 text-uppercase small text-muted">Open Requisitions</h6>

                    @if($recentRequisitions->isEmpty())
                        <p class="small text-muted mb-0">No open requisitions.</p>
                    @else
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
                                        <td>
                                            <a href="{{ route('store-requisitions.show', $req) }}">
                                                {{ $req->requisition_number ?? $req->id }}
                                            </a>
                                        </td>
                                        <td>{{ $req->project?->code ?? '—' }}</td>
                                        <td class="text-capitalize">{{ $req->status ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            {{-- Recent Issues --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="card-title mb-2 text-uppercase small text-muted">Recent Store Issues</h6>

                    @if($recentIssues->isEmpty())
                        <p class="small text-muted mb-0">No issues recorded yet.</p>
                    @else
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
                                        <td>
                                            <a href="{{ route('store-issues.show', $issue) }}">
                                                {{ $issue->issue_number ?? $issue->id }}
                                            </a>
                                        </td>
                                        <td>{{ $issue->project?->code ?? '—' }}</td>
                                        <td>{{ $issue->contractor?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Recent Returns / Open Gate Passes --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title mb-2 text-uppercase small text-muted">Recent Returns &amp; Open Gate Passes</h6>

                    <div class="row g-2">
                        <div class="col-12">
                            <h6 class="small text-muted mb-1">Recent Returns</h6>
                            @if($recentReturns->isEmpty())
                                <p class="small text-muted mb-2">No returns recorded.</p>
                            @else
                                <div class="table-responsive mb-2">
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
                                                <td>
                                                    <a href="{{ route('store-returns.show', $return) }}">
                                                        {{ $return->return_number ?? $return->id }}
                                                    </a>
                                                </td>
                                                <td>{{ $return->project?->code ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        <div class="col-12">
                            <h6 class="small text-muted mb-1">Open Gate Passes</h6>
                            @if($openGatePasses->isEmpty())
                                <p class="small text-muted mb-0">No open gate passes.</p>
                            @else
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
                                                <td>
                                                    <a href="{{ route('gate-passes.show', $gp) }}">
                                                        {{ $gp->gatepass_number }}
                                                    </a>
                                                </td>
                                                <td>{{ $gp->project?->code ?? '—' }}</td>
                                                <td class="text-capitalize">{{ $gp->status }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
