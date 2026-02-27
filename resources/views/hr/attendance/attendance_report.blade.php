@extends('layouts.erp')

@section('title', 'Attendance Report')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Attendance Report</h1>
            <small class="text-muted">{{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</small>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
            <a href="{{ route('hr.attendance.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('hr.attendance.report') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" 
                           value="{{ $startDate->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" 
                           value="{{ $endDate->format('Y-m-d') }}">
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
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Report Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Emp Code</th>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th class="text-center">Working Days</th>
                            <th class="text-center">Present</th>
                            <th class="text-center">Absent</th>
                            <th class="text-center">Half Day</th>
                            <th class="text-center">Late</th>
                            <th class="text-center">Leave</th>
                            <th class="text-center">Holiday</th>
                            <th class="text-center">Weekly Off</th>
                            <th class="text-center">OT Hours</th>
                            <th class="text-center">Paid Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData as $data)
                            <tr>
                                <td>{{ $data['employee_code'] }}</td>
                                <td>{{ $data['employee_name'] }}</td>
                                <td>{{ $data['department'] }}</td>
                                <td class="text-center">{{ $data['working_days'] }}</td>
                                <td class="text-center text-success fw-bold">{{ $data['present'] }}</td>
                                <td class="text-center text-danger fw-bold">{{ $data['absent'] }}</td>
                                <td class="text-center text-warning">{{ $data['half_day'] }}</td>
                                <td class="text-center text-warning">{{ $data['late'] }}</td>
                                <td class="text-center text-info">{{ $data['leave'] }}</td>
                                <td class="text-center">{{ $data['holiday'] }}</td>
                                <td class="text-center">{{ $data['weekly_off'] }}</td>
                                <td class="text-center text-primary">{{ number_format($data['ot_hours'], 1) }}</td>
                                <td class="text-center fw-bold">{{ number_format($data['paid_days'], 1) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-muted py-4">
                                    No attendance data found for the selected period
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($reportData) > 0)
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="text-end">Total:</td>
                            <td class="text-center">{{ collect($reportData)->sum('working_days') }}</td>
                            <td class="text-center">{{ collect($reportData)->sum('present') }}</td>
                            <td class="text-center">{{ collect($reportData)->sum('absent') }}</td>
                            <td class="text-center">{{ collect($reportData)->sum('half_day') }}</td>
                            <td class="text-center">{{ collect($reportData)->sum('late') }}</td>
                            <td class="text-center">{{ collect($reportData)->sum('leave') }}</td>
                            <td class="text-center">{{ collect($reportData)->sum('holiday') }}</td>
                            <td class="text-center">{{ collect($reportData)->sum('weekly_off') }}</td>
                            <td class="text-center">{{ number_format(collect($reportData)->sum('ot_hours'), 1) }}</td>
                            <td class="text-center">{{ number_format(collect($reportData)->sum('paid_days'), 1) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    @if(count($reportData) > 0)
    <div class="row g-3 mt-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0">{{ number_format(collect($reportData)->avg('present'), 1) }}</h4>
                    <small>Avg. Present Days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0">{{ number_format(collect($reportData)->avg('absent'), 1) }}</h4>
                    <small>Avg. Absent Days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h4 class="mb-0">{{ number_format(collect($reportData)->avg('late'), 1) }}</h4>
                    <small>Avg. Late Count</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4 class="mb-0">{{ number_format(collect($reportData)->sum('ot_hours'), 1) }}</h4>
                    <small>Total OT Hours</small>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('styles')
<style>
@media print {
    .btn-group, .card-body form, .no-print { display: none !important; }
    .card { border: none !important; }
    .table { font-size: 10px; }
}
</style>
@endpush
@endsection
