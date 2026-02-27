@extends('layouts.erp')

@section('title', 'Loans Dashboard')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Loans Dashboard</h4>
        <a href="{{ route('hr.loans.employee-loans.create') }}" class="btn btn-primary btn-sm">New Loan Application</a>
    </div>

    @include('partials.flash')

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Pending</small><h4 class="mb-0">{{ $summary['pending'] }}</h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Approved</small><h4 class="mb-0">{{ $summary['approved'] }}</h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Active</small><h4 class="mb-0">{{ $summary['active'] }}</h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Outstanding</small><h4 class="mb-0">₹{{ number_format($summary['outstanding'], 2) }}</h4></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Recent Loans</strong>
            <a href="{{ route('hr.loans.employee-loans.index') }}" class="btn btn-outline-primary btn-sm">Open Loan Register</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Loan No</th><th>Employee</th><th>Type</th><th>Applied</th><th>Status</th><th class="text-end">View</th></tr></thead>
                    <tbody>
                        @forelse($recentLoans as $loan)
                            <tr>
                                <td><code>{{ $loan->loan_number }}</code></td>
                                <td>{{ $loan->employee?->full_name }}</td>
                                <td>{{ $loan->loanType?->name }}</td>
                                <td>₹{{ number_format($loan->applied_amount, 2) }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span></td>
                                <td class="text-end"><a href="{{ route('hr.loans.employee-loans.show', $loan) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No loan records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
