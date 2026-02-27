@extends('layouts.erp')

@section('title', 'Support - Daily Digest')

@section('content')
@php
    $fmtMoney = function ($v) {
        return number_format((float) ($v ?? 0), 2);
    };
    $fmtInt = function ($v) {
        return number_format((int) ($v ?? 0));
    };
    $prettyStatus = function ($v) {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            $s = 'open';
        }
        return ucwords(str_replace('_', ' ', $s));
    };
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Daily Digest</h4>
        <div class="text-muted">Brief summary of ERP activities for the selected date</div>
    </div>
    <div class="d-flex gap-2">
        @can('support.digest.update')
            <a href="{{ route('support.digest.recipients') }}" class="btn btn-outline-secondary">
                <i class="bi bi-people me-1"></i> Recipients
            </a>
        @endcan
    </div>
</div>

@include('partials.alerts')

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <form method="GET" action="{{ route('support.digest.preview') }}" class="d-flex gap-2">
                    <div class="flex-grow-1">
                        <label class="form-label">Digest Date</label>
                        <input type="date" name="date" class="form-control" value="{{ request('date', $digestDate->toDateString()) }}">
                    </div>
                    <div>
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-outline-primary w-100" type="submit">
                            <i class="bi bi-arrow-repeat me-1"></i> Refresh
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-md-8 text-md-end">
                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                    @can('support.digest.send')
                        <form action="{{ route('support.digest.send') }}" method="POST">
                            @csrf
                            <input type="hidden" name="date" value="{{ request('date', $digestDate->toDateString()) }}">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Send digest to all configured recipients?');">
                                <i class="bi bi-envelope-paper me-1"></i> Send to Recipients
                            </button>
                        </form>
                    @endcan

                    <form action="{{ route('support.digest.send_test') }}" method="POST">
                        @csrf
                        <input type="hidden" name="date" value="{{ request('date', $digestDate->toDateString()) }}">
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bi bi-send me-1"></i> Send Test to Me
                        </button>
                    </form>
                </div>
                <div class="text-muted small mt-2">Digest covers activities on <strong>{{ $digestDate->format('d M Y') }}</strong>.</div>
            </div>
        </div>
    </div>
</div>

{{-- STORE --}}
<div class="card mb-3">
    <div class="card-header"><strong>Store Summary</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted">Material Inward (GRN)</div>
                <div class="fs-5 fw-semibold">₹ {{ $fmtMoney($digest['store']['inward']['value_total'] ?? 0) }}</div>
                <div class="text-muted small">GRNs: {{ $fmtInt($digest['store']['inward']['grn_count'] ?? 0) }} | Lines: {{ $fmtInt($digest['store']['inward']['line_count'] ?? 0) }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted">Material Issued</div>
                <div class="fs-5 fw-semibold">₹ {{ $fmtMoney($digest['store']['issue']['value_total'] ?? 0) }}</div>
                <div class="text-muted small">Issues: {{ $fmtInt($digest['store']['issue']['issue_count'] ?? 0) }} | Lines: {{ $fmtInt($digest['store']['issue']['line_count'] ?? 0) }}</div>
            </div>
        </div>
        <div class="text-muted small mt-2">
            Note: Values are estimated using linked Purchase Order item rates where available.
        </div>
    </div>
</div>

{{-- PRODUCTION --}}
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Production Summary</strong>
        <span class="text-muted small">DPRs: {{ $fmtInt($digest['production']['dpr_count'] ?? 0) }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="text-muted">Total Qty reported</div>
                <div class="fs-5 fw-semibold">{{ $fmtMoney($digest['production']['qty_total'] ?? 0) }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted">Total Minutes</div>
                <div class="fs-5 fw-semibold">{{ $fmtInt($digest['production']['mins_total'] ?? 0) }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted">DPRs submitted/approved</div>
                <div class="fs-5 fw-semibold">{{ $fmtInt($digest['production']['dpr_count'] ?? 0) }}</div>
            </div>
        </div>

        @if(empty($digest['production']['projects']))
            <div class="text-muted">No production DPR entries found for this date.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th class="text-end">DPRs</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Minutes</th>
                            <th class="text-end">Completed Steps</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($digest['production']['projects'] as $p)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $p['project_code'] ?? '' }}{{ !empty($p['project_name']) ? ' - ' . $p['project_name'] : '' }}</div>
                                </td>
                                <td class="text-end">{{ $fmtInt($p['dpr_count'] ?? 0) }}</td>
                                <td class="text-end">{{ $fmtMoney($p['qty_total'] ?? 0) }}</td>
                                <td class="text-end">{{ $fmtInt($p['mins_total'] ?? 0) }}</td>
                                <td class="text-end">{{ $fmtInt($p['completed_steps'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- CRM --}}
<div class="card mb-3">
    <div class="card-header"><strong>CRM Summary</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted">Leads Created</div>
                <div class="fs-5 fw-semibold">{{ $fmtInt($digest['crm']['leads_created'] ?? 0) }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted">Activities Logged</div>
                <div class="fs-5 fw-semibold">{{ $fmtInt($digest['crm']['activities_logged'] ?? 0) }}</div>
                <div class="text-muted small">Completed: {{ $fmtInt($digest['crm']['activities_completed'] ?? 0) }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted">Quotations Created</div>
                <div class="fs-5 fw-semibold">{{ $fmtInt($digest['crm']['quotations_created'] ?? 0) }}</div>
                <div class="text-muted small">₹ {{ $fmtMoney($digest['crm']['quotations_created_value'] ?? 0) }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted">Quotations Sent</div>
                <div class="fs-5 fw-semibold">{{ $fmtInt($digest['crm']['quotations_sent'] ?? 0) }}</div>
                <div class="text-muted small">₹ {{ $fmtMoney($digest['crm']['quotations_sent_value'] ?? 0) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- PURCHASE --}}
<div class="card mb-3">
    <div class="card-header"><strong>Purchase Indents</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted">Open Indents</div>
                <div class="fs-5 fw-semibold">{{ $fmtInt($digest['purchase']['open_indents'] ?? 0) }}</div>
                <div class="text-muted small">(Status not rejected/closed)</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted">Approved (pending procurement)</div>
                <div class="fs-5 fw-semibold">{{ $fmtInt($digest['purchase']['approved_pending_proc'] ?? 0) }}</div>

                @if(!empty($digest['purchase']['by_procurement_status']))
                    <div class="small mt-1">
                        @foreach($digest['purchase']['by_procurement_status'] as $st)
                            @php
                                $label = $prettyStatus($st['status'] ?? $st['procurement_status'] ?? 'open');
                            @endphp
                            <span class="badge bg-light text-dark me-1">{{ $label }}: {{ $fmtInt($st['count'] ?? 0) }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="col-md-4">
                <div class="text-muted">Overdue Required-by (Top shown)</div>
                @php
                    $overdueList = $digest['purchase']['overdue_required_by'] ?? [];
                    $overdueCount = is_array($overdueList) ? count($overdueList) : 0;
                @endphp
                <div class="fs-5 fw-semibold">{{ $fmtInt($overdueCount) }}</div>
                <div class="text-muted small">Showing up to 10 oldest overdue</div>
            </div>
        </div>

        @if(!empty($digest['purchase']['overdue_required_by']))
            <div class="mt-3">
                <div class="text-muted fw-semibold mb-2">Top Overdue Indents</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Indent</th>
                                <th>Project</th>
                                <th class="text-end">Required By</th>
                                <th class="text-end">Procurement</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($digest['purchase']['overdue_required_by'] as $i)
                                <tr>
                                    <td class="fw-semibold">{{ $i['code'] ?? '-' }}</td>
                                    <td>
                                        {{ $i['project_code'] ?? '' }}
                                        @if(!empty($i['project_name']))
                                            - {{ $i['project_name'] }}
                                        @endif
                                    </td>
                                    <td class="text-end">{{ !empty($i['required_by_date']) ? \Carbon\Carbon::parse($i['required_by_date'])->format('d-m-Y') : '-' }}</td>
                                    <td class="text-end">
                                        <span class="badge bg-light text-dark">{{ $prettyStatus($i['procurement_status'] ?? 'open') }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- PAYMENTS --}}
<div class="card mb-3">
    <div class="card-header"><strong>Payment Reminders</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted">Supplier Bills Overdue</div>
                <div class="fs-5 fw-semibold">₹ {{ $fmtMoney($digest['payments']['supplier']['overdue_value'] ?? 0) }}</div>
                <div class="text-muted small">Count: {{ $fmtInt($digest['payments']['supplier']['overdue_count'] ?? 0) }}</div>

                <div class="text-muted mt-2">Supplier Bills Due Next 7 Days</div>
                <div class="fw-semibold">₹ {{ $fmtMoney($digest['payments']['supplier']['due_soon_value'] ?? 0) }}</div>
                <div class="text-muted small">Count: {{ $fmtInt($digest['payments']['supplier']['due_soon_count'] ?? 0) }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted">Client Receivables Overdue</div>
                <div class="fs-5 fw-semibold">₹ {{ $fmtMoney($digest['payments']['client']['overdue_value'] ?? 0) }}</div>
                <div class="text-muted small">Count: {{ $fmtInt($digest['payments']['client']['overdue_count'] ?? 0) }}</div>

                <div class="text-muted mt-2">Client Receivables Due Next 7 Days</div>
                <div class="fw-semibold">₹ {{ $fmtMoney($digest['payments']['client']['due_soon_value'] ?? 0) }}</div>
                <div class="text-muted small">Count: {{ $fmtInt($digest['payments']['client']['due_soon_count'] ?? 0) }}</div>
            </div>
        </div>

        <div class="text-muted small mt-2">
            Payment reminders are calculated as of today (not the digest date).
        </div>
    </div>
</div>

@endsection
