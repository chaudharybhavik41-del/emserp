@extends('layouts.erp')

@section('title', 'Edit Maintenance Plan')

@section('content')
<div class="container">
    <h4 class="mb-4">Edit Maintenance Plan</h4>

    <form action="{{ route('maintenance.plans.update', $maintenance_plan) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Machine <span class="text-danger">*</span></label>
                <select name="machine_id" class="form-select" required>
                    @foreach ($machines as $machine)
                        <option value="{{ $machine->id }}"
                            {{ (string) old('machine_id', $maintenance_plan->machine_id) === (string) $machine->id ? 'selected' : '' }}>
                            {{ $machine->name }} ({{ $machine->code }})
                        </option>
                    @endforeach
                </select>
                @error('machine_id') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Plan Code</label>
                <input type="text" name="plan_code" class="form-control"
                       value="{{ old('plan_code', $maintenance_plan->plan_code) }}" readonly>
                @error('plan_code') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Plan Name <span class="text-danger">*</span></label>
            <input type="text" name="plan_name" class="form-control"
                   value="{{ old('plan_name', $maintenance_plan->plan_name) }}" required>
            @error('plan_name') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Maintenance Type <span class="text-danger">*</span></label>
                <select name="maintenance_type" class="form-select" required>
                    @php($types = ['preventive','predictive','calibration','inspection'])
                    @foreach($types as $t)
                        <option value="{{ $t }}" {{ old('maintenance_type', $maintenance_plan->maintenance_type) === $t ? 'selected' : '' }}>
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
                        <option value="{{ $ft }}" {{ old('frequency_type', $maintenance_plan->frequency_type) === $ft ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_',' ', $ft)) }}
                        </option>
                    @endforeach
                </select>
                @error('frequency_type') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Frequency Value <span class="text-danger">*</span></label>
                <input type="number" name="frequency_value" class="form-control"
                       value="{{ old('frequency_value', $maintenance_plan->frequency_value) }}" min="1" required>
                @error('frequency_value') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Last Executed Date</label>
                <input type="date" name="last_executed_date" class="form-control"
                       value="{{ old('last_executed_date', optional($maintenance_plan->last_executed_date)->format('Y-m-d')) }}">
                @error('last_executed_date') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Next Scheduled Date</label>
                <input type="date" name="next_scheduled_date" class="form-control"
                       value="{{ old('next_scheduled_date', optional($maintenance_plan->next_scheduled_date)->format('Y-m-d')) }}">
                @error('next_scheduled_date') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Alert Days Before</label>
                <input type="number" name="alert_days_before" class="form-control"
                       value="{{ old('alert_days_before', $maintenance_plan->alert_days_before) }}" min="0">
                @error('alert_days_before') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Estimated Duration (hours)</label>
                <input type="number" name="estimated_duration_hours" class="form-control"
                       value="{{ old('estimated_duration_hours', $maintenance_plan->estimated_duration_hours) }}" step="0.25" min="0">
                @error('estimated_duration_hours') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="requires_shutdown" id="requires_shutdown"
                        value="1" {{ old('requires_shutdown', $maintenance_plan->requires_shutdown) ? 'checked' : '' }}>
                    <label class="form-check-label" for="requires_shutdown">
                        Requires Shutdown
                    </label>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Alert Users</label>
            @php($selectedUsers = old('alert_user_ids', $maintenance_plan->alert_user_ids ?? []))
            <select name="alert_user_ids[]" class="form-select" multiple>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}"
                        {{ in_array($user->id, $selectedUsers) ? 'selected' : '' }}>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
            <div class="form-text">Hold Ctrl / Cmd to select multiple.</div>
            @error('alert_user_ids') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label">Checklist Items (one per line)</label>
            @php($checklistText = old('checklist_items_text', implode("\n", $maintenance_plan->checklist_items ?? [])))
            <textarea name="checklist_items_text" class="form-control" rows="4">{{ $checklistText }}</textarea>
            @error('checklist_items_text') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="3">{{ old('remarks', $maintenance_plan->remarks) }}</textarea>
            @error('remarks') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <button class="btn btn-primary">Update Plan</button>
        <a href="{{ route('maintenance.plans.index') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection
