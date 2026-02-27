@extends('layouts.erp')

@section('title', 'Report Breakdown')

@section('content')
<div class="container">
    <h4 class="mb-4">Report Breakdown</h4>

    <form action="{{ route('maintenance.breakdowns.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Machine <span class="text-danger">*</span></label>
                <select name="machine_id" class="form-select" required>
                    <option value="">Select Machine</option>
                    @foreach ($machines as $m)
                        <option value="{{ $m->id }}" {{ old('machine_id') == $m->id ? 'selected' : '' }}>
                            {{ $m->name }}{{ $m->code ? ' (' . $m->code . ')' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('machine_id') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Reported At <span class="text-danger">*</span></label>
                <input type="datetime-local"
                       name="reported_at"
                       class="form-control"
                       value="{{ old('reported_at', now()->format('Y-m-d\TH:i')) }}"
                       required>
                @error('reported_at') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Breakdown Type <span class="text-danger">*</span></label>
                @php
                    $types = [
                        'mechanical' => 'Mechanical',
                        'electrical' => 'Electrical',
                        'hydraulic' => 'Hydraulic',
                        'software' => 'Software',
                        'operator_error' => 'Operator Error',
                        'other' => 'Other',
                    ];
                @endphp
                <select name="breakdown_type" class="form-select" required>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" {{ old('breakdown_type', 'mechanical') == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('breakdown_type') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Severity <span class="text-danger">*</span></label>
                @php
                    $severities = [
                        'minor' => 'Minor',
                        'major' => 'Major',
                        'critical' => 'Critical',
                    ];
                @endphp
                <select name="severity" class="form-select" required>
                    @foreach($severities as $value => $label)
                        <option value="{{ $value }}" {{ old('severity', 'minor') == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('severity') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-12 mb-3">
                <label class="form-label">Problem Description <span class="text-danger">*</span></label>
                <textarea name="problem_description" class="form-control" rows="4" required>{{ old('problem_description') }}</textarea>
                @error('problem_description') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-12 mb-3">
                <label class="form-label">Immediate Action Taken</label>
                <textarea name="immediate_action_taken" class="form-control" rows="3">{{ old('immediate_action_taken') }}</textarea>
                @error('immediate_action_taken') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <button class="btn btn-primary">Submit</button>
        <a href="{{ route('maintenance.breakdowns.index') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection
