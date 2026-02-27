@extends('layouts.erp')

@section('title', 'Leave Applications')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Leave Applications</h1>
            <small class="text-muted">Manage employee leave requests</small>
        </div>
        <div class="btn-group">
            @if(Route::has('hr.leave.calendar'))
            <a href="{{ route('hr.leave.calendar') }}" class="btn btn-outline-secondary">
                <i class="bi bi-calendar3 me-1"></i> Calendar
            </a>
            @endif
            @can('hr.leave.create')
                @if(Route::has('hr.leave.create'))
                <a href="{{ route('hr.leave.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> New Application
                </a>
                @endif
            @endcan
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-1">Pending Approval</h6>
                            <h3 class="card-title mb-0">{{ $stats['pending'] }}</h3>
                        </div>
                        <div class="fs-1 opacity-50"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                    @if($stats['pending'] > 0 && Route::has('hr.leave.pending'))
                        <a href="{{ route('hr.leave.pending') }}" class="btn btn-sm btn-dark mt-2">View Pending</a>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Approved Today</h6>
                            <h3 class="card-title mb-0">{{ $stats['approved_today'] }}</h3>
                        </div>
                        <div class="fs-1 opacity-50"><i class="bi bi-check-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">On Leave Today</h6>
                            <h3 class="card-title mb-0">{{ $stats['on_leave_today'] }}</h3>
                        </div>
                        <div class="fs-1 opacity-50"><i class="bi bi-person-dash"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('hr.leave.index') }}" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search employee..." value="{{ request('q') }}">
                </div>
                <div class="col-md-2">
                    <select name="leave_type_id" class="form-select form-select-sm">
                        <option value="">All Leave Types</option>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}" {{ request('leave_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
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
                <div class="col-md-2">
                    <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}" placeholder="From Date">
                </div>
                <div class="col-md-2">
                    <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}" placeholder="To Date">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-search"></i></button>
                    <a href="{{ route('hr.leave.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Applications Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Application #</th>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Period</th>
                            <th class="text-center">Days</th>
                            <th>Reason</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($applications as $application)
                            <tr>
                                <td>
                                    <a href="{{ route('hr.leave.show', $application) }}" class="fw-medium text-decoration-none">
                                        {{ $application->application_number }}
                                    </a>
                                    @php
                                        $appliedAt = $application->applied_on ?? ($application->applied_at ?? null);
                                        if (!$appliedAt) {
                                            $appliedAt = $application->created_at ?? null;
                                        }
                                        if (is_string($appliedAt)) {
                                            try { $appliedAt = \Carbon\Carbon::parse($appliedAt); } catch (\Throwable $e) { $appliedAt = $application->created_at ?? null; }
                                        }
                                    @endphp
                                    <br><small class="text-muted">{{ $appliedAt?->format('d M Y') }}</small>
                                </td>
                                <td>
                                    <a href="{{ route('hr.employees.show', $application->employee) }}" class="text-decoration-none">
                                        {{ $application->employee->full_name }}
                                    </a>
                                    <br><small class="text-muted">{{ $application->employee->employee_code }}</small>
                                </td>
                                <td>
                                    <span class="badge" style="background-color: {{ $application->leaveType->color_code ?? '#6c757d' }}">
                                        {{ $application->leaveType->short_name }}
                                    </span>
                                    {{ $application->leaveType->name }}
                                </td>
                                <td>
                                    {{ $application->from_date->format('d M') }}
                                    @if(!$application->from_date->equalTo($application->to_date))
                                        - {{ $application->to_date->format('d M Y') }}
                                    @else
                                        , {{ $application->from_date->format('Y') }}
                                    @endif
                                    @if($application->from_session !== 'full_day' || $application->to_session !== 'full_day')
                                        <br><small class="text-muted">
                                            @if($application->from_date->equalTo($application->to_date))
                                                {{ ucfirst(str_replace('_', ' ', $application->from_session)) }}
                                            @else
                                                {{ ucfirst(str_replace('_', ' ', $application->from_session)) }} - {{ ucfirst(str_replace('_', ' ', $application->to_session)) }}
                                            @endif
                                        </small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary">{{ $application->total_days }}</span>
                                </td>
                                <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 150px;" title="{{ $application->reason }}">
                                        {{ $application->reason }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $application->status->color() }}">
                                        {{ $application->status->label() }}
                                    </span>
                                    @if($application->approved_by)
                                        <br><small class="text-muted">by {{ $application->approvedBy?->name }}</small>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('hr.leave.show', $application) }}" class="btn btn-outline-primary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if($application->status->value === 'pending')
                                            @can('hr.leave.approve')
                                                <button type="button" class="btn btn-outline-success" title="Approve" 
                                                        data-bs-toggle="modal" data-bs-target="#approveModal{{ $application->id }}">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" title="Reject"
                                                        data-bs-toggle="modal" data-bs-target="#rejectModal{{ $application->id }}">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            @endcan
                                        @endif
                                        @if($application->status->canCancel())
                                            <button type="button" class="btn btn-outline-warning" title="Cancel"
                                                    data-bs-toggle="modal" data-bs-target="#cancelModal{{ $application->id }}">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            {{-- Approve Modal --}}
                            @if($application->status->value === 'pending')
                            <div class="modal fade" id="approveModal{{ $application->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('hr.leave.approve', $application) }}" method="POST">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Approve Leave - {{ $application->application_number }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>{{ $application->employee->full_name }}</strong> has applied for <strong>{{ $application->total_days }} day(s)</strong> of {{ $application->leaveType->name }}.</p>
                                                <p>Period: {{ $application->from_date->format('d M Y') }} - {{ $application->to_date->format('d M Y') }}</p>
                                                <div class="mb-3">
                                                    <label class="form-label">Remarks (Optional)</label>
                                                    <textarea name="remarks" class="form-control" rows="2"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success">Approve</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            {{-- Reject Modal --}}
                            <div class="modal fade" id="rejectModal{{ $application->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('hr.leave.reject', $application) }}" method="POST">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Leave - {{ $application->application_number }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>{{ $application->employee->full_name }}</strong> has applied for <strong>{{ $application->total_days }} day(s)</strong> of {{ $application->leaveType->name }}.</p>
                                                <div class="mb-3">
                                                    <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                                                    <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Reject</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endif

                            {{-- Cancel Modal --}}
                            @if($application->status->canCancel())
                            <div class="modal fade" id="cancelModal{{ $application->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('hr.leave.cancel', $application) }}" method="POST">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Cancel Leave - {{ $application->application_number }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-warning">
                                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                                    @if($application->status->value === 'approved')
                                                        This leave was already approved. Cancelling will restore the leave balance.
                                                    @else
                                                        Are you sure you want to cancel this leave application?
                                                    @endif
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Reason for Cancellation <span class="text-danger">*</span></label>
                                                    <textarea name="cancellation_reason" class="form-control" rows="3" required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-warning">Cancel Leave</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endif
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                    No leave applications found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($applications->hasPages())
            <div class="card-footer">
                {{ $applications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
