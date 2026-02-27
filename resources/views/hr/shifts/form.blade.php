@extends('layouts.erp')

@section('title', isset($shift) ? 'Edit Shift' : 'Add Shift')

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h4 class="mb-1">{{ isset($shift) ? 'Edit Shift' : 'Add Shift' }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hr.shifts.index') }}">Shifts</a></li>
                <li class="breadcrumb-item active">{{ isset($shift) ? 'Edit' : 'Add' }}</li>
            </ol>
        </nav>
    </div>

    <form method="POST" 
          action="{{ isset($shift) ? route('hr.shifts.update', $shift) : route('hr.shifts.store') }}">
        @csrf
        @if(isset($shift))
            @method('PUT')
        @endif

        <div class="row">
            <div class="col-lg-8">
                {{-- Basic Info --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Basic Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                       id="code" name="code" 
                                       value="{{ old('code', $shift->code ?? '') }}" 
                                       maxlength="20" required style="text-transform: uppercase;">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-8">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" 
                                       value="{{ old('name', $shift->name ?? '') }}" 
                                       maxlength="100" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control @error('start_time') is-invalid @enderror" 
                                       id="start_time" name="start_time" 
                                       value="{{ old('start_time', isset($shift) ? \Carbon\Carbon::parse($shift->start_time)->format('H:i') : '') }}" 
                                       required>
                                @error('start_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control @error('end_time') is-invalid @enderror" 
                                       id="end_time" name="end_time" 
                                       value="{{ old('end_time', isset($shift) ? \Carbon\Carbon::parse($shift->end_time)->format('H:i') : '') }}" 
                                       required>
                                @error('end_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="working_hours" class="form-label">Working Hours <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('working_hours') is-invalid @enderror" 
                                       id="working_hours" name="working_hours" 
                                       value="{{ old('working_hours', $shift->working_hours ?? 8) }}" 
                                       min="1" max="24" step="0.5" required>
                                @error('working_hours')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <label for="break_duration_minutes" class="form-label">Break Duration (min)</label>
                                <input type="number" class="form-control @error('break_duration_minutes') is-invalid @enderror" 
                                       id="break_duration_minutes" name="break_duration_minutes" 
                                       value="{{ old('break_duration_minutes', $shift->break_duration_minutes ?? 0) }}" 
                                       min="0" max="120">
                                @error('break_duration_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" id="spans_next_day" 
                                           name="spans_next_day" value="1"
                                           {{ old('spans_next_day', $shift->spans_next_day ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="spans_next_day">Spans to Next Day</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" id="is_night_shift" 
                                           name="is_night_shift" value="1"
                                           {{ old('is_night_shift', $shift->is_night_shift ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_night_shift">Night Shift</label>
                                </div>
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
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="grace_period_minutes" class="form-label">Grace Period (min)</label>
                                <input type="number" class="form-control @error('grace_period_minutes') is-invalid @enderror" 
                                       id="grace_period_minutes" name="grace_period_minutes" 
                                       value="{{ old('grace_period_minutes', $shift->grace_period_minutes ?? 0) }}" 
                                       min="0" max="60">
                                @error('grace_period_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">No late mark within this period</small>
                            </div>
                            <div class="col-md-4">
                                <label for="late_mark_after_minutes" class="form-label">Late Mark After (min)</label>
                                <input type="number" class="form-control @error('late_mark_after_minutes') is-invalid @enderror" 
                                       id="late_mark_after_minutes" name="late_mark_after_minutes" 
                                       value="{{ old('late_mark_after_minutes', $shift->late_mark_after_minutes ?? 0) }}" 
                                       min="0">
                                @error('late_mark_after_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="half_day_late_minutes" class="form-label">Half Day Late (min)</label>
                                <input type="number" class="form-control @error('half_day_late_minutes') is-invalid @enderror" 
                                       id="half_day_late_minutes" name="half_day_late_minutes" 
                                       value="{{ old('half_day_late_minutes', $shift->half_day_late_minutes ?? 0) }}" 
                                       min="0">
                                @error('half_day_late_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Mark half day if late by this much</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="absent_after_minutes" class="form-label">Absent After (min)</label>
                                <input type="number" class="form-control @error('absent_after_minutes') is-invalid @enderror" 
                                       id="absent_after_minutes" name="absent_after_minutes" 
                                       value="{{ old('absent_after_minutes', $shift->absent_after_minutes ?? 0) }}" 
                                       min="0">
                                @error('absent_after_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Mark absent if late by this much</small>
                            </div>
                            <div class="col-md-4">
                                <label for="early_going_grace_minutes" class="form-label">Early Going Grace (min)</label>
                                <input type="number" class="form-control @error('early_going_grace_minutes') is-invalid @enderror" 
                                       id="early_going_grace_minutes" name="early_going_grace_minutes" 
                                       value="{{ old('early_going_grace_minutes', $shift->early_going_grace_minutes ?? 0) }}" 
                                       min="0" max="60">
                                @error('early_going_grace_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="half_day_early_minutes" class="form-label">Half Day Early (min)</label>
                                <input type="number" class="form-control @error('half_day_early_minutes') is-invalid @enderror" 
                                       id="half_day_early_minutes" name="half_day_early_minutes" 
                                       value="{{ old('half_day_early_minutes', $shift->half_day_early_minutes ?? 0) }}" 
                                       min="0">
                                @error('half_day_early_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="auto_half_day_on_single_punch" 
                                   name="auto_half_day_on_single_punch" value="1"
                                   {{ old('auto_half_day_on_single_punch', $shift->auto_half_day_on_single_punch ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="auto_half_day_on_single_punch">
                                Auto Half Day on Single Punch
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Overtime Rules --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Overtime Rules</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="ot_applicable" 
                                   name="ot_applicable" value="1"
                                   {{ old('ot_applicable', $shift->ot_applicable ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="ot_applicable">OT Applicable</label>
                        </div>

                        <div id="ot_settings" style="{{ old('ot_applicable', $shift->ot_applicable ?? false) ? '' : 'display: none;' }}">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="ot_start_after_minutes" class="form-label">OT Starts After (min)</label>
                                    <input type="number" class="form-control @error('ot_start_after_minutes') is-invalid @enderror" 
                                           id="ot_start_after_minutes" name="ot_start_after_minutes" 
                                           value="{{ old('ot_start_after_minutes', $shift->ot_start_after_minutes ?? 0) }}" 
                                           min="0">
                                    @error('ot_start_after_minutes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3">
                                    <label for="min_ot_minutes" class="form-label">Min OT (min)</label>
                                    <input type="number" class="form-control @error('min_ot_minutes') is-invalid @enderror" 
                                           id="min_ot_minutes" name="min_ot_minutes" 
                                           value="{{ old('min_ot_minutes', $shift->min_ot_minutes ?? 30) }}" 
                                           min="0">
                                    @error('min_ot_minutes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3">
                                    <label for="max_ot_hours_per_day" class="form-label">Max OT Hours/Day</label>
                                    <input type="number" class="form-control @error('max_ot_hours_per_day') is-invalid @enderror" 
                                           id="max_ot_hours_per_day" name="max_ot_hours_per_day" 
                                           value="{{ old('max_ot_hours_per_day', $shift->max_ot_hours_per_day ?? 4) }}" 
                                           min="0" max="12" step="0.5">
                                    @error('max_ot_hours_per_day')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3">
                                    <label for="ot_rate_multiplier" class="form-label">OT Rate Multiplier</label>
                                    <input type="number" class="form-control @error('ot_rate_multiplier') is-invalid @enderror" 
                                           id="ot_rate_multiplier" name="ot_rate_multiplier" 
                                           value="{{ old('ot_rate_multiplier', $shift->ot_rate_multiplier ?? 1.5) }}" 
                                           min="1" max="5" step="0.1">
                                    @error('ot_rate_multiplier')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                {{-- Options --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Options</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="is_flexible" 
                                   name="is_flexible" value="1"
                                   {{ old('is_flexible', $shift->is_flexible ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_flexible">Flexible Timing</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="is_active" 
                                   name="is_active" value="1"
                                   {{ old('is_active', $shift->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> 
                                {{ isset($shift) ? 'Update Shift' : 'Create Shift' }}
                            </button>
                            <a href="{{ route('hr.shifts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.getElementById('ot_applicable').addEventListener('change', function() {
    document.getElementById('ot_settings').style.display = this.checked ? '' : 'none';
});
</script>
@endpush
@endsection
