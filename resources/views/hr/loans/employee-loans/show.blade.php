@extends('layouts.erp')

@section('title', 'Loan Details')

@section('content')
<div class="container-fluid py-3">
    @include('partials.flash')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Loan {{ $loan->loan_number }}</h4>
            <p class="text-muted mb-0">{{ $loan->employee?->full_name }} | {{ $loan->loanType?->name }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('hr.loans.employee-loans.edit', $loan) }}" class="btn btn-outline-primary btn-sm">Edit</a>
            <a href="{{ route('hr.loans.employee-loans.schedule', $loan) }}" class="btn btn-outline-secondary btn-sm">Repayment Schedule</a>
            <a href="{{ route('hr.loans.employee-loans.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Applied</small><h5>₹{{ number_format($loan->applied_amount, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Approved</small><h5>₹{{ number_format($loan->approved_amount, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Disbursed</small><h5>₹{{ number_format($loan->disbursed_amount, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Outstanding</small><h5>₹{{ number_format($loan->total_outstanding, 2) }}</h5></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><strong>Status:</strong> <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span></div>
                <div class="col-md-3"><strong>Tenure:</strong> {{ $loan->tenure_months }} months</div>
                <div class="col-md-3"><strong>Rate:</strong> {{ number_format($loan->interest_rate, 2) }}%</div>
                <div class="col-md-3"><strong>EMI:</strong> ₹{{ number_format($loan->emi_amount, 2) }}</div>
                <div class="col-12"><strong>Purpose:</strong> {{ $loan->purpose ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card"><div class="card-header">Approve</div><div class="card-body">
                <form method="POST" action="{{ route('hr.loans.employee-loans.approve', $loan) }}">@csrf
                    <div class="mb-2"><input type="number" name="approved_amount" class="form-control form-control-sm" placeholder="Approved Amount" min="0" step="0.01"></div>
                    <div class="mb-2"><textarea name="approval_remarks" class="form-control form-control-sm" rows="2" placeholder="Remarks"></textarea></div>
                    <button class="btn btn-success btn-sm">Approve</button>
                </form>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-header">Reject</div><div class="card-body">
                <form method="POST" action="{{ route('hr.loans.employee-loans.reject', $loan) }}">@csrf
                    <div class="mb-2"><textarea name="rejection_reason" class="form-control form-control-sm" rows="3" placeholder="Reason" required></textarea></div>
                    <button class="btn btn-danger btn-sm">Reject</button>
                </form>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-header">Disburse</div><div class="card-body">
                <form method="POST" action="{{ route('hr.loans.employee-loans.disburse', $loan) }}">@csrf
                    <div class="mb-2"><input type="number" name="disbursed_amount" class="form-control form-control-sm" placeholder="Disbursed Amount" min="0" step="0.01"></div>
                    <div class="mb-2"><input type="date" name="emi_start_date" class="form-control form-control-sm"></div>
                    <button class="btn btn-primary btn-sm">Disburse</button>
                </form>
            </div></div>
        </div>
    </div>
</div>
@endsection
