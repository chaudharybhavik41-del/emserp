@extends('layouts.erp')

@section('title', 'Gate Pass ' . $gatePass->gatepass_number)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Gate Pass {{ $gatePass->gatepass_number }}</h1>
            <div class="small text-muted">
                {{ $gatePass->gatepass_date?->format('d-m-Y') }}
                @if($gatePass->gatepass_time)
                    at {{ \Illuminate\Support\Carbon::parse($gatePass->gatepass_time)->format('H:i') }}
                @endif
            </div>
        </div>
        <div class="d-flex gap-2">
            @if(in_array($gatePass->status, ['out', 'partially_returned']) && $gatePass->lines->where('is_returnable', true)->isNotEmpty())
                @can('store.gatepass.create')
                    <a href="{{ route('gate-passes.return', $gatePass) }}" class="btn btn-sm btn-outline-primary">
                        Register Return
                    </a>
                @endcan
            @endif

            <a href="{{ route('gate-passes.pdf', $gatePass) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                Print
            </a>

            <a href="{{ route('gate-passes.index') }}" class="btn btn-sm btn-secondary">
                Back to List
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->has('general'))
        <div class="alert alert-danger">
            {{ $errors->first('general') }}
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Gate Pass No.</div>
                    <div class="fw-semibold">{{ $gatePass->gatepass_number }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Type</div>
                    <div class="fw-semibold">{{ $gatePass->type_label }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    @php
                        $status = $gatePass->status;
                        $badgeClass = 'bg-secondary';
                        $label = $gatePass->status_label;

                        if ($status === 'out') {
                            $badgeClass = 'bg-warning text-dark';
                        } elseif ($status === 'partially_returned') {
                            $badgeClass = 'bg-info text-dark';
                        } elseif ($status === 'closed') {
                            $badgeClass = 'bg-success';
                        } elseif ($status === 'cancelled') {
                            $badgeClass = 'bg-danger';
                        }
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $label }}</span>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Project</div>
                    @if($gatePass->project)
                        <div class="fw-semibold">{{ $gatePass->project->code }} - {{ $gatePass->project->name }}</div>
                    @else
                        <div class="text-muted">General / Store / Outside Work</div>
                    @endif
                </div>
            </div>

            <div class="row g-3 mt-3">
                <div class="col-md-3">
                    <div class="text-muted small">Contractor</div>
                    @if($gatePass->contractor)
                        <div>{{ $gatePass->contractor->name }}</div>
                    @else
                        <div class="text-muted">-</div>
                    @endif
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">To Party / Vendor</div>
                    @if($gatePass->toParty)
                        <div>{{ $gatePass->toParty->name }}</div>
                    @else
                        <div class="text-muted">-</div>
                    @endif
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Vehicle No.</div>
                    <div>{{ $gatePass->vehicle_number ?? '-' }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Driver Name</div>
                    <div>{{ $gatePass->driver_name ?? '-' }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Transport Mode</div>
                    <div>{{ $gatePass->transport_mode ?? '-' }}</div>
                </div>
            </div>

            <div class="row g-3 mt-3">
                <div class="col-md-6">
                    <div class="text-muted small">Address</div>
                    <div>{{ $gatePass->address ?? '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Reason</div>
                    <div>{{ $gatePass->reason ?? '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Header Returnable?</div>
                    @if($gatePass->is_returnable)
                        <span class="badge bg-info text-dark">Yes</span>
                    @else
                        <span class="badge bg-secondary">No</span>
                    @endif
                </div>
            </div>

            <div class="row g-3 mt-3">
                <div class="col-md-6">
                    <div class="text-muted small">Remarks</div>
                    <div>{{ $gatePass->remarks ?? '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Created By</div>
                    <div>{{ $gatePass->createdBy?->name ?? '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Approved By</div>
                    <div>{{ $gatePass->approvedBy?->name ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header">
            <span class="fw-semibold">Linked Store Docs / Accounts</span>
            <span class="small text-muted ms-2">Store Issue &amp; Store Return posting / vouchers</span>
        </div>
        <div class="card-body">
            @php
                $linkedIssues = $gatePass->lines
                    ->pluck('storeIssueLine.issue')
                    ->filter()
                    ->unique('id')
                    ->values();

                $linkedReturns = $gatePass->storeReturns ?? collect();
            @endphp

            @if($gatePass->type !== 'project_material')
                <div class="text-muted">
                    This gate pass type does not link to Store Issue / Store Return (and therefore has no direct Accounts posting).
                </div>
            @elseif($linkedIssues->isEmpty() && $linkedReturns->isEmpty())
                <div class="text-muted">
                    No linked Store Issues / Store Returns found for this gate pass.
                </div>
            @else
                @if($linkedIssues->isNotEmpty())
                    <div class="fw-semibold mb-2">Store Issues (Outward)</div>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>Issue No</th>
                                <th>Date</th>
                                <th>Project</th>
                                <th>Contractor</th>
                                <th>Accounts</th>
                                <th>Voucher</th>
                                <th style="width: 1%"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($linkedIssues as $issue)
                                @php
                                    $acc = $issue->isAccountsPostingNotRequired()
                                        ? 'not_required'
                                        : ($issue->isPostedToAccounts() ? 'posted' : ($issue->accounting_status ?? 'pending'));
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('store-issues.show', $issue) }}" class="text-decoration-none">
                                            {{ $issue->issue_number ?? ('Issue #'.$issue->id) }}
                                        </a>
                                    </td>
                                    <td>{{ optional($issue->issue_date)->format('d-m-Y') ?? '-' }}</td>
                                    <td>{{ $issue->project?->code ?? '-' }}</td>
                                    <td>{{ $issue->contractor?->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $acc === 'posted' ? 'success' : ($acc === 'not_required' ? 'secondary' : 'warning') }}">
                                            {{ strtoupper($acc) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($issue->voucher)
                                            <a href="{{ route('accounting.vouchers.show', $issue->voucher) }}" class="text-decoration-none">
                                                {{ $issue->voucher->voucher_no }}
                                            </a>
                                        @elseif(!empty($issue->voucher_id))
                                            <a href="{{ route('accounting.vouchers.show', $issue->voucher_id) }}" class="text-decoration-none">
                                                View Voucher
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @can('store.issue.post_to_accounts')
                                            @if(config('accounting.enable_store_issue_posting'))
                                                @if($acc !== 'posted' && $acc !== 'not_required')
                                                    <form method="POST" action="{{ route('store-issues.post-to-accounts', $issue) }}">
                                                        @csrf
                                                        <button class="btn btn-sm btn-success"
                                                                onclick="return confirm('Post this Store Issue to Accounts?');">
                                                            Post
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($linkedReturns->isNotEmpty())
                    <div class="fw-semibold mb-2">Store Returns (Inward)</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>Return No</th>
                                <th>Date</th>
                                <th>Project</th>
                                <th>Contractor</th>
                                <th>Accounts</th>
                                <th>Voucher</th>
                                <th style="width: 1%"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($linkedReturns as $ret)
                                @php
                                    $rAcc = $ret->isAccountsPostingNotRequired()
                                        ? 'not_required'
                                        : ($ret->isPostedToAccounts() ? 'posted' : ($ret->accounting_status ?? 'pending'));
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('store-returns.show', $ret) }}" class="text-decoration-none">
                                            {{ $ret->return_number ?? ('Return #'.$ret->id) }}
                                        </a>
                                    </td>
                                    <td>{{ optional($ret->return_date)->format('d-m-Y') ?? '-' }}</td>
                                    <td>{{ $ret->project?->code ?? '-' }}</td>
                                    <td>{{ $ret->contractor?->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $rAcc === 'posted' ? 'success' : ($rAcc === 'not_required' ? 'secondary' : 'warning') }}">
                                            {{ strtoupper($rAcc) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($ret->voucher)
                                            <a href="{{ route('accounting.vouchers.show', $ret->voucher) }}" class="text-decoration-none">
                                                {{ $ret->voucher->voucher_no }}
                                            </a>
                                        @elseif(!empty($ret->voucher_id))
                                            <a href="{{ route('accounting.vouchers.show', $ret->voucher_id) }}" class="text-decoration-none">
                                                View Voucher
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @can('store.issue.post_to_accounts')
                                            @if(config('accounting.enable_store_issue_posting'))
                                                @if($rAcc !== 'posted' && $rAcc !== 'not_required')
                                                    <form method="POST" action="{{ route('store-returns.post-to-accounts', $ret) }}">
                                                        @csrf
                                                        <button class="btn btn-sm btn-success"
                                                                onclick="return confirm('Post this Store Return to Accounts?');">
                                                            Post
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>


    <div class="card">
        <div class="card-header">
            <span class="fw-semibold">Gate Pass Lines</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Description / Item / Machine</th>
                        <th>UOM</th>
                        <th class="text-end">Qty</th>
                        <th>Returnable</th>
                        <th>Expected Return</th>
                        <th class="text-end">Returned Qty</th>
                        <th>Returned On</th>
                        <th>Remarks</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($gatePass->lines as $line)
                        <tr>
                            <td>{{ $line->line_no }}</td>
                            <td>
                                @if($gatePass->type === 'project_material')
                                    @if($line->item)
                                        <div>{{ $line->item->code }} - {{ $line->item->name }}</div>
                                    @else
                                        <div class="text-muted">Item ID: {{ $line->item_id }}</div>
                                    @endif
                                    @if($line->store_stock_item_id || $line->store_issue_line_id)
                                        <div class="small text-muted">
                                            @if($line->store_stock_item_id)
                                                Stock #{{ $line->store_stock_item_id }}
                                            @endif

                                            @if($line->store_issue_line_id)
                                                @if($line->store_stock_item_id)
                                                    &nbsp;|&nbsp;
                                                @endif
                                                Issue Line #{{ $line->store_issue_line_id }}
                                                @if($line->storeIssueLine && $line->storeIssueLine->issue)
                                                    ({{ $line->storeIssueLine->issue->issue_number }})
                                                @endif
                                            @endif
                                        </div>
                                    @endif
                                @elseif($gatePass->type === 'machinery_maintenance')
                                    @if($line->machine)
                                        <div>{{ $line->machine->code }} - {{ $line->machine->name }}</div>
                                    @else
                                        <div class="text-muted">Machine ID: {{ $line->machine_id }}</div>
                                    @endif
                                @endif

                                @if($line->description)
                                    <div class="small text-muted">{{ $line->description }}</div>
                                @endif
                            </td>
                            <td>
                                @if($line->uom)
                                    {{ $line->uom->name }}
                                @else
                                    @if($gatePass->type === 'machinery_maintenance')
                                        Nos.
                                    @else
                                        -
                                    @endif
                                @endif
                            </td>
                            <td class="text-end">
                                {{ number_format($line->qty, 3) }}
                            </td>
                            <td>
                                @if($line->is_returnable)
                                    <span class="badge bg-info text-dark">Yes</span>
                                @else
                                    <span class="badge bg-secondary">No</span>
                                @endif
                            </td>
                            <td>
                                @if($line->expected_return_date)
                                    {{ \Illuminate\Support\Carbon::parse($line->expected_return_date)->format('d-m-Y') }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end">
                                {{ number_format($line->returned_qty ?? 0, 3) }}
                            </td>
                            <td>
                                @if($line->returned_on)
                                    {{ \Illuminate\Support\Carbon::parse($line->returned_on)->format('d-m-Y') }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $line->remarks ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-3">
                                No lines added to this gate pass.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection



