@extends('layouts.erp')

@section('title', isset($policy) ? 'Edit Attendance Policy' : 'Add Attendance Policy')

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h4 class="mb-1">{{ isset($policy) ? 'Edit Attendance Policy' : 'Add Attendance Policy' }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hr.attendance-policies.index') }}">Attendance Policies</a></li>
                <li class="breadcrumb-item active">{{ isset($policy) ? 'Edit' : 'Add' }}</li>
            </ol>
        </nav>
    </div>

    <form method="POST" 
          action="{{ isset($policy) ? route('hr.attendance-policies.update', $policy) : route('hr.attendance-policies.store') }}">
        @csrf
        @if(isset($policy))
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
                                       value="{{ old('code', $policy->code ?? '') }}" 
                                       maxlength="20" required style="text-transform: uppercase;">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-8">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" 
                                       value="{{ old('name', $policy->name ?? '') }}" 
                                       maxlength="100" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="effective_from" class="form-label">Effective From</label>
                                <input type="date" class="form-control @error('effective_from') is-invalid @enderror"
                                       id="effective_from" name="effective_from"
                                       value="{{ old('effective_from', optional($policy->effective_from ?? null)?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                                @error('effective_from')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" 
                                      rows="2">{{ old('description', $policy->description ?? '') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Late Coming Rules --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Late Coming Rules</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="grace_period_minutes" class="form-label">Grace Period (min)</label>
                                <input type="number" class="form-control @error('grace_period_minutes') is-invalid @enderror" 
                                       id="grace_period_minutes" name="grace_period_minutes" 
                                       value="{{ old('grace_period_minutes', $policy->grace_period_minutes ?? 10) }}" 
                                       min="0" max="60">
                                @error('grace_period_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="late_deduction_per_instance" class="form-label">Late Deduction Per Instance (min)</label>
                                <input type="number" class="form-control @error('late_deduction_per_instance') is-invalid @enderror" 
                                       id="late_deduction_per_instance" name="late_deduction_per_instance" 
                                       value="{{ old('late_deduction_per_instance', $policy->late_deduction_per_instance ?? 0) }}" 
                                       min="0" max="480">
                                @error('late_deduction_per_instance')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="max_late_instances_per_month" class="form-label">Max Late Instances / Month</label>
                                <input type="number" class="form-control @error('max_late_instances_per_month') is-invalid @enderror" 
                                       id="max_late_instances_per_month" name="max_late_instances_per_month" 
                                       value="{{ old('max_late_instances_per_month', $policy->max_late_instances_per_month ?? 3) }}" 
                                       min="0" max="31">
                                @error('max_late_instances_per_month')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <label for="late_deduction_from_leave" class="form-label">Late Deduction From Leave (days)</label>
                                <input type="number" class="form-control @error('late_deduction_from_leave') is-invalid @enderror" 
                                       id="late_deduction_from_leave" name="late_deduction_from_leave" 
                                       value="{{ old('late_deduction_from_leave', $policy->late_deduction_from_leave ?? 0) }}" 
                                       min="0" max="10" step="0.5">
                                @error('late_deduction_from_leave')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="half_day_after_late_minutes" class="form-label">Half Day After Late (min)</label>
                                <input type="number" class="form-control @error('half_day_after_late_minutes') is-invalid @enderror" 
                                       id="half_day_after_late_minutes" name="half_day_after_late_minutes" 
                                       value="{{ old('half_day_after_late_minutes', $policy->half_day_after_late_minutes ?? 120) }}" 
                                       min="0" max="480">
                                @error('half_day_after_late_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="absent_after_late_minutes" class="form-label">Absent After Late (min)</label>
                                <input type="number" class="form-control @error('absent_after_late_minutes') is-invalid @enderror" 
                                       id="absent_after_late_minutes" name="absent_after_late_minutes" 
                                       value="{{ old('absent_after_late_minutes', $policy->absent_after_late_minutes ?? 240) }}" 
                                       min="0" max="720">
                                @error('absent_after_late_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Early Going Rules --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Half Day & No Punch Rules</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="half_day_after_early_minutes" class="form-label">Half Day After Early (min)</label>
                                <input type="number" class="form-control @error('half_day_after_early_minutes') is-invalid @enderror" 
                                       id="half_day_after_early_minutes" name="half_day_after_early_minutes" 
                                       value="{{ old('half_day_after_early_minutes', $policy->half_day_after_early_minutes ?? 120) }}" 
                                       min="0" max="480">
                                @error('half_day_after_early_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="mark_absent_on_no_punch" class="form-label d-block">No Punch Handling</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" class="form-check-input" id="mark_absent_on_no_punch" 
                                           name="mark_absent_on_no_punch" value="1"
                                           {{ old('mark_absent_on_no_punch', $policy->mark_absent_on_no_punch ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="mark_absent_on_no_punch">Mark absent on no punch</label>
                                </div>
                                @error('mark_absent_on_no_punch')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Working Hours --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Working Hours</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="min_hours_for_full_day" class="form-label">Min Hours Full Day</label>
                                <input type="number" class="form-control @error('min_hours_for_full_day') is-invalid @enderror" 
                                       id="min_hours_for_full_day" name="min_hours_for_full_day" 
                                       value="{{ old('min_hours_for_full_day', $policy->min_hours_for_full_day ?? 8) }}" 
                                       min="1" max="24" step="0.5">
                                @error('min_hours_for_full_day')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="min_hours_for_half_day" class="form-label">Min Hours Half Day</label>
                                <input type="number" class="form-control @error('min_hours_for_half_day') is-invalid @enderror" 
                                       id="min_hours_for_half_day" name="min_hours_for_half_day" 
                                       value="{{ old('min_hours_for_half_day', $policy->min_hours_for_half_day ?? 4) }}" 
                                       min="0.5" max="12" step="0.5">
                                @error('min_hours_for_half_day')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="allow_week_off_work" class="form-label d-block">Week Off / Holiday Work</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" class="form-check-input" id="allow_week_off_work" 
                                           name="allow_week_off_work" value="1"
                                           {{ old('allow_week_off_work', $policy->allow_week_off_work ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="allow_week_off_work">Allow work on week off / holiday</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Overtime --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Overtime Rules</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="ot_allowed" 
                                   name="ot_allowed" value="1"
                                   {{ old('ot_allowed', $policy->ot_allowed ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="ot_allowed">Overtime Allowed</label>
                        </div>

                        <div id="ot_fields" style="{{ old('ot_allowed', $policy->ot_allowed ?? true) ? '' : 'display: none;' }}">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="ot_rate_multiplier" class="form-label">Regular OT Multiplier</label>
                                    <input type="number" class="form-control @error('ot_rate_multiplier') is-invalid @enderror" 
                                           id="ot_rate_multiplier" name="ot_rate_multiplier" 
                                           value="{{ old('ot_rate_multiplier', $policy->ot_rate_multiplier ?? 1.5) }}" 
                                           min="0" max="10" step="0.1">
                                    @error('ot_rate_multiplier')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="ot_calculation_basis" class="form-label">OT Calculation Basis</label>
                                    <select class="form-select @error('ot_calculation_basis') is-invalid @enderror"
                                            id="ot_calculation_basis" name="ot_calculation_basis">
                                        <option value="basic" {{ old('ot_calculation_basis', $policy->ot_calculation_basis ?? 'basic') === 'basic' ? 'selected' : '' }}>Basic Salary</option>
                                        <option value="gross" {{ old('ot_calculation_basis', $policy->ot_calculation_basis ?? 'basic') === 'gross' ? 'selected' : '' }}>Gross Salary</option>
                                        <option value="fixed" {{ old('ot_calculation_basis', $policy->ot_calculation_basis ?? 'basic') === 'fixed' ? 'selected' : '' }}>Fixed Hourly Rate</option>
                                    </select>
                                    @error('ot_calculation_basis')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="ot_min_minutes" class="form-label">Min OT Minutes</label>
                                    <input type="number" class="form-control @error('ot_min_minutes') is-invalid @enderror" 
                                           id="ot_min_minutes" name="ot_min_minutes" 
                                           value="{{ old('ot_min_minutes', $policy->ot_min_minutes ?? 30) }}" 
                                           min="0">
                                    @error('ot_min_minutes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="ot_max_hours_per_day" class="form-label">Max OT Hours / Day</label>
                                    <input type="number" class="form-control @error('ot_max_hours_per_day') is-invalid @enderror" 
                                           id="ot_max_hours_per_day" name="ot_max_hours_per_day" 
                                           value="{{ old('ot_max_hours_per_day', $policy->ot_max_hours_per_day ?? 4) }}" 
                                           min="0" max="24">
                                    @error('ot_max_hours_per_day')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="ot_max_hours_per_month" class="form-label">Max OT Hours / Month</label>
                                    <input type="number" class="form-control @error('ot_max_hours_per_month') is-invalid @enderror" 
                                           id="ot_max_hours_per_month" name="ot_max_hours_per_month" 
                                           value="{{ old('ot_max_hours_per_month', $policy->ot_max_hours_per_month ?? 50) }}" 
                                           min="0" max="300">
                                    @error('ot_max_hours_per_month')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="week_off_ot_multiplier" class="form-label">Week Off OT Multiplier</label>
                                    <input type="number" class="form-control @error('week_off_ot_multiplier') is-invalid @enderror" 
                                           id="week_off_ot_multiplier" name="week_off_ot_multiplier" 
                                           value="{{ old('week_off_ot_multiplier', $policy->week_off_ot_multiplier ?? 2) }}" 
                                           min="0" max="10" step="0.1">
                                    @error('week_off_ot_multiplier')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="holiday_ot_multiplier" class="form-label">Holiday OT Multiplier</label>
                                    <input type="number" class="form-control @error('holiday_ot_multiplier') is-invalid @enderror" 
                                           id="holiday_ot_multiplier" name="holiday_ot_multiplier" 
                                           value="{{ old('holiday_ot_multiplier', $policy->holiday_ot_multiplier ?? 2) }}" 
                                           min="0" max="10" step="0.1">
                                    @error('holiday_ot_multiplier')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input" id="ot_needs_approval" 
                                               name="ot_needs_approval" value="1"
                                               {{ old('ot_needs_approval', $policy->ot_needs_approval ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="ot_needs_approval">OT Needs Approval</label>
                                    </div>
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
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_default" 
                                   name="is_default" value="1"
                                   {{ old('is_default', $policy->is_default ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_default">Default Policy</label>
                        </div>
                        <div class="form-check mt-2">
                            <input type="checkbox" class="form-check-input" id="is_active" 
                                   name="is_active" value="1"
                                   {{ old('is_active', $policy->is_active ?? true) ? 'checked' : '' }}>
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
                                {{ isset($policy) ? 'Update Policy' : 'Create Policy' }}
                            </button>
                            <a href="{{ route('hr.attendance-policies.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.getElementById('ot_allowed').addEventListener('change', function() {
    document.getElementById('ot_fields').style.display = this.checked ? '' : 'none';
});
</script>
@endpush
@endsection
