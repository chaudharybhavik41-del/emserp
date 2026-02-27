@extends('layouts.erp')

@section('title', 'Daily Attendance')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Daily Attendance</h1>
            <small class="text-muted">{{ \Carbon\Carbon::parse($date)->format('l, d F Y') }}</small>
        </div>
        <div class="btn-group">
            <a href="{{ route('hr.attendance.index', ['date' => \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d')]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-chevron-left"></i>
            </a>
            <button type="button" class="btn btn-outline-secondary" id="datePickerBtn">
                <i class="bi bi-calendar me-1"></i> {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
            </button>
            <a href="{{ route('hr.attendance.index', ['date' => \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d')]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col">
            <div class="card bg-primary text-white">
                <div class="card-body text-center py-2">
                    <h3 class="mb-0">{{ $summary['total'] }}</h3>
                    <small>Total</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card bg-success text-white">
                <div class="card-body text-center py-2">
                    <h3 class="mb-0">{{ $summary['present'] }}</h3>
                    <small>Present</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card bg-danger text-white">
                <div class="card-body text-center py-2">
                    <h3 class="mb-0">{{ $summary['absent'] }}</h3>
                    <small>Absent</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center py-2">
                    <h3 class="mb-0">{{ $summary['late'] }}</h3>
                    <small>Late</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card bg-info text-white">
                <div class="card-body text-center py-2">
                    <h3 class="mb-0">{{ $summary['half_day'] }}</h3>
                    <small>Half Day</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center py-2">
                    <h3 class="mb-0">{{ $summary['on_leave'] }}</h3>
                    <small>On Leave</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-primary">
                <div class="card-body text-center py-2">
                    <h3 class="mb-0 text-primary">{{ $summary['ot_hours'] }}</h3>
                    <small>OT Hours</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('hr.attendance.index') }}" class="row g-2 align-items-center">
                <input type="hidden" name="date" value="{{ $date }}">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search employee..." value="{{ request('q') }}">
                </div>
                <div class="col-md-2">
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-search"></i></button>
                </div>
                <div class="col-auto ms-auto">
                    @can('hr.attendance.create')
                        <a href="{{ route('hr.attendance.manual-entry', ['date' => $date]) }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Manual Entry
                        </a>
                    @endcan
                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#processModal">
                        <i class="bi bi-gear me-1"></i> Process Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Attendance Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Emp. Code</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Shift</th>
                            <th>In Time</th>
                            <th>Out Time</th>
                            <th>Work Hrs</th>
                            <th>Status</th>
                            <th>OT</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $attendance)
                            <tr>
                                <td>
                                    <a href="{{ route('hr.employees.show', $attendance->employee) }}" class="text-decoration-none">
                                        {{ $attendance->employee->employee_code }}
                                    </a>
                                </td>
                                <td>{{ $attendance->employee->full_name }}</td>
                                <td>{{ $attendance->employee->department?->name ?? '-' }}</td>
                                <td>
                                    <small>{{ $attendance->shift?->short_name ?? '-' }}</small>
                                    @if($attendance->shift)
                                        <br><small class="text-muted">{{ \Carbon\Carbon::parse($attendance->shift->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($attendance->shift->end_time)->format('h:i A') }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($attendance->first_in)
                                        {{ \Carbon\Carbon::parse($attendance->first_in)->format('h:i A') }}
                                        @if($attendance->late_minutes > 0)
                                            <br><small class="text-danger">Late: {{ $attendance->late_minutes }} min</small>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($attendance->last_out)
                                        {{ \Carbon\Carbon::parse($attendance->last_out)->format('h:i A') }}
                                        @if($attendance->early_out_minutes > 0)
                                            <br><small class="text-warning">Early: {{ $attendance->early_out_minutes }} min</small>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ number_format($attendance->working_hours, 2) }}</td>
                                <td>
                                    <span class="badge bg-{{ $attendance->status->color() }}">
                                        {{ $attendance->status->shortCode() }}
                                    </span>
                                    @if($attendance->is_regularized)
                                        <i class="bi bi-patch-check text-info" title="Regularized"></i>
                                    @endif
                                </td>
                                <td>
                                    @if($attendance->overtime_minutes > 0)
                                        <span class="text-primary fw-medium">{{ number_format($attendance->overtime_minutes / 60, 1) }} hrs</span>
                                        @if($attendance->ot_status === 'approved')
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        @elseif($attendance->ot_status === 'pending')
                                            <i class="bi bi-clock text-warning"></i>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewModal{{ $attendance->id }}" title="View">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        @if($attendance->status->value !== 'weekly_off' && $attendance->status->value !== 'holiday')
                                            @can('hr.attendance.update')
                                                <a href="{{ route('hr.attendance.manual-entry', ['employee_id' => $attendance->employee_id, 'date' => $date]) }}" class="btn btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            {{-- View Modal --}}
                            <div class="modal fade" id="viewModal{{ $attendance->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Attendance Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Employee</small>
                                                    <p class="mb-0 fw-medium">{{ $attendance->employee->full_name }}</p>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Date</small>
                                                    <p class="mb-0">{{ \Carbon\Carbon::parse($attendance->attendance_date)->format('d M Y') }}</p>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Shift</small>
                                                    <p class="mb-0">{{ $attendance->shift?->name ?? 'N/A' }}</p>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Status</small>
                                                    <p class="mb-0"><span class="badge bg-{{ $attendance->status->color() }}">{{ $attendance->status->label() }}</span></p>
                                                </div>
                                            </div>
                                            <hr>
                                            <div class="row mb-3">
                                                <div class="col-4">
                                                    <small class="text-muted">First In</small>
                                                    <p class="mb-0">{{ $attendance->first_in ? \Carbon\Carbon::parse($attendance->first_in)->format('h:i A') : '-' }}</p>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted">Last Out</small>
                                                    <p class="mb-0">{{ $attendance->last_out ? \Carbon\Carbon::parse($attendance->last_out)->format('h:i A') : '-' }}</p>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted">Working Hrs</small>
                                                    <p class="mb-0">{{ number_format($attendance->working_hours, 2) }}</p>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-4">
                                                    <small class="text-muted">Late Minutes</small>
                                                    <p class="mb-0 {{ $attendance->late_minutes > 0 ? 'text-danger' : '' }}">{{ $attendance->late_minutes }}</p>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted">Early Out</small>
                                                    <p class="mb-0 {{ $attendance->early_out_minutes > 0 ? 'text-warning' : '' }}">{{ $attendance->early_out_minutes }}</p>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted">OT Minutes</small>
                                                    <p class="mb-0 {{ $attendance->overtime_minutes > 0 ? 'text-primary' : '' }}">{{ $attendance->overtime_minutes }}</p>
                                                </div>
                                            </div>
                                            @if($attendance->punches && $attendance->punches->count() > 0)
                                                <hr>
                                                <h6>Punch Details</h6>
                                                <ul class="list-group list-group-flush">
                                                    @foreach($attendance->punches as $punch)
                                                        <li class="list-group-item d-flex justify-content-between px-0">
                                                            <span>{{ \Carbon\Carbon::parse($punch->punch_time)->format('h:i:s A') }}</span>
                                                            <span class="badge bg-{{ $punch->punch_type === 'in' ? 'success' : 'danger' }}">{{ strtoupper($punch->punch_type) }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            @if($attendance->remarks)
                                                <hr>
                                                <small class="text-muted">Remarks</small>
                                                <p class="mb-0">{{ $attendance->remarks }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                    No attendance records found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($attendances->hasPages())
            <div class="card-footer">
                {{ $attendances->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Process Attendance Modal --}}
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('hr.attendance.process') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Process Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="{{ $date }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="process_absent" id="processAbsent" value="1" checked>
                        <label class="form-check-label" for="processAbsent">Mark absent for employees without punch</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="recalculate" id="recalculate" value="1">
                        <label class="form-check-label" for="recalculate">Recalculate existing attendance</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Date Picker Modal --}}
<div class="modal fade" id="datePickerModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Date</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('hr.attendance.index') }}" method="GET">
                    <input type="date" name="date" class="form-control" value="{{ $date }}">
                    <button type="submit" class="btn btn-primary w-100 mt-3">Go</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('datePickerBtn').addEventListener('click', function() {
    new bootstrap.Modal(document.getElementById('datePickerModal')).show();
});
</script>
@endpush
@endsection
