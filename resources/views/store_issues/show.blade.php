@extends('layouts.erp')

@section('title', 'Store Issue ' . ($issue->issue_number ?? ''))

@section('content')
    <div class="container-fluid">

        {{-- Header: title + statuses + actions --}}
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h1 class="h4 mb-1">
                    Store Issue
                    @if($issue->issue_number)
                        - {{ $issue->issue_number }}
                    @endif
                </h1>

                @php
                    $storeStatus = $issue->status ?? 'draft';

                    // Accounting status is driven by voucher_id + accounting_status
                    $accStatus = $issue->accounting_status ?? 'pending';
                    if (!empty($issue->voucher_id)) {
                        $accStatus = 'posted';
                    }

                    $storeBadge = match ($storeStatus) {
                        'posted' => 'bg-success',
                        'cancelled' => 'bg-danger',
                        default => 'bg-secondary',
                    };

                    $accBadge = match ($accStatus) {
                        'posted' => 'bg-success',
                        'not_required' => 'bg-info',
                        default => 'bg-secondary', // pending
                    };

                    $accLabel = match ($accStatus) {
                        'posted' => 'Accounts: Posted',
                        'not_required' => 'Accounts: N/A',
                        default => 'Accounts: Pending',
                    };

                    $voucherNo = optional($issue->voucher)->voucher_no;
                @endphp

                <div class="small mt-1">
                    <span class="badge {{ $storeBadge }} me-2">Store: {{ strtoupper($storeStatus) }}</span>
                    <span class="badge {{ $accBadge }} me-2">
                        {{ $accLabel }}
                        @if($accStatus === 'posted')
                            @if($voucherNo)
                                – {{ $voucherNo }}
                            @elseif(!empty($issue->voucher_id))
                                – #{{ $issue->voucher_id }}
                            @endif
                        @endif
                    </span>
                </div>
            </div>

            <div class="text-end">
                <a href="{{ route('store-issues.index') }}" class="btn btn-sm btn-outline-secondary mb-1">
                    Back to list
                </a>

                @can('store.issue.post_to_accounts')
                    @if(config('accounting.enable_store_issue_posting'))
                        @if(!$issue->isPostedToAccounts() && !$issue->isAccountsPostingNotRequired())
                            <form action="{{ route('store-issues.post-to-accounts', $issue) }}"
                                  method="POST"
                                  class="d-inline">
                                @csrf
                                <button type="submit"
                                        class="btn btn-sm btn-primary mb-1"
                                        onclick="return confirm('Post this Store Issue to Accounts?');">
                                    Post to Accounts
                                </button>
                            </form>
                        @endif
                    @endif
                @endcan
            </div>
        </div>

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Issue header details --}}
        <div class="card mb-3">
            <div class="card-header py-2">
                <span class="fw-semibold small">Header</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <strong>Issue Date:</strong><br>
                        {{ optional($issue->issue_date)->format('d-m-Y') ?? '-' }}
                    </div>
                    <div class="col-md-3">
                        <strong>Project:</strong><br>
                        @if($issue->project)
                            {{ $issue->project->code }} - {{ $issue->project->name }}
                        @else
                            -
                        @endif
                    </div>
                    <div class="col-md-3">
                        <strong>Store Requisition:</strong><br>
                        @if($issue->requisition)
                            <a href="{{ route('store-requisitions.show', $issue->requisition) }}">
                                {{ $issue->requisition->requisition_number }}
                            </a>
                        @else
                            -
                        @endif
                    </div>
                    <div class="col-md-3">
                        <strong>Issued To:</strong><br>
                        @if($issue->contractor)
                            {{ $issue->contractor->name }}
                            @if($issue->contractor_person_name)
                                ({{ $issue->contractor_person_name }})
                            @endif
                        @elseif($issue->contractor_person_name)
                            {{ $issue->contractor_person_name }}
                        @else
                            -
                        @endif
                    </div>

                    <div class="col-12">
                        <strong>Remarks:</strong><br>
                        <span class="text-muted">{{ $issue->remarks ?: '-' }}</span>
                    </div>

                    @if($accStatus === 'posted')
                        <div class="col-12">
                            <strong>Accounting Voucher:</strong><br>
                            <span class="text-muted">
                                {{ $voucherNo ?: ('#' . $issue->voucher_id) }}
                                <span class="mx-2">•</span>
                                <a href="{{ route('accounting.vouchers.index', ['type' => 'store_issue']) }}">
                                    Open voucher list
                                </a>
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Issue lines --}}
        <div class="card mb-3">
            <div class="card-header py-2">
                <span class="fw-semibold small">Issue Lines</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                        <tr class="small">
                            <th style="width: 4%">#</th>
                            <th style="width: 22%">Item</th>
                            <th style="width: 10%">UOM</th>
                            <th style="width: 12%" class="text-end">Issued Qty</th>
                            <th style="width: 12%" class="text-end">Returned Qty</th>
                            <th style="width: 12%" class="text-end">Balance</th>
                            <th style="width: 10%">Material</th>
                            <th style="width: 12%">Brand</th>
                            <th style="width: 20%">Stock Ref</th>
                            <th>Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($issue->lines as $index => $line)
                            @php
                                $stock = $line->stockItem;
                                $isClient = (bool) ($stock?->is_client_material ?? false);
                                $qty = (float) ($line->issued_weight_kg ?? 0);
                                if ($qty <= 0) {
                                    $qty = (float) ($line->issued_qty_pcs ?? 0);
                                }

                                $returnedQty = (float) ($returnedByLine[$line->id] ?? 0);
                                $balanceQty = max(0.0, $qty - $returnedQty);
                            @endphp
                            <tr class="small">
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    @if($line->item)
                                        <div class="fw-semibold">{{ $line->item->name }}</div>
                                        <div class="text-muted">{{ $line->item->code }}</div>
                                    @else
                                        <span class="text-muted">Item #{{ $line->item_id }}</span>
                                    @endif
                                </td>
                                <td>{{ $line->uom?->name ?? '-' }}</td>
                                <td class="text-end">{{ number_format($qty, 3) }}</td>
                        <td class="text-end">{{ number_format($returnedQty, 3) }}</td>
                        <td class="text-end">{{ number_format($balanceQty, 3) }}</td>
                                <td>
                                    @if($isClient)
                                        <span class="badge bg-info">Client</span>
                                    @else
                                        <span class="badge bg-secondary">Own</span>
                                    @endif
                                </td>
                                <td>{{ $stock?->brand ?: '-' }}</td>
                                <td>{{ $stock?->source_reference ?? '-' }}</td>
                                <td>{{ $line->remarks ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted small py-3">
                                    No lines found for this Store Issue.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Posting History (Accounting audit) --}}
        <div class="card mt-3">
            <div class="card-header py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold small">Posting History</span>
                    <span class="small text-muted">Latest posting events for this store issue</span>
                </div>
            </div>
            <div class="card-body p-0">
                @php
                    /** @var \Illuminate\Support\Collection|\App\Models\ActivityLog[] $postingLogs */
                    $postingLogs = \App\Models\ActivityLog::forSubject($issue)
                        ->whereIn('action', ['posted_to_accounts', 'accounts_posting_not_required'])
                        ->orderByDesc('created_at')
                        ->limit(10)
                        ->get();
                @endphp

                @if($postingLogs->isEmpty())
                    <p class="text-muted small mb-0 p-3">
                        No accounting posting activity recorded yet.
                    </p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                            <tr class="small">
                                <th style="width: 20%">When</th>
                                <th style="width: 20%">User</th>
                                <th style="width: 20%">Action</th>
                                <th style="width: 20%">Voucher</th>
                                <th>Note</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($postingLogs as $log)
                                @php
                                    $meta = $log->metadata ?? [];
                                @endphp
                                <tr class="small">
                                    <td>{{ optional($log->created_at)->format('d-m-Y H:i') }}</td>
                                    <td>{{ $log->user_name ?? $log->user?->name ?? 'System' }}</td>
                                    <td>
                                        @if($log->action === 'posted_to_accounts')
                                            Posted
                                        @elseif($log->action === 'accounts_posting_not_required')
                                            Not Required
                                        @else
                                            {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($meta['voucher_no']))
                                            {{ $meta['voucher_no'] }}
                                        @elseif(!empty($meta['voucher_id']))
                                            #{{ $meta['voucher_id'] }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        {{ $log->description ?? '-' }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection



