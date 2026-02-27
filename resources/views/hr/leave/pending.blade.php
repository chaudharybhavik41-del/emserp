@extends('layouts.erp')

@section('title', 'Pending Leave Approvals')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Pending Leave Approvals</h1>
            <small class="text-muted">{{ $applications->total() }} applications awaiting approval</small>
        </div>
        <div class="btn-group">
            @if($applications->count() > 0)
            <button type="button" class="btn btn-success" onclick="bulkApprove()">
                <i class="bi bi-check-all me-1"></i> Approve Selected
            </button>
            @endif
            <a href="{{ route('hr.leave.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    {{-- Pending Applications --}}
    <div class="card">
        <div class="card-body p-0">
            <form id="bulkApproveForm" method="POST" action="{{ route('hr.leave.bulk-approve') }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th>Application #</th>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Period</th>
                                <th class="text-center">Days</th>
                                <th>Reason</th>
                                <th>Applied On</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($applications as $application)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="application_ids[]" 
                                               value="{{ $application->id }}" class="form-check-input app-checkbox">
                                    </td>
                                    <td>
                                        <a href="{{ route('hr.leave.applications.show', $application) }}" class="fw-bold">
                                            {{ $application->application_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $application->employee->full_name }}</div>
                                        <small class="text-muted">{{ $application->employee->employee_code }}</small>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: {{ $application->leaveType->color_code ?? '#6c757d' }}">
                                            {{ $application->leaveType->short_name ?? $application->leaveType->code }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $application->from_date->format('d M') }}
                                        @if(!$application->from_date->equalTo($application->to_date))
                                            - {{ $application->to_date->format('d M Y') }}
                                        @else
                                            , {{ $application->from_date->format('Y') }}
                                        @endif
                                        @if($application->is_half_day)
                                            <br><small class="text-muted">
                                                ({{ $application->half_day_type == 'first_half' ? '1st Half' : '2nd Half' }})
                                            </small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">{{ $application->total_days }}</span>
                                    </td>
                                    <td>
                                        <span title="{{ $application->reason }}">
                                            {{ Str::limit($application->reason, 30) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $application->created_at->format('d M Y') }}
                                        <br><small class="text-muted">{{ $application->created_at->diffForHumans() }}</small>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-success" 
                                                    onclick="approveApplication({{ $application->id }}, '{{ $application->employee->full_name }}', {{ $application->total_days }})">
                                                <i class="bi bi-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger" 
                                                    onclick="rejectApplication({{ $application->id }}, '{{ $application->employee->full_name }}')">
                                                <i class="bi bi-x"></i>
                                            </button>
                                            <a href="{{ route('hr.leave.applications.show', $application) }}" class="btn btn-outline-secondary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="bi bi-check-circle fs-1 d-block mb-2"></i>
                                            No pending leave applications
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
        @if($applications->hasPages())
        <div class="card-footer">
            {{ $applications->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Approve Modal --}}
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="approveForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Approve Leave Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Approve leave for <strong id="approveEmployeeName"></strong>?</p>
                    <p class="text-muted mb-3">Duration: <span id="approveDays"></span> day(s)</p>
                    <div class="mb-3">
                        <label class="form-label">Remarks (Optional)</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check me-1"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject Leave Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reject leave for <strong id="rejectEmployeeName"></strong>?</p>
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x me-1"></i> Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Select All
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.app-checkbox').forEach(cb => cb.checked = this.checked);
});

// Approve Single
function approveApplication(id, name, days) {
    document.getElementById('approveEmployeeName').textContent = name;
    document.getElementById('approveDays').textContent = days;
    document.getElementById('approveForm').action = `/hr/leave/applications/${id}/approve`;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

// Reject Single
function rejectApplication(id, name) {
    document.getElementById('rejectEmployeeName').textContent = name;
    document.getElementById('rejectForm').action = `/hr/leave/applications/${id}/reject`;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

// Bulk Approve
function bulkApprove() {
    const checked = document.querySelectorAll('.app-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one application');
        return;
    }
    if (confirm(`Approve ${checked.length} selected application(s)?`)) {
        document.getElementById('bulkApproveForm').submit();
    }
}
</script>
@endpush
@endsection
