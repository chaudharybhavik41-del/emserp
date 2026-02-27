@extends('layouts.erp')

@section('title', 'Employee Loans')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Employee Loans</h4>
            <p class="text-muted mb-0">Loan register with approvals and disbursement status.</p>
        </div>
        <a href="{{ route('hr.loans.employee-loans.create') }}" class="btn btn-primary btn-sm">Apply Loan</a>
    </div>

    @include('partials.flash')

    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="employee_id" class="form-select form-select-sm">
                        <option value="">All Employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>
                                {{ $employee->employee_code }} - {{ trim($employee->first_name . ' ' . $employee->last_name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        @foreach(['applied','pending_approval','approved','rejected','disbursed','active','closed','written_off','cancelled'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('hr.loans.employee-loans.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Loan No</th><th>Employee</th><th>Type</th><th>Applied</th><th>Approved</th><th>Outstanding</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        @forelse($loans as $loan)
                            <tr>
                                <td><code>{{ $loan->loan_number }}</code></td>
                                <td>{{ $loan->employee?->full_name }}</td>
                                <td>{{ $loan->loanType?->name }}</td>
                                <td>₹{{ number_format($loan->applied_amount, 2) }}</td>
                                <td>₹{{ number_format($loan->approved_amount, 2) }}</td>
                                <td>₹{{ number_format($loan->total_outstanding, 2) }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span></td>
                                <td class="text-end"><a href="{{ route('hr.loans.employee-loans.show', $loan) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-3">No loans found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($loans->hasPages())<div class="card-footer">{{ $loans->links() }}</div>@endif
    </div>
</div>
@endsection
