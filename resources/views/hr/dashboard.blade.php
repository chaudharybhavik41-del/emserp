@extends('layouts.erp')

@section('title', 'HR Dashboard')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">HR Dashboard</h1>
            <small class="text-muted">{{ now()->format('l, d F Y') }}</small>
        </div>
    </div>

    {{-- Employee Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">Total Employees</h6>
                            <h2 class="mb-0">{{ number_format($employeeStats['total']) }}</h2>
                            <small>{{ $employeeStats['active'] }} Active</small>
                        </div>
                        <div class="fs-1 opacity-50"><i class="bi bi-people"></i></div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <a href="{{ route('hr.employees.index') }}" class="text-white text-decoration-none small">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">Present Today</h6>
                            <h2 class="mb-0">{{ $todayAttendance['present'] }}</h2>
                            <small>{{ $todayAttendance['late'] }} Late</small>
                        </div>
                        <div class="fs-1 opacity-50"><i class="bi bi-person-check"></i></div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <a href="{{ route('hr.attendance.index') }}" class="text-white text-decoration-none small">
                        View Attendance <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>On Leave Today</h6>
                            <h2 class="mb-0">{{ $todayAttendance['on_leave'] }}</h2>
                            <small>{{ $todayAttendance['absent'] }} Absent</small>
                        </div>
                        <div class="fs-1 opacity-50"><i class="bi bi-person-dash"></i></div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <a href="{{ route('hr.leave.index') }}" class="text-dark text-decoration-none small">
                        View Leaves <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">Pending Approvals</h6>
                            <h2 class="mb-0">{{ $pendingApprovals['leave'] + $pendingApprovals['overtime'] + $pendingApprovals['regularization'] }}</h2>
                            <small>{{ $pendingApprovals['leave'] }} Leave, {{ $pendingApprovals['overtime'] }} OT</small>
                        </div>
                        <div class="fs-1 opacity-50"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <a href="{{ route('hr.leave.pending') }}" class="text-white text-decoration-none small">
                        View Pending <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Left Column --}}
        <div class="col-md-8">
            {{-- Current Payroll --}}
            @if($currentPayroll)
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-currency-rupee me-2"></i>Current Payroll Period</h6>
                    <span class="badge bg-{{ $currentPayroll->status->color() }}">{{ $currentPayroll->status->label() }}</span>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h5 class="mb-0">{{ $currentPayroll->period_name }}</h5>
                            <small class="text-muted">Period</small>
                        </div>
                        <div class="col-md-3">
                            <h5 class="mb-0">{{ $currentPayroll->payrolls_count ?? 0 }}</h5>
                            <small class="text-muted">Employees</small>
                        </div>
                        <div class="col-md-3">
                            <h5 class="mb-0">₹{{ number_format($currentPayroll->total_amount ?? 0, 0) }}</h5>
                            <small class="text-muted">Total Amount</small>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('hr.payroll.period', $currentPayroll) }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye me-1"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Attendance Trend Chart --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Attendance Trend (Last 7 Days)</h6>
                </div>
                <div class="card-body">
                    <canvas id="attendanceChart" height="100"></canvas>
                </div>
            </div>

            {{-- Recent Leave Applications --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Recent Leave Applications</h6>
                    <a href="{{ route('hr.leave.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Period</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentLeaves as $leave)
                                    <tr>
                                        <td>
                                            {{ $leave->employee->full_name }}
                                            <br><small class="text-muted">{{ $leave->employee->employee_code }}</small>
                                        </td>
                                        <td>
                                            <span class="badge" style="background-color: {{ $leave->leaveType->color_code ?? '#6c757d' }}">
                                                {{ $leave->leaveType->short_name }}
                                            </span>
                                        </td>
                                        <td>{{ $leave->from_date->format('d M') }} - {{ $leave->to_date->format('d M') }}</td>
                                        <td>{{ $leave->total_days }}</td>
                                        <td><span class="badge bg-{{ $leave->status->color() }}">{{ $leave->status->label() }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">No recent applications</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column --}}
        <div class="col-md-4">
            {{-- On Leave Today --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-person-dash me-2"></i>On Leave Today ({{ $onLeaveToday->count() }})</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($onLeaveToday as $leave)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $leave->employee->full_name }}</strong>
                                    <br><small class="text-muted">{{ $leave->employee->department?->name }}</small>
                                </div>
                                <span class="badge" style="background-color: {{ $leave->leaveType->color_code ?? '#6c757d' }}">
                                    {{ $leave->leaveType->short_name }}
                                </span>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted">No one on leave today</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Upcoming Birthdays --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-gift me-2"></i>Upcoming Birthdays</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($upcomingBirthdays as $emp)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $emp->full_name }}</strong>
                                    <br><small class="text-muted">{{ $emp->department?->name }}</small>
                                </div>
                                <span class="badge bg-primary">{{ $emp->date_of_birth->format('d M') }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted">No upcoming birthdays</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Work Anniversaries --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-award me-2"></i>Work Anniversaries</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($upcomingAnniversaries as $emp)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $emp->full_name }}</strong>
                                    <br><small class="text-muted">{{ $emp->service_years }} years on {{ $emp->date_of_joining->format('d M') }}</small>
                                </div>
                                <span class="badge bg-success">{{ $emp->service_years + 1 }} yrs</span>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted">No upcoming anniversaries</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Probation Due --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-hourglass-bottom me-2"></i>Probation Ending Soon</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($probationDue as $emp)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $emp->full_name }}</strong>
                                    <br><small class="text-muted">{{ $emp->designation?->name }}</small>
                                </div>
                                <span class="badge bg-warning text-dark">{{ $emp->probation_end_date?->format('d M') }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted">No probations ending soon</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Department Distribution --}}
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Department Distribution</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach($departmentWise as $dept)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                {{ $dept['department'] }}
                                <span class="badge bg-secondary">{{ $dept['count'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    
    const attendanceData = @json($attendanceTrend);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: attendanceData.map(d => {
                const date = new Date(d.attendance_date);
                return date.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric' });
            }),
            datasets: [
                {
                    label: 'Present',
                    data: attendanceData.map(d => d.present),
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Absent',
                    data: attendanceData.map(d => d.absent),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'On Leave',
                    data: attendanceData.map(d => d.on_leave),
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script>
@endpush
@endsection



