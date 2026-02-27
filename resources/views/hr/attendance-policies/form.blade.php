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
                                <label for="late_coming_grace_minutes" class="form-label">Grace Period (min)</label>
                                <input type="number" class="form-control @error('late_coming_grace_minutes') is-invalid @enderror" 
                                       id="late_coming_grace_minutes" name="late_coming_grace_minutes" 
                                       value="{{ old('late_coming_grace_minutes', $policy->late_coming_grace_minutes ?? 0) }}" 
                                       min="0" max="60">
                                @error('late_coming_grace_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="late_coming_deduction_type" class="form-label">Deduction Type</label>
                                <select class="form-select @error('late_coming_deduction_type') is-invalid @enderror" 
                                        id="late_coming_deduction_type" name="late_coming_deduction_type">
                                    <option value="none" {{ old('late_coming_deduction_type', $policy->late_coming_deduction_type ?? '') === 'none' ? 'selected' : '' }}>None</option>
                                    <option value="per_instance" {{ old('late_coming_deduction_type', $policy->late_coming_deduction_type ?? '') === 'per_instance' ? 'selected' : '' }}>Per Instance</option>
                                    <option value="half_day" {{ old('late_coming_deduction_type', $policy->late_coming_deduction_type ?? '') === 'half_day' ? 'selected' : '' }}>Half Day</option>
                                    <option value="full_day" {{ old('late_coming_deduction_type', $policy->late_coming_deduction_type ?? '') === 'full_day' ? 'selected' : '' }}>Full Day</option>
                                </select>
                                @error('late_coming_deduction_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <label for="late_instances_for_half_day" class="form-label">Late Instances for Half Day</label>
                                <input type="number" class="form-control @error('late_instances_for_half_day') is-invalid @enderror" 
                                       id="late_instances_for_half_day" name="late_instances_for_half_day" 
                                       value="{{ old('late_instances_for_half_day', $policy->late_instances_for_half_day ?? 3) }}" 
                                       min="1" max="10">
                                @error('late_instances_for_half_day')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="late_instances_for_full_day" class="form-label">Late Instances for Full Day</label>
                                <input type="number" class="form-control @error('late_instances_for_full_day') is-invalid @enderror" 
                                       id="late_instances_for_full_day" name="late_instances_for_full_day" 
                                       value="{{ old('late_instances_for_full_day', $policy->late_instances_for_full_day ?? 6) }}" 
                                       min="1" max="20">
                                @error('late_instances_for_full_day')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Early Going Rules --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Early Going Rules</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="early_going_grace_minutes" class="form-label">Grace Period (min)</label>
                                <input type="number" class="form-control @error('early_going_grace_minutes') is-invalid @enderror" 
                                       id="early_going_grace_minutes" name="early_going_grace_minutes" 
                                       value="{{ old('early_going_grace_minutes', $policy->early_going_grace_minutes ?? 0) }}" 
                                       min="0" max="60">
                                @error('early_going_grace_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="early_going_deduction_type" class="form-label">Deduction Type</label>
                                <select class="form-select @error('early_going_deduction_type') is-invalid @enderror" 
                                        id="early_going_deduction_type" name="early_going_deduction_type">
                                    <option value="none" {{ old('early_going_deduction_type', $policy->early_going_deduction_type ?? '') === 'none' ? 'selected' : '' }}>None</option>
                                    <option value="per_instance" {{ old('early_going_deduction_type', $policy->early_going_deduction_type ?? '') === 'per_instance' ? 'selected' : '' }}>Per Instance</option>
                                    <option value="half_day" {{ old('early_going_deduction_type', $policy->early_going_deduction_type ?? '') === 'half_day' ? 'selected' : '' }}>Half Day</option>
                                    <option value="full_day" {{ old('early_going_deduction_type', $policy->early_going_deduction_type ?? '') === 'full_day' ? 'selected' : '' }}>Full Day</option>
                                </select>
                                @error('early_going_deduction_type')
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
                                <label for="min_working_hours_full_day" class="form-label">Min Hours Full Day</label>
                                <input type="number" class="form-control @error('min_working_hours_full_day') is-invalid @enderror" 
                                       id="min_working_hours_full_day" name="min_working_hours_full_day" 
                                       value="{{ old('min_working_hours_full_day', $policy->min_working_hours_full_day ?? 8) }}" 
                                       min="1" max="24" step="0.5">
                                @error('min_working_hours_full_day')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="min_working_hours_half_day" class="form-label">Min Hours Half Day</label>
                                <input type="number" class="form-control @error('min_working_hours_half_day') is-invalid @enderror" 
                                       id="min_working_hours_half_day" name="min_working_hours_half_day" 
                                       value="{{ old('min_working_hours_half_day', $policy->min_working_hours_half_day ?? 4) }}" 
                                       min="0.5" max="12" step="0.5">
                                @error('min_working_hours_half_day')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="weekend_policy" class="form-label">Weekend Policy</label>
                                <select class="form-select @error('weekend_policy') is-invalid @enderror" 
                                        id="weekend_policy" name="weekend_policy">
                                    <option value="">-- Select --</option>
                                    <option value="no_work" {{ old('weekend_policy', $policy->weekend_policy ?? '') === 'no_work' ? 'selected' : '' }}>No Work</option>
                                    <option value="alternate_saturday" {{ old('weekend_policy', $policy->weekend_policy ?? '') === 'alternate_saturday' ? 'selected' : '' }}>Alternate Saturday</option>
                                    <option value="all_saturdays_off" {{ old('weekend_policy', $policy->weekend_policy ?? '') === 'all_saturdays_off' ? 'selected' : '' }}>All Saturdays Off</option>
                                    <option value="all_weekends_off" {{ old('weekend_policy', $policy->weekend_policy ?? '') === 'all_weekends_off' ? 'selected' : '' }}>All Weekends Off</option>
                                </select>
                                @error('weekend_policy')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
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
                            <input type="checkbox" class="form-check-input" id="overtime_applicable" 
                                   name="overtime_applicable" value="1"
                                   {{ old('overtime_applicable', $policy->overtime_applicable ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="overtime_applicable">Overtime Applicable</label>
                        </div>

                        <div id="ot_fields" style="{{ old('overtime_applicable', $policy->overtime_applicable ?? false) ? '' : 'display: none;' }}">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="overtime_min_minutes" class="form-label">Min OT Minutes</label>
                                    <input type="number" class="form-control @error('overtime_min_minutes') is-invalid @enderror" 
                                           id="overtime_min_minutes" name="overtime_min_minutes" 
                                           value="{{ old('overtime_min_minutes', $policy->overtime_min_minutes ?? 30) }}" 
                                           min="0">
                                    @error('overtime_min_minutes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="overtime_rate_multiplier" class="form-label">OT Rate Multiplier</label>
                                    <input type="number" class="form-control @error('overtime_rate_multiplier') is-invalid @enderror" 
                                           id="overtime_rate_multiplier" name="overtime_rate_multiplier" 
                                           value="{{ old('overtime_rate_multiplier', $policy->overtime_rate_multiplier ?? 1.5) }}" 
                                           min="1" max="5" step="0.1">
                                    @error('overtime_rate_multiplier')
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
                        <div class="form-check">
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
document.getElementById('overtime_applicable').addEventListener('change', function() {
    document.getElementById('ot_fields').style.display = this.checked ? '' : 'none';
});
</script>
@endpush
@endsection
