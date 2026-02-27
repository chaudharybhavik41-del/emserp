@extends('layouts.erp')

@section('title', 'Loan Repayment Schedule')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Repayment Schedule</h4>
            <p class="text-muted mb-0">{{ $loan->loan_number }} - {{ $loan->employee?->full_name }}</p>
        </div>
        <a href="{{ route('hr.loans.employee-loans.show', $loan) }}" class="btn btn-outline-secondary btn-sm">Back to Loan</a>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>#</th><th>Due Date</th><th>Opening</th><th>Principal</th><th>Interest</th><th>EMI</th><th>Paid</th><th>Closing</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($loan->repayments as $repayment)
                            <tr>
                                <td>{{ $repayment->installment_no }}</td>
                                <td>{{ $repayment->due_date?->format('d M Y') }}</td>
                                <td>₹{{ number_format($repayment->opening_balance, 2) }}</td>
                                <td>₹{{ number_format($repayment->principal_amount, 2) }}</td>
                                <td>₹{{ number_format($repayment->interest_amount, 2) }}</td>
                                <td>₹{{ number_format($repayment->emi_amount, 2) }}</td>
                                <td>₹{{ number_format($repayment->paid_amount, 2) }}</td>
                                <td>₹{{ number_format($repayment->closing_balance, 2) }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst($repayment->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-3">No repayment schedule generated yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
