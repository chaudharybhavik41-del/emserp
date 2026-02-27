@extends('layouts.erp')

@section('title', 'Leave Balance Report')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Leave Balance Report</h1>
            <small class="text-muted">View leave balances for all employees</small>
        </div>
        <div>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
            @if(Route::has('hr.leave.index'))
            <a href="{{ route('hr.leave.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Leave Type</label>
                    <select name="leave_type_id" class="form-select">
                        <option value="">All Leave Types</option>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}" {{ request('leave_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        @for($y = date('Y'); $y >= date('Y') - 3; $y--)
                            <option value="{{ $y }}" {{ request('year', date('Y')) == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Employees</h6>
                    <h3 class="mb-0">{{ $balances->total() }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Credited</h6>
                    <h3 class="mb-0">{{ number_format($balances->sum(fn($b) => $b->opening_balance + $b->credited), 1) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="card-title">Total Taken</h6>
                    <h3 class="mb-0">{{ number_format($balances->sum('taken'), 1) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Available</h6>
                    <h3 class="mb-0">{{ number_format($balances->sum('closing_balance'), 1) }}</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Balance Table --}}
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Leave Balances - {{ request('year', date('Y')) }}</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Leave Type</th>
                            <th class="text-center">Opening</th>
                            <th class="text-center">Credited</th>
                            <th class="text-center">Taken</th>
                            <th class="text-center">Adjusted</th>
                            <th class="text-center">Lapsed</th>
                            <th class="text-center">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($balances as $balance)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $balance->employee->full_name ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ $balance->employee->employee_code ?? '-' }}</small>
                                </td>
                                <td>{{ $balance->employee->department->name ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ $balance->leaveType->code ?? '-' }}</span>
                                    {{ $balance->leaveType->name ?? '-' }}
                                </td>
                                <td class="text-center">{{ number_format($balance->opening_balance, 1) }}</td>
                                <td class="text-center text-success">{{ number_format($balance->credited, 1) }}</td>
                                <td class="text-center text-danger">{{ number_format($balance->taken, 1) }}</td>
                                <td class="text-center">{{ number_format($balance->adjusted, 1) }}</td>
                                <td class="text-center text-muted">{{ number_format($balance->lapsed, 1) }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $balance->closing_balance > 0 ? 'bg-success' : ($balance->closing_balance < 0 ? 'bg-danger' : 'bg-secondary') }} fs-6">
                                        {{ number_format($balance->closing_balance, 1) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No leave balance records found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($balances->hasPages())
        <div class="card-footer">
            {{ $balances->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

@push('styles')
<style>
@media print {
    .btn, form, .card-footer { display: none !important; }
    .card { border: 1px solid #000 !important; }
}
</style>
@endpush
@endsection
