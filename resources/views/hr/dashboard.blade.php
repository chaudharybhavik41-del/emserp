@extends('layouts.erp')

@section('title', 'HR Dashboard')

@section('page_header')
    <div>
        <h1 class="h5 mb-0">HR Dashboard</h1>
        <small class="text-muted">{{ now()->format('l, d F Y') }}</small>
    </div>
@endsection

@push('styles')
<style>
    .hr-hero {
        position: relative;
        overflow: hidden;
        padding: 1.45rem;
        border-radius: 1.35rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        background:
            radial-gradient(circle at top left, rgba(139, 92, 246, 0.1), transparent 30%),
            linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 255, 0.95));
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.06);
    }

    .hr-hero::after {
        content: "";
        position: absolute;
        inset: auto auto -5rem -4rem;
        width: 12rem;
        height: 12rem;
        border-radius: 999px;
        background: rgba(59, 130, 246, 0.08);
    }

    .hr-eyebrow {
        margin-bottom: 0.45rem;
        color: #7c3aed;
        font-size: 0.74rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .hr-hero-title {
        margin-bottom: 0.45rem;
        font-size: clamp(1.45rem, 1.1rem + 1vw, 2.15rem);
        font-weight: 700;
        letter-spacing: -0.03em;
        color: #0f172a;
    }

    .hr-hero-copy {
        max-width: 44rem;
        margin-bottom: 1rem;
        color: #475569;
    }

    .hr-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
    }

    .hr-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.5rem 0.8rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.82);
        border: 1px solid rgba(148, 163, 184, 0.18);
        color: #0f172a;
        font-size: 0.84rem;
    }

    .hr-chip strong {
        font-size: 0.96rem;
    }

    .hr-focus-panel {
        position: relative;
        z-index: 1;
        height: 100%;
        padding: 1rem;
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.82);
        border: 1px solid rgba(148, 163, 184, 0.2);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .hr-kpi {
        position: relative;
        overflow: hidden;
    }

    .hr-kpi .erp-kpi-body {
        padding: 1rem 1.05rem 1rem 1.35rem;
    }

    .hr-kpi .erp-kpi-label,
    .hr-kpi .erp-kpi-value,
    .hr-kpi .erp-kpi-meta-row {
        margin-left: 0.15rem;
    }

    .hr-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.85rem;
    }

    .hr-kpi::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 5px;
        background: var(--hr-accent, #7c3aed);
    }

    .hr-kpi--violet { --hr-accent: #7c3aed; }
    .hr-kpi--emerald { --hr-accent: #059669; }
    .hr-kpi--amber { --hr-accent: #d97706; }
    .hr-kpi--sky { --hr-accent: #0284c7; }

    .hr-panel {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.96));
    }

    .hr-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.95rem;
    }

    .hr-section-kicker {
        color: #7c3aed;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .hr-section-title {
        margin: 0.1rem 0 0;
        font-size: 1rem;
        font-weight: 650;
        color: #0f172a;
    }

    [data-bs-theme="dark"] .hr-hero {
        background:
            radial-gradient(circle at top left, rgba(168, 85, 247, 0.16), transparent 30%),
            linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(49, 46, 129, 0.22));
        border-color: rgba(255, 255, 255, 0.08);
    }

    [data-bs-theme="dark"] .hr-eyebrow,
    [data-bs-theme="dark"] .hr-section-kicker {
        color: #c4b5fd;
    }

    [data-bs-theme="dark"] .hr-hero-title,
    [data-bs-theme="dark"] .hr-section-title,
    [data-bs-theme="dark"] .hr-chip {
        color: #f8fafc;
    }

    [data-bs-theme="dark"] .hr-hero-copy {
        color: #cbd5e1;
    }

    [data-bs-theme="dark"] .hr-chip,
    [data-bs-theme="dark"] .hr-focus-panel {
        background: rgba(15, 23, 42, 0.55);
        border-color: rgba(255, 255, 255, 0.08);
    }

    [data-bs-theme="dark"] .hr-panel {
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.94), rgba(22, 26, 31, 0.96));
    }

    @media (max-width: 1199.98px) {
        .hr-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .hr-kpi-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
    <div class="hr-hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <div class="hr-eyebrow">People Pulse</div>
                <div class="hr-hero-title">Attendance, approvals and workforce rhythm.</div>
                <div class="hr-hero-copy">
                    Keep the people function focused on daily attendance health, approval load, and the upcoming employee moments that need attention.
                </div>
                <div class="hr-chip-row">
                    <span class="hr-chip"><i class="bi bi-people"></i> Active <strong>{{ $employeeStats['active'] }}</strong></span>
                    <span class="hr-chip"><i class="bi bi-person-check"></i> Present <strong>{{ $todayAttendance['present'] }}</strong></span>
                    <span class="hr-chip"><i class="bi bi-hourglass-split"></i> Pending <strong>{{ $pendingApprovals['leave'] + $pendingApprovals['overtime'] + $pendingApprovals['regularization'] }}</strong></span>
                    <span class="hr-chip"><i class="bi bi-gift"></i> Birthdays <strong>{{ $upcomingBirthdays->count() }}</strong></span>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hr-focus-panel">
                    <div class="hr-section-kicker">Today’s Focus</div>
                    <h3 class="h6 mb-2">Key people actions</h3>
                    <div class="small text-muted mb-3">Attendance exceptions, pending approvals and probation reviews usually need the fastest response from HR.</div>
                    <div class="d-grid gap-2">
                        <a href="{{ route('hr.attendance.index') }}" class="btn btn-primary btn-sm">Open Attendance</a>
                        <a href="{{ route('hr.leave.pending') }}" class="btn btn-outline-secondary btn-sm">Review Pending Leave</a>
                        <a href="{{ route('hr.employees.index') }}" class="btn btn-outline-secondary btn-sm">Open Employees</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="hr-kpi-grid mb-4">
        <div class="erp-kpi hr-kpi hr-kpi--violet">
            <div class="erp-kpi-body">
                <div>
                    <div class="erp-kpi-label">Total Employees</div>
                    <div class="erp-kpi-value">{{ number_format($employeeStats['total']) }}</div>
                    <div class="erp-kpi-meta-row">
                        <span class="erp-kpi-pill">Active <strong>{{ $employeeStats['active'] }}</strong></span>
                    </div>
                </div>
                <span class="erp-kpi-icon"><i class="bi bi-people"></i></span>
            </div>
        </div>

        <div class="erp-kpi hr-kpi hr-kpi--emerald">
            <div class="erp-kpi-body">
                <div>
                    <div class="erp-kpi-label">Present Today</div>
                    <div class="erp-kpi-value">{{ $todayAttendance['present'] }}</div>
                    <div class="erp-kpi-meta-row">
                        <span class="erp-kpi-pill">Late <strong>{{ $todayAttendance['late'] }}</strong></span>
                    </div>
                </div>
                <span class="erp-kpi-icon"><i class="bi bi-person-check"></i></span>
            </div>
        </div>

        <div class="erp-kpi hr-kpi hr-kpi--amber">
            <div class="erp-kpi-body">
                <div>
                    <div class="erp-kpi-label">On Leave Today</div>
                    <div class="erp-kpi-value">{{ $todayAttendance['on_leave'] }}</div>
                    <div class="erp-kpi-meta-row">
                        <span class="erp-kpi-pill">Absent <strong>{{ $todayAttendance['absent'] }}</strong></span>
                    </div>
                </div>
                <span class="erp-kpi-icon"><i class="bi bi-person-dash"></i></span>
            </div>
        </div>

        <div class="erp-kpi hr-kpi hr-kpi--sky">
            <div class="erp-kpi-body">
                <div>
                    <div class="erp-kpi-label">Pending Approvals</div>
                    <div class="erp-kpi-value">{{ $pendingApprovals['leave'] + $pendingApprovals['overtime'] + $pendingApprovals['regularization'] }}</div>
                    <div class="erp-kpi-meta-row">
                        <span class="erp-kpi-pill">Leave <strong>{{ $pendingApprovals['leave'] }}</strong></span>
                        <span class="erp-kpi-pill">OT <strong>{{ $pendingApprovals['overtime'] }}</strong></span>
                        <span class="erp-kpi-pill">Regularization <strong>{{ $pendingApprovals['regularization'] }}</strong></span>
                    </div>
                </div>
                <span class="erp-kpi-icon"><i class="bi bi-hourglass-split"></i></span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-8 erp-stack">
            @if($currentPayroll)
                <div class="erp-surface hr-panel p-3 p-lg-4">
                    <div class="hr-section-head">
                        <div>
                            <div class="hr-section-kicker">Payroll</div>
                            <div class="hr-section-title">Current Payroll Period</div>
                        </div>
                        <span class="badge bg-{{ $currentPayroll->status->color() }}">{{ $currentPayroll->status->label() }}</span>
                    </div>
                    <div class="row text-center g-3">
                        <div class="col-md-3">
                            <div class="small text-muted mb-1">Period</div>
                            <div class="fw-semibold">{{ $currentPayroll->period_name }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted mb-1">Employees</div>
                            <div class="fw-semibold">{{ $currentPayroll->payrolls_count ?? 0 }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted mb-1">Total Amount</div>
                            <div class="fw-semibold">₹{{ number_format($currentPayroll->total_amount ?? 0, 0) }}</div>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('hr.payroll.period', $currentPayroll) }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-eye me-1"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <div class="erp-surface hr-panel p-3 p-lg-4">
                <div class="hr-section-head">
                    <div>
                        <div class="hr-section-kicker">Trend</div>
                        <div class="hr-section-title">Attendance Trend</div>
                    </div>
                    <div class="small text-muted">Last 7 days</div>
                </div>
                <canvas id="attendanceChart" height="100"></canvas>
            </div>

            <div class="erp-surface hr-panel p-3 p-lg-4">
                <div class="hr-section-head">
                    <div>
                        <div class="hr-section-kicker">Requests</div>
                        <div class="hr-section-title">Recent Leave Applications</div>
                    </div>
                    <a href="{{ route('hr.leave.index') }}" class="btn btn-outline-primary btn-sm">View All</a>
                </div>
                @if($recentLeaves->isEmpty())
                    <div class="erp-empty-state py-4">
                        <div class="small">No recent applications</div>
                    </div>
                @else
                    <div class="erp-table-card">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Period</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentLeaves as $leave)
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
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-4 erp-stack">
            <div class="erp-surface hr-panel p-3">
                <div class="hr-section-head">
                    <div>
                        <div class="hr-section-kicker">Presence</div>
                        <div class="hr-section-title">On Leave Today ({{ $onLeaveToday->count() }})</div>
                    </div>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse($onLeaveToday as $leave)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <strong>{{ $leave->employee->full_name }}</strong>
                                <br><small class="text-muted">{{ $leave->employee->department?->name }}</small>
                            </div>
                            <span class="badge" style="background-color: {{ $leave->leaveType->color_code ?? '#6c757d' }}">
                                {{ $leave->leaveType->short_name }}
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item px-0 text-center text-muted">No one on leave today</li>
                    @endforelse
                </ul>
            </div>

            <div class="erp-surface hr-panel p-3">
                <div class="hr-section-head">
                    <div>
                        <div class="hr-section-kicker">Moments</div>
                        <div class="hr-section-title">Upcoming Birthdays</div>
                    </div>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse($upcomingBirthdays as $emp)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <strong>{{ $emp->full_name }}</strong>
                                <br><small class="text-muted">{{ $emp->department?->name }}</small>
                            </div>
                            <span class="badge bg-primary">{{ $emp->date_of_birth->format('d M') }}</span>
                        </li>
                    @empty
                        <li class="list-group-item px-0 text-center text-muted">No upcoming birthdays</li>
                    @endforelse
                </ul>
            </div>

            <div class="erp-surface hr-panel p-3">
                <div class="hr-section-head">
                    <div>
                        <div class="hr-section-kicker">Recognition</div>
                        <div class="hr-section-title">Work Anniversaries</div>
                    </div>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse($upcomingAnniversaries as $emp)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <strong>{{ $emp->full_name }}</strong>
                                <br><small class="text-muted">{{ $emp->service_years }} years on {{ $emp->date_of_joining->format('d M') }}</small>
                            </div>
                            <span class="badge bg-success">{{ $emp->service_years + 1 }} yrs</span>
                        </li>
                    @empty
                        <li class="list-group-item px-0 text-center text-muted">No upcoming anniversaries</li>
                    @endforelse
                </ul>
            </div>

            <div class="erp-surface hr-panel p-3">
                <div class="hr-section-head">
                    <div>
                        <div class="hr-section-kicker">Watchlist</div>
                        <div class="hr-section-title">Probation Ending Soon</div>
                    </div>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse($probationDue as $emp)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <strong>{{ $emp->full_name }}</strong>
                                <br><small class="text-muted">{{ $emp->designation?->name }}</small>
                            </div>
                            <span class="badge bg-warning text-dark">{{ $emp->probation_end_date?->format('d M') }}</span>
                        </li>
                    @empty
                        <li class="list-group-item px-0 text-center text-muted">No probations ending soon</li>
                    @endforelse
                </ul>
            </div>

            <div class="erp-surface hr-panel p-3">
                <div class="hr-section-head">
                    <div>
                        <div class="hr-section-kicker">Shape</div>
                        <div class="hr-section-title">Department Distribution</div>
                    </div>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach($departmentWise as $dept)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            {{ $dept['department'] }}
                            <span class="badge bg-secondary">{{ $dept['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection

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
