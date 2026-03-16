@extends('layouts.erp')

@section('title', 'Report Breakdown')

@section('content')
@php
    $types = [
        'mechanical' => ['label' => 'Mechanical', 'hint' => 'Belts, shafts, bearings'],
        'electrical' => ['label' => 'Electrical', 'hint' => 'Power, panels, sensors'],
        'hydraulic' => ['label' => 'Hydraulic', 'hint' => 'Pressure, oil, actuators'],
        'software' => ['label' => 'Software', 'hint' => 'PLC, HMI, program issue'],
        'operator_error' => ['label' => 'Operator Error', 'hint' => 'Usage or process issue'],
        'other' => ['label' => 'Other', 'hint' => 'Anything not covered above'],
    ];
    $severities = [
        'minor' => ['label' => 'Minor', 'hint' => 'Machine can wait briefly'],
        'major' => ['label' => 'Major', 'hint' => 'Needs quick action'],
        'critical' => ['label' => 'Critical', 'hint' => 'Immediate shutdown risk'],
    ];
@endphp

<div class="container-fluid px-0">
    <div class="erp-mobile-form-shell erp-mobile-form-shell--breakdown">
        <div class="erp-mobile-form-header">
            <div>
                <p class="erp-mobile-form-eyebrow">Breakdown Register</p>
                <h3 class="mb-2">Report Breakdown</h3>
                <p class="text-muted mb-0">Simplified for supervisors to submit quickly from mobile PWA during urgent stoppages.</p>
            </div>
            <div class="erp-mobile-form-status">
                <span class="badge text-bg-danger-subtle border border-danger-subtle text-danger-emphasis">Fast Incident Entry</span>
                <span class="badge text-bg-light border">{{ count($machines) }} Active Machines</span>
            </div>
        </div>

        <form action="{{ route('maintenance.breakdowns.store') }}" method="POST" class="erp-mobile-form" data-breakdown-form>
            @csrf

            <div class="erp-form-card">
                <div class="erp-form-card__head">
                    <div>
                        <h5>Breakdown Basics</h5>
                        <p>Select the machine and capture the report time immediately. One tap can reset the time to now.</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-lg-7">
                        <label class="form-label">Machine <span class="text-danger">*</span></label>
                        <select name="machine_id" class="form-select form-select-lg" required>
                            <option value="">Select Machine</option>
                            @foreach ($machines as $m)
                                <option value="{{ $m->id }}" {{ (string) old('machine_id') === (string) $m->id ? 'selected' : '' }}>
                                    {{ $m->name }}{{ $m->code ? ' (' . $m->code . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('machine_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-lg-5">
                        <label class="form-label">Reported At <span class="text-danger">*</span></label>
                        <div class="erp-inline-action">
                            <input
                                type="datetime-local"
                                name="reported_at"
                                class="form-control form-control-lg"
                                value="{{ old('reported_at', now()->format('Y-m-d\TH:i')) }}"
                                required
                                data-breakdown-reported-at
                            >
                            <button type="button" class="btn btn-outline-secondary" data-breakdown-now>Now</button>
                        </div>
                        @error('reported_at') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="erp-form-card">
                <div class="erp-form-card__head">
                    <div>
                        <h5>Issue Category</h5>
                        <p>Large selection cards reduce typing and work better on touch screens.</p>
                    </div>
                </div>

                <label class="form-label">Breakdown Type <span class="text-danger">*</span></label>
                <div class="erp-choice-grid">
                    @foreach($types as $value => $meta)
                        <label class="erp-choice-card">
                            <input
                                type="radio"
                                name="breakdown_type"
                                value="{{ $value }}"
                                {{ old('breakdown_type', 'mechanical') === $value ? 'checked' : '' }}
                                required
                            >
                            <span class="erp-choice-card__body">
                                <strong>{{ $meta['label'] }}</strong>
                                <small>{{ $meta['hint'] }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('breakdown_type') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

                <label class="form-label mt-4">Severity <span class="text-danger">*</span></label>
                <div class="erp-choice-grid erp-choice-grid--compact">
                    @foreach($severities as $value => $meta)
                        <label class="erp-choice-card erp-choice-card--severity">
                            <input
                                type="radio"
                                name="severity"
                                value="{{ $value }}"
                                {{ old('severity', 'minor') === $value ? 'checked' : '' }}
                                required
                            >
                            <span class="erp-choice-card__body">
                                <strong>{{ $meta['label'] }}</strong>
                                <small>{{ $meta['hint'] }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('severity') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
            </div>

            <div class="erp-form-card">
                <div class="erp-form-card__head">
                    <div>
                        <h5>Problem Notes</h5>
                        <p>Keep the main issue clear. Immediate action is optional but useful for the next maintenance supervisor.</p>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Problem Description <span class="text-danger">*</span></label>
                    <textarea
                        name="problem_description"
                        class="form-control"
                        rows="5"
                        required
                        placeholder="Describe what failed, what symptoms were seen, and whether the machine stopped completely."
                    >{{ old('problem_description') }}</textarea>
                    @error('problem_description') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="form-label">Immediate Action Taken</label>
                    <textarea
                        name="immediate_action_taken"
                        class="form-control"
                        rows="4"
                        placeholder="Example: machine isolated, main breaker off, operator informed, spare part request raised"
                    >{{ old('immediate_action_taken') }}</textarea>
                    @error('immediate_action_taken') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="erp-mobile-form-actions erp-mobile-form-actions--urgent">
                <button class="btn btn-danger btn-lg px-4">Submit Breakdown</button>
                <a href="{{ route('maintenance.breakdowns.index') }}" class="btn btn-outline-secondary btn-lg px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-breakdown-form]');

    if (!form) {
        return;
    }

    var input = form.querySelector('[data-breakdown-reported-at]');
    var nowButton = form.querySelector('[data-breakdown-now]');

    if (!input || !nowButton) {
        return;
    }

    nowButton.addEventListener('click', function () {
        var current = new Date();
        current.setMinutes(current.getMinutes() - current.getTimezoneOffset());
        input.value = current.toISOString().slice(0, 16);
    });
});
</script>
@endpush
