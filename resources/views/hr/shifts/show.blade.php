@extends('layouts.erp')

@section('title', 'Shift Details')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">{{ $shift->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('hr.shifts.index') }}">Shifts</a></li>
                    <li class="breadcrumb-item active">{{ $shift->code }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('hr.shifts.edit', $shift) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
            <a href="{{ route('hr.shifts.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            {{-- Basic Info --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Basic Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;">Code:</td>
                                    <td><code>{{ $shift->code }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Name:</td>
                                    <td>{{ $shift->name }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Timing:</td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($shift->start_time)->format('h:i A') }} - 
                                        {{ \Carbon\Carbon::parse($shift->end_time)->format('h:i A') }}
                                        @if($shift->spans_next_day)
                                            <span class="badge bg-info ms-1">+1 Day</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Working Hours:</td>
                                    <td>{{ $shift->working_hours }} hours</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;">Break Duration:</td>
                                    <td>{{ $shift->break_duration_minutes ?? 0 }} minutes</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Night Shift:</td>
                                    <td>{!! $shift->is_night_shift ? '<span class="badge bg-dark">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Flexible:</td>
                                    <td>{!! $shift->is_flexible ? '<span class="badge bg-info">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Status:</td>
                                    <td>{!! $shift->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' !!}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Late/Early Rules --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Late Coming & Early Going Rules</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted small mb-2">Late Coming</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Grace Period:</td>
                                    <td>{{ $shift->grace_period_minutes ?? 0 }} minutes</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Late Mark After:</td>
                                    <td>{{ $shift->late_mark_after_minutes ?? 0 }} minutes</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Half Day After:</td>
                                    <td>{{ $shift->half_day_late_minutes ?? 0 }} minutes</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Absent After:</td>
                                    <td>{{ $shift->absent_after_minutes ?? 0 }} minutes</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted small mb-2">Early Going</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Grace Period:</td>
                                    <td>{{ $shift->early_going_grace_minutes ?? 0 }} minutes</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Half Day After:</td>
                                    <td>{{ $shift->half_day_early_minutes ?? 0 }} minutes</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Single Punch:</td>
                                    <td>{!! $shift->auto_half_day_on_single_punch ? '<span class="badge bg-warning">Half Day</span>' : '<span class="badge bg-secondary">Normal</span>' !!}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Overtime Rules --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Overtime Rules</h6>
                </div>
                <div class="card-body">
                    @if($shift->ot_applicable)
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted">OT Starts After:</td>
                                        <td>{{ $shift->ot_start_after_minutes ?? 0 }} minutes</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Minimum OT:</td>
                                        <td>{{ $shift->min_ot_minutes ?? 0 }} minutes</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted">Max OT/Day:</td>
                                        <td>{{ $shift->max_ot_hours_per_day ?? 0 }} hours</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">OT Multiplier:</td>
                                        <td>{{ $shift->ot_rate_multiplier ?? 1 }}x</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    @else
                        <p class="text-muted mb-0">Overtime is not applicable for this shift.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Stats --}}
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Assigned Employees:</span>
                        <span class="badge bg-primary fs-6">{{ $shift->employees_count }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
