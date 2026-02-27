@extends('layouts.erp')

@section('title', 'Leave Application Details')

@section('content')
@php
    $statusEnum = $leaveApplication->status instanceof \App\Enums\Hr\LeaveStatus ? $leaveApplication->status : null;
    $statusValue = $statusEnum?->value ?? (is_string($leaveApplication->status) ? $leaveApplication->status : '');
    $statusLabel = $statusEnum?->label() ?? ucfirst(str_replace('_', ' ', $statusValue));
    $statusColor = $statusEnum?->color() ?? 'secondary';
@endphp
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Leave Application</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('hr.leave-applications.index') }}">Leave Applications</a></li>
                    <li class="breadcrumb-item active">View</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.leave-applications.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    @include('partials.flash')

    <div class="row">
        <div class="col-lg-8">
            {{-- Main Info --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Application Details</h6>
                    <span class="badge bg-{{ $statusColor }} {{ $statusValue === 'pending' ? 'text-dark' : '' }}">{{ $statusLabel }}</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;">Employee:</td>
                                    <td>
                                        <strong>{{ $leaveApplication->employee->full_name }}</strong>
                                        <br><small class="text-muted">{{ $leaveApplication->employee->employee_code }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Leave Type:</td>
                                    <td>
                                        @if($leaveApplication->leaveType->color)
                                            <span class="badge" style="background-color: {{ $leaveApplication->leaveType->color }};">
                                                {{ $leaveApplication->leaveType->name }}
                                            </span>
                                        @else
                                            {{ $leaveApplication->leaveType->name }}
                                        @endif
                                        @if(!$leaveApplication->leaveType->is_paid)
                                            <small class="text-muted">(Unpaid)</small>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">From Date:</td>
                                    <td>
                                        {{ $leaveApplication->from_date->format('d M Y') }}
                                        ({{ ucfirst(str_replace('_', ' ', $leaveApplication->from_session)) }})
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">To Date:</td>
                                    <td>
                                        {{ $leaveApplication->to_date->format('d M Y') }}
                                        ({{ ucfirst(str_replace('_', ' ', $leaveApplication->to_session)) }})
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;">Total Days:</td>
                                    <td><span class="badge bg-primary fs-6">{{ $leaveApplication->total_days }}</span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Applied On:</td>
                                    <td>{{ $leaveApplication->applied_on ? $leaveApplication->applied_on->format('d M Y H:i') : '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Contact:</td>
                                    <td>{{ $leaveApplication->contact_during_leave ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Handover To:</td>
                                    <td>{{ $leaveApplication->handoverEmployee->full_name ?? '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Reason --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Reason</h6>
                </div>
                <div class="card-body">
                    <p class="mb-0">{{ $leaveApplication->reason }}</p>
                </div>
            </div>

            {{-- Approval Details --}}
            @if(!in_array($statusValue, ['pending', 'draft'], true))
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Approval Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width: 30%;">
                                    {{ $statusValue === 'approved' ? 'Approved By:' : ($statusValue === 'rejected' ? 'Rejected By:' : 'Action By:') }}
                                </td>
                                <td>{{ $leaveApplication->approvedBy->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Date:</td>
                                <td>{{ $leaveApplication->approved_on ? $leaveApplication->approved_on->format('d M Y H:i') : '-' }}</td>
                            </tr>
                            @if($leaveApplication->approver_remarks)
                                <tr>
                                    <td class="text-muted">Remarks:</td>
                                    <td>{{ $leaveApplication->approver_remarks }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            {{-- Actions --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Actions</h6>
                </div>
                <div class="card-body">
                    @if($statusValue === 'pending')
                        <form method="POST" action="{{ route('hr.leave-applications.approve', $leaveApplication) }}" class="mb-3">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">Remarks (Optional)</label>
                                <textarea name="approver_remarks" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('Approve this leave application?')">
                                <i class="bi bi-check-lg me-1"></i> Approve
                            </button>
                        </form>

                        <form method="POST" action="{{ route('hr.leave-applications.reject', $leaveApplication) }}" class="mb-3">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">Rejection Reason <span class="text-danger">*</span></label>
                                <textarea name="approver_remarks" class="form-control form-control-sm" rows="2" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger btn-sm w-100" onclick="return confirm('Reject this leave application?')">
                                <i class="bi bi-x-lg me-1"></i> Reject
                            </button>
                        </form>

                        <hr>

                        <a href="{{ route('hr.leave-applications.edit', $leaveApplication) }}" class="btn btn-outline-primary btn-sm w-100 mb-2">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </a>
                    @endif

                    @if(in_array($statusValue, ['pending', 'approved'], true))
                        <form method="POST" action="{{ route('hr.leave-applications.cancel', $leaveApplication) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="return confirm('Cancel this leave application?')">
                                <i class="bi bi-x-circle me-1"></i> Cancel Application
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Timeline --}}
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Timeline</h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <div class="small text-muted">Applied</div>
                                <div>{{ $leaveApplication->applied_on ? $leaveApplication->applied_on->format('d M Y H:i') : $leaveApplication->created_at->format('d M Y H:i') }}</div>
                            </div>
                        </div>

                        @if($leaveApplication->approved_on)
                            <div class="timeline-item">
                                <div class="timeline-marker {{ $statusValue === 'approved' ? 'bg-success' : ($statusValue === 'rejected' ? 'bg-danger' : 'bg-secondary') }}"></div>
                                <div class="timeline-content">
                                    <div class="small text-muted">{{ $statusLabel }}</div>
                                    <div>{{ $leaveApplication->approved_on->format('d M Y H:i') }}</div>
                                </div>
                            </div>
                        @endif

                        @if($leaveApplication->cancelled_on)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-secondary"></div>
                                <div class="timeline-content">
                                    <div class="small text-muted">Cancelled</div>
                                    <div>{{ $leaveApplication->cancelled_on->format('d M Y H:i') }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline-item {
    position: relative;
    padding-bottom: 15px;
}
.timeline-item:not(:last-child):before {
    content: '';
    position: absolute;
    left: -24px;
    top: 10px;
    height: calc(100% - 10px);
    width: 2px;
    background: #dee2e6;
}
.timeline-marker {
    position: absolute;
    left: -30px;
    top: 2px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}
</style>
@endsection
