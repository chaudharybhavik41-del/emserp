@extends('layouts.erp')

@section('title', 'Salary Advance Details')

@section('content')
<div class="container-fluid py-3">
    @if($advance->employee)
        @include('hr.employees.partials.hub-nav', ['employee' => $advance->employee, 'activeSection' => 'loans-advances'])
    @endif

    @include('partials.flash')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Advance {{ $advance->advance_number }}</h4>
            <p class="text-muted mb-0">{{ $advance->employee?->full_name }}</p>
        </div>
        <div class="d-flex gap-2">
            @if(auth()->user()?->can('hr.employee.update') && in_array($advance->status, ['applied', 'rejected'], true) && empty($advance->disbursement_voucher_id) && (float) $advance->recovered_amount <= 0)
                <a href="{{ route('hr.advances.salary-advances.edit', $advance) }}" class="btn btn-outline-primary btn-sm">Edit</a>
            @endif
            @if($advance->employee)
                <a href="{{ route('hr.employees.loans-advances', $advance->employee) }}" class="btn btn-outline-secondary btn-sm">Back to Employee</a>
            @else
                <a href="{{ route('hr.advances.salary-advances.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Requested</small><h5>₹{{ number_format($advance->requested_amount, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Approved</small><h5>₹{{ number_format($advance->approved_amount, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Disbursed</small><h5>₹{{ number_format($advance->disbursed_amount, 2) }}</h5><div class="text-muted small">{{ $advance->disbursement_date?->format('d M Y') ?: '-' }}</div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Balance</small><h5>₹{{ number_format($advance->balance_amount, 2) }}</h5></div></div></div>
    </div>

    <div class="card mb-3"><div class="card-body"><div class="row g-3"><div class="col-md-3"><strong>Status:</strong> <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $advance->status)) }}</span></div><div class="col-md-3"><strong>Recovery Months:</strong> {{ $advance->recovery_months }}</div><div class="col-md-3"><strong>Monthly Deduction:</strong> ₹{{ number_format($advance->monthly_deduction, 2) }}</div><div class="col-md-3"><strong>Recovery Start:</strong> {{ $advance->recovery_start_date?->format('d M Y') ?: '-' }}</div><div class="col-12"><strong>Purpose:</strong> {{ $advance->purpose }}</div></div></div></div>

    @can('hr.employee.update')
    <div class="row g-3">
        <div class="col-md-3"><div class="card"><div class="card-header">Approve</div><div class="card-body"><form method="POST" action="{{ route('hr.advances.salary-advances.approve', $advance) }}">@csrf<div class="mb-2"><input type="number" name="approved_amount" min="0" step="0.01" class="form-control form-control-sm" placeholder="Approved Amount"></div><div class="mb-2"><input type="number" name="recovery_months" min="1" max="60" class="form-control form-control-sm" placeholder="Recovery Months"></div><button class="btn btn-success btn-sm">Approve</button></form></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-header">Reject</div><div class="card-body"><form method="POST" action="{{ route('hr.advances.salary-advances.reject', $advance) }}">@csrf<div class="mb-2"><textarea name="rejection_reason" class="form-control form-control-sm" rows="3" placeholder="Rejection reason" required></textarea></div><button class="btn btn-danger btn-sm">Reject</button></form></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-header">Disburse</div><div class="card-body"><form method="POST" action="{{ route('hr.advances.salary-advances.disburse', $advance) }}">@csrf<div class="mb-2"><input type="number" name="disbursed_amount" min="0" step="0.01" class="form-control form-control-sm" placeholder="Disbursed Amount"></div><div class="mb-2"><input type="date" name="disbursement_date" class="form-control form-control-sm" value="{{ old('disbursement_date', now()->format('Y-m-d')) }}"></div><div class="mb-2"><input type="date" name="recovery_start_date" class="form-control form-control-sm"></div><button class="btn btn-primary btn-sm">Disburse</button></form></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-header">Cancel</div><div class="card-body"><form method="POST" action="{{ route('hr.advances.salary-advances.cancel', $advance) }}">@csrf<div class="mb-2"><input type="date" name="reversal_date" class="form-control form-control-sm" value="{{ optional($advance->approved_at)->format('Y-m-d') ?? now()->format('Y-m-d') }}" required></div><div class="mb-2"><textarea name="cancellation_reason" class="form-control form-control-sm" rows="3" placeholder="Cancellation reason" required></textarea></div><button class="btn btn-outline-dark btn-sm">Cancel Advance</button></form></div></div></div>
    </div>
    @endcan
</div>
@endsection
