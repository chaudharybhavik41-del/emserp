@extends('layouts.erp')

@section('title', 'OT Register')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Overtime Register</h4>
            <div class="text-muted small">Pending, approved, and rejected OT records in one place.</div>
        </div>
    </div>

    @include('partials.flash')

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Pending Review</div>
                    <div class="fs-4 fw-semibold">{{ $otSummary['pending'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Approved</div>
                    <div class="fs-4 fw-semibold">{{ $otSummary['approved'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Rejected</div>
                    <div class="fs-4 fw-semibold">{{ $otSummary['rejected'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Approved Hours</div>
                    <div class="fs-4 fw-semibold">{{ number_format((float) $otSummary['approved_hours'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-light py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control form-control-sm"
                           value="{{ request('q') }}"
                           placeholder="Search by employee code or name">
                </div>
                <div class="col-md-2">
                    <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" {{ request('status', 'pending') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 text-md-end">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('hr.attendance.ot-approval', ['status' => 'all']) }}" class="btn btn-outline-secondary btn-sm">Show All</a>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Shift</th>
                            <th class="text-end">OT Hours</th>
                            <th class="text-end">Approved Hours</th>
                            <th>Status</th>
                            <th>Reviewed By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td>
                                    <div>{{ $record->attendance_date?->format('d M Y') }}</div>
                                    @if($record->ot_approved_at)
                                        <div class="small text-muted">{{ $record->ot_approved_at->format('d M Y h:i A') }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $record->employee?->full_name ?: '-' }}</div>
                                    <div class="small text-muted">{{ $record->employee?->employee_code ?: '-' }}</div>
                                </td>
                                <td>{{ $record->shift?->name ?: '-' }}</td>
                                <td class="text-end">{{ number_format((float) $record->ot_hours, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $record->ot_hours_approved, 2) }}</td>
                                <td>
                                    @php
                                        $statusTone = match ($record->ot_status) {
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'pending' => 'warning',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $statusTone }}">
                                        {{ $statusOptions[$record->ot_status] ?? ucfirst($record->ot_status) }}
                                    </span>
                                </td>
                                <td>{{ $record->otApprover?->name ?: '-' }}</td>
                                <td class="text-end">
                                    @if(in_array($record->ot_status, ['pending', 'none'], true))
                                        <form method="POST" action="{{ route('hr.attendance.approve-ot', $record) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="approved_hours" value="{{ $record->ot_hours }}">
                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('hr.attendance.reject-ot', $record) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                        </form>
                                    @else
                                        <a href="{{ route('hr.attendance.show', $record) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No overtime records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($records->hasPages())
            <div class="card-footer">
                {{ $records->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
