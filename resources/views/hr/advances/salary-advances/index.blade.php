@extends('layouts.erp')

@section('title', 'Salary Advances')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Salary Advances</h4>
        <a href="{{ route('hr.advances.salary-advances.create') }}" class="btn btn-primary btn-sm">Apply Advance</a>
    </div>

    @include('partials.flash')

    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="employee_id" class="form-select form-select-sm">
                        <option value="">All Employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->employee_code }} - {{ trim($employee->first_name . ' ' . $employee->last_name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        @foreach(['applied','approved','rejected','disbursed','recovering','closed','cancelled'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button class="btn btn-secondary btn-sm">Filter</button> <a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.advances.salary-advances.index') }}">Reset</a></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>No</th><th>Employee</th><th>Requested</th><th>Approved</th><th>Balance</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        @forelse($advances as $advance)
                            <tr>
                                <td><code>{{ $advance->advance_number }}</code></td>
                                <td>{{ $advance->employee?->full_name }}</td>
                                <td>₹{{ number_format($advance->requested_amount, 2) }}</td>
                                <td>₹{{ number_format($advance->approved_amount, 2) }}</td>
                                <td>₹{{ number_format($advance->balance_amount, 2) }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $advance->status)) }}</span></td>
                                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('hr.advances.salary-advances.show', $advance) }}">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($advances->hasPages())<div class="card-footer">{{ $advances->links() }}</div>@endif
    </div>
</div>
@endsection
