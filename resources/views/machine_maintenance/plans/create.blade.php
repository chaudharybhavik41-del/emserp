@extends('layouts.erp')

@section('title', 'Create Maintenance Plan')

@section('content')
<div class="container">
    <h4 class="mb-4">Create Maintenance Plan</h4>

    <form action="{{ route('maintenance.plans.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Machine <span class="text-danger">*</span></label>
                <select name="machine_id" class="form-select" required>
                    <option value="">Select Machine</option>
                    @foreach ($machines as $machine)
                        <option value="{{ $machine->id }}"
                            {{ (string) old('machine_id') === (string) $machine->id ? 'selected' : '' }}>
                            {{ $machine->name }} ({{ $machine->code }})
                        </option>
                    @endforeach
                </select>
                @error('machine_id') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Plan Code</label>
                <input type="text" name="plan_code" class="form-control"
                       value="{{ old('plan_code') }}"
                       placeholder="Leave blank to auto-generate">
                @error('plan_code') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Plan Name <span class="text-danger">*</span></label>
            <input type="text" name="plan_name" class="form-control" value="{{ old('plan_name') }}" required>
            @error('plan_name') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Maintenance Type <span class="text-danger">*</span></label>
                <select name="maintenance_type" class="form-select" required>
                    @php($types = ['preventive','predictive','calibration','inspection'])
                    @foreach($types as $t)
                        <option value="{{ $t }}" {{ old('maintenance_type', 'preventive') === $t ? 'selected' : '' }}>
                            {{ ucfirst($t) }}
                        </option>
                    @endforeach
                </select>
                @error('maintenance_type') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Frequency Type <span class="text-danger">*</span></label>
                <select name="frequency_type" class="form-select" required>
                    @php($freqTypes = ['daily','weekly','monthly','quarterly','half_yearly','yearly','operating_hours'])
                    @foreach($freqTypes as $ft)
                        <option value="{{ $ft }}" {{ old('frequency_type', 'monthly') === $ft ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_',' ', $ft)) }}
                        </option>
                    @endforeach
                </select>
                @error('frequency_type') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Frequency Value <span class="text-danger">*</span></label>
                <input type="number" name="frequency_value" class="form-control"
                       value="{{ old('frequency_value', 1) }}" min="1" required>
                <div class="form-text">Example: 1 monthly / 2 weekly / 500 operating hours</div>
                @error('frequency_value') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Last Executed Date</label>
                <input type="date" name="last_executed_date" class="form-control" value="{{ old('last_executed_date') }}">
                @error('last_executed_date') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Next Scheduled Date</label>
                <input type="date" name="next_scheduled_date" class="form-control" value="{{ old('next_scheduled_date') }}">
                <div class="form-text">Leave blank to auto-calculate (for date-based plans).</div>
                @error('next_scheduled_date') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Alert Days Before</label>
                <input type="number" name="alert_days_before" class="form-control"
                       value="{{ old('alert_days_before', 7) }}" min="0">
                @error('alert_days_before') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Estimated Duration (hours)</label>
                <input type="number" name="estimated_duration_hours" class="form-control"
                       value="{{ old('estimated_duration_hours') }}" step="0.25" min="0">
                @error('estimated_duration_hours') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="requires_shutdown" id="requires_shutdown"
                        value="1" {{ old('requires_shutdown', 1) ? 'checked' : '' }}>
                    <label class="form-check-label" for="requires_shutdown">
                        Requires Shutdown
                    </label>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Alert Users</label>
            <select name="alert_user_ids[]" class="form-select" multiple>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}"
                        {{ in_array($user->id, old('alert_user_ids', [])) ? 'selected' : '' }}>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
            <div class="form-text">Hold Ctrl / Cmd to select multiple.</div>
            @error('alert_user_ids') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label">Checklist Items (one per line)</label>
            <textarea name="checklist_items_text" class="form-control" rows="4">{{ old('checklist_items_text') }}</textarea>
            @error('checklist_items_text') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="3">{{ old('remarks') }}</textarea>
            @error('remarks') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <button class="btn btn-primary">Save Plan</button>
        <a href="{{ route('maintenance.plans.index') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection
