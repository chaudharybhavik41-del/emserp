@extends('layouts.erp')

@section('title', 'New Maintenance Log')

@section('content')
<div class="container">
    <h4 class="mb-4">New Maintenance Log</h4>

    <form action="{{ route('maintenance.logs.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Machine <span class="text-danger">*</span></label>
                <select name="machine_id" class="form-select" required>
                    <option value="">Select Machine</option>
                    @foreach ($machines as $machine)
                        <option value="{{ $machine->id }}" {{ old('machine_id') == $machine->id ? 'selected' : '' }}>
                            {{ $machine->name }} ({{ $machine->code }})
                        </option>
                    @endforeach
                </select>
                @error('machine_id') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Plan (optional)</label>
                <select name="maintenance_plan_id" class="form-select">
                    <option value="">-- None --</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" {{ old('maintenance_plan_id') == $plan->id ? 'selected' : '' }}>
                            {{ $plan->plan_name }} ({{ $plan->plan_code }})
                        </option>
                    @endforeach
                </select>
                @error('maintenance_plan_id') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Maintenance Type <span class="text-danger">*</span></label>
                <select name="maintenance_type" class="form-select" required>
                    @foreach (['preventive', 'breakdown', 'predictive', 'calibration', 'inspection'] as $type)
                        <option value="{{ $type }}" {{ old('maintenance_type', 'preventive') === $type ? 'selected' : '' }}>
                            {{ ucfirst($type) }}
                        </option>
                    @endforeach
                </select>
                @error('maintenance_type') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-select" required>
                    @foreach (['scheduled', 'in_progress', 'completed', 'deferred', 'cancelled'] as $status)
                        <option value="{{ $status }}" {{ old('status', 'scheduled') === $status ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_',' ', $status)) }}
                        </option>
                    @endforeach
                </select>
                @error('status') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    @foreach (['low', 'medium', 'high', 'critical'] as $p)
                        <option value="{{ $p }}" {{ old('priority', 'medium') === $p ? 'selected' : '' }}>
                            {{ ucfirst($p) }}
                        </option>
                    @endforeach
                </select>
                @error('priority') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Scheduled Date</label>
                <input type="date" name="scheduled_date" class="form-control" value="{{ old('scheduled_date') }}">
                @error('scheduled_date') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Started At</label>
                <input type="datetime-local" name="started_at" class="form-control"
                       value="{{ old('started_at') }}">
                <div class="form-text">If blank, system will auto-fill.</div>
                @error('started_at') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Completed At</label>
                <input type="datetime-local" name="completed_at" class="form-control"
                       value="{{ old('completed_at') }}">
                @error('completed_at') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Meter Reading Before</label>
                <input type="number" name="meter_reading_before" class="form-control" step="0.01" min="0"
                       value="{{ old('meter_reading_before') }}">
                @error('meter_reading_before') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Meter Reading After</label>
                <input type="number" name="meter_reading_after" class="form-control" step="0.01" min="0"
                       value="{{ old('meter_reading_after') }}">
                @error('meter_reading_after') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Work Description <span class="text-danger">*</span></label>
            <textarea name="work_description" class="form-control" rows="3" required>{{ old('work_description') }}</textarea>
            @error('work_description') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label">Work Performed</label>
            <textarea name="work_performed" class="form-control" rows="2">{{ old('work_performed') }}</textarea>
            @error('work_performed') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Findings</label>
                <textarea name="findings" class="form-control" rows="2">{{ old('findings') }}</textarea>
                @error('findings') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Recommendations</label>
                <textarea name="recommendations" class="form-control" rows="2">{{ old('recommendations') }}</textarea>
                @error('recommendations') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Technicians</label>
            <select name="technician_user_ids[]" class="form-select" multiple>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}"
                        @if(in_array($user->id, old('technician_user_ids', []))) selected @endif>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
            @error('technician_user_ids') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label">External Vendor (optional)</label>
            <select name="external_vendor_party_id" class="form-select">
                <option value="">-- None --</option>
                @foreach ($vendors as $vendor)
                    <option value="{{ $vendor->id }}" {{ old('external_vendor_party_id') == $vendor->id ? 'selected' : '' }}>
                        {{ $vendor->name }}
                    </option>
                @endforeach
            </select>
            @error('external_vendor_party_id') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Labor Cost</label>
                <input type="number" name="labor_cost" class="form-control" step="0.01" min="0" value="{{ old('labor_cost', 0) }}">
                @error('labor_cost') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">External Service Cost</label>
                <input type="number" name="external_service_cost" class="form-control" step="0.01" min="0" value="{{ old('external_service_cost', 0) }}">
                @error('external_service_cost') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Downtime (hours)</label>
                <input type="number" name="downtime_hours" class="form-control" step="0.01" min="0" value="{{ old('downtime_hours', 0) }}">
                @error('downtime_hours') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="alert alert-info">
            <strong>Parts cost:</strong> will be calculated automatically from <strong>Store Issue</strong> imports on the log view page.
        </div>

        <div class="mb-3">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="3">{{ old('remarks') }}</textarea>
            @error('remarks') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <button class="btn btn-primary">Save Log</button>
        <a href="{{ route('maintenance.logs.index') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection
