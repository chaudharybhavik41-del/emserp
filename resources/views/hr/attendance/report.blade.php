@extends('layouts.erp')

@section('title', 'Attendance Report')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Attendance Report</h1>
            <small class="text-muted">
                {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}
            </small>
        </div>
        <div>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
            @if(Route::has('hr.attendance.index'))
            <a href="{{ route('hr.attendance.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4 print-hide">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" 
                           value="{{ request('from_date', $startDate->toDateString()) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" 
                           value="{{ request('to_date', $endDate->toDateString()) }}">
                </div>
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
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary Cards --}}
    @if(count($reportData) > 0)
    @php
        $totalPresent = collect($reportData)->sum('present');
        $totalAbsent = collect($reportData)->sum('absent');
        $totalLate = collect($reportData)->sum('late_count');
        $totalOT = collect($reportData)->sum('ot_hours');
    @endphp
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>Total Present Days</h6>
                    <h3 class="mb-0">{{ $totalPresent }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6>Total Absent Days</h6>
                    <h3 class="mb-0">{{ $totalAbsent }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6>Late Count</h6>
                    <h3 class="mb-0">{{ $totalLate }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>Total OT Hours</h6>
                    <h3 class="mb-0">{{ number_format($totalOT, 1) }}</h3>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Report Table --}}
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Attendance Summary</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Employee Code</th>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th class="text-center">Days</th>
                            <th class="text-center">Present</th>
                            <th class="text-center">Absent</th>
                            <th class="text-center">Half Day</th>
                            <th class="text-center">Leave</th>
                            <th class="text-center">W/O</th>
                            <th class="text-center">Holiday</th>
                            <th class="text-center">Late</th>
                            <th class="text-center">OT Hrs</th>
                            <th class="text-center">Paid Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData as $data)
                            @php
                                $employee = $data['employee'];
                            @endphp
                            <tr>
                                <td>{{ $employee->employee_code ?? '-' }}</td>
                                <td>{{ $employee->full_name ?? '-' }}</td>
                                <td>{{ $employee->department->name ?? '-' }}</td>
                                <td class="text-center">{{ $data['total_days'] }}</td>
                                <td class="text-center text-success fw-bold">{{ $data['present'] }}</td>
                                <td class="text-center text-danger fw-bold">{{ $data['absent'] }}</td>
                                <td class="text-center text-warning fw-bold">{{ $data['half_day'] }}</td>
                                <td class="text-center text-info">{{ $data['leave'] }}</td>
                                <td class="text-center text-secondary">{{ $data['weekly_off'] }}</td>
                                <td class="text-center text-primary">{{ $data['holiday'] }}</td>
                                <td class="text-center">
                                    @if($data['late_count'] > 0)
                                        <span class="badge bg-warning text-dark">{{ $data['late_count'] }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-center">{{ number_format($data['ot_hours'], 1) }}</td>
                                <td class="text-center fw-bold">{{ number_format($data['paid_days'], 1) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No attendance data found for the selected period
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($reportData) > 0)
                    <tfoot class="table-secondary">
                        <tr class="fw-bold">
                            <td colspan="3">Total ({{ count($reportData) }} employees)</td>
                            <td class="text-center">-</td>
                            <td class="text-center text-success">{{ collect($reportData)->sum('present') }}</td>
                            <td class="text-center text-danger">{{ collect($reportData)->sum('absent') }}</td>
                            <td class="text-center text-warning">{{ collect($reportData)->sum('half_day') }}</td>
                            <td class="text-center text-info">{{ collect($reportData)->sum('leave') }}</td>
                            <td class="text-center text-secondary">{{ collect($reportData)->sum('weekly_off') }}</td>
                            <td class="text-center text-primary">{{ collect($reportData)->sum('holiday') }}</td>
                            <td class="text-center">{{ collect($reportData)->sum('late_count') }}</td>
                            <td class="text-center">{{ number_format(collect($reportData)->sum('ot_hours'), 1) }}</td>
                            <td class="text-center">{{ number_format(collect($reportData)->sum('paid_days'), 1) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
@media print {
    .print-hide, .btn, nav { display: none !important; }
    .card { border: 1px solid #000 !important; break-inside: avoid; }
    table { font-size: 10px !important; }
}
</style>
@endpush
@endsection
