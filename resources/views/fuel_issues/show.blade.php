@extends('layouts.erp')

@section('title', 'Fuel Issue ' . ($issue->issue_number ?? ('#' . $issue->id)))

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h1 class="h4 mb-1">Fuel Issue {{ $issue->issue_number ?: ('#' . $issue->id) }}</h1>
                @php
                    $accStatus = $issue->accounting_status ?? 'pending';
                    if (!empty($issue->voucher_id)) {
                        $accStatus = 'posted';
                    }
                @endphp
                <div class="small">
                    @if($accStatus === 'posted')
                        <span class="badge bg-success">Accounts: Posted</span>
                    @elseif($accStatus === 'not_required')
                        <span class="badge bg-info">Accounts: N/A</span>
                    @else
                        <span class="badge bg-secondary">Accounts: Pending</span>
                    @endif
                </div>
            </div>
            <div class="text-end">
                <a href="{{ route('fuel-issues.index') }}" class="btn btn-sm btn-outline-secondary mb-1">Back</a>
                @can('store.issue.post_to_accounts')
                    @if(!$issue->isPostedToAccounts() && !$issue->isAccountsPostingNotRequired())
                        <form action="{{ route('fuel-issues.post-to-accounts', $issue) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary mb-1"
                                    onclick="return confirm('Post this fuel issue to Accounts?')">
                                Post to Accounts
                            </button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

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

        <div class="card">
            <div class="card-header py-2"><span class="fw-semibold small">Fuel Issue Details</span></div>
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-md-3"><strong>Date:</strong><br>{{ optional($issue->issue_date)->format('d-m-Y') }}</div>
                    <div class="col-md-4">
                        <strong>Machine:</strong><br>
                        @if($issue->machine)
                            {{ $issue->machine->code ? ($issue->machine->code . ' - ') : '' }}{{ $issue->machine->name }}
                        @else
                            -
                        @endif
                    </div>
                    <div class="col-md-5">
                        <strong>Project:</strong><br>
                        @if($issue->project)
                            {{ $issue->project->code }} - {{ $issue->project->name }}
                        @else
                            -
                        @endif
                    </div>
                    <div class="col-md-4">
                        <strong>Fuel Item:</strong><br>
                        {{ $issue->item?->code ? ($issue->item->code . ' - ') : '' }}{{ $issue->item?->name ?: '-' }}
                    </div>
                    <div class="col-md-2"><strong>Qty:</strong><br>{{ number_format((float) $issue->qty, 3) }} {{ $issue->uom?->name ?: '' }}</div>
                    <div class="col-md-2"><strong>Rate:</strong><br>{{ number_format((float) $issue->unit_rate, 2) }}</div>
                    <div class="col-md-2"><strong>Amount:</strong><br>{{ number_format((float) $issue->amount, 2) }}</div>
                    <div class="col-md-2"><strong>Opening Meter:</strong><br>{{ $issue->opening_meter_reading !== null ? number_format((float) $issue->opening_meter_reading, 3) : '-' }}</div>
                    <div class="col-md-2"><strong>Closing Meter:</strong><br>{{ $issue->closing_meter_reading !== null ? number_format((float) $issue->closing_meter_reading, 3) : '-' }}</div>
                    <div class="col-md-12"><strong>Remarks:</strong><br>{{ $issue->remarks ?: '-' }}</div>
                    @if($accStatus === 'posted')
                        <div class="col-md-12">
                            <strong>Accounting Voucher:</strong><br>
                            {{ $issue->voucher?->voucher_no ?: ('#' . $issue->voucher_id) }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

