@php
    $isEdit = isset($maintenance_plan);
    $pageTitle = $isEdit ? 'Edit Maintenance Plan' : 'Create Maintenance Plan';
    $pageSubtitle = $isEdit
        ? 'Update schedule, alerts, and checklist details for the assigned machine.'
        : 'Set up a maintenance plan that supervisors can manage quickly from mobile or desktop.';
    $selectedUsers = collect(old('alert_user_ids', $maintenance_plan->alert_user_ids ?? []))
        ->map(fn ($id) => (string) $id)
        ->all();
    $selectedFrequency = old('frequency_type', $maintenance_plan->frequency_type ?? 'monthly');
    $frequencyCards = [
        'daily' => ['label' => 'Daily', 'hint' => 'Every day'],
        'weekly' => ['label' => 'Weekly', 'hint' => 'Every week'],
        'monthly' => ['label' => 'Monthly', 'hint' => 'Every month'],
        'quarterly' => ['label' => 'Quarterly', 'hint' => 'Every 3 months'],
        'half_yearly' => ['label' => 'Half Yearly', 'hint' => 'Every 6 months'],
        'yearly' => ['label' => 'Yearly', 'hint' => 'Every 12 months'],
        'operating_hours' => ['label' => 'Operating Hours', 'hint' => 'Usage-based schedule'],
    ];
    $maintenanceTypes = [
        'preventive' => ['label' => 'Preventive', 'hint' => 'Routine service'],
        'predictive' => ['label' => 'Predictive', 'hint' => 'Condition-based check'],
        'calibration' => ['label' => 'Calibration', 'hint' => 'Accuracy and settings'],
        'inspection' => ['label' => 'Inspection', 'hint' => 'Visual and safety review'],
    ];
    $selectedMaintenanceType = old('maintenance_type', $maintenance_plan->maintenance_type ?? 'preventive');
    $checklistText = old('checklist_items_text', isset($maintenance_plan) ? implode("\n", $maintenance_plan->checklist_items ?? []) : '');
@endphp

<div class="container-fluid px-0">
    <div class="erp-mobile-form-shell erp-mobile-form-shell--maintenance">
        <div class="erp-mobile-form-header">
            <div>
                <p class="erp-mobile-form-eyebrow">Maintenance Supervisor</p>
                <h3 class="mb-2">{{ $pageTitle }}</h3>
                <p class="text-muted mb-0">{{ $pageSubtitle }}</p>
            </div>
            <div class="erp-mobile-form-status">
                <span class="badge text-bg-light border">{{ $isEdit ? 'Editing Existing Plan' : 'New Plan Setup' }}</span>
                <span class="badge text-bg-primary">{{ count($machines) }} Machines Available</span>
            </div>
        </div>

        <form action="{{ $formAction }}" method="POST" class="erp-mobile-form" data-maintenance-plan-form>
            @csrf
            @if ($formMethod !== 'POST')
                @method($formMethod)
            @endif

            <div class="erp-form-card">
                <div class="erp-form-card__head">
                    <div>
                        <h5>Plan Basics</h5>
                        <p>Pick the machine and name the plan clearly so the supervisor can identify it at a glance.</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-lg-7">
                        <label class="form-label">Machine <span class="text-danger">*</span></label>
                        <select name="machine_id" class="form-select form-select-lg" required>
                            <option value="">Select Machine</option>
                            @foreach ($machines as $machine)
                                <option value="{{ $machine->id }}"
                                    {{ (string) old('machine_id', $maintenance_plan->machine_id ?? '') === (string) $machine->id ? 'selected' : '' }}>
                                    {{ $machine->name }} ({{ $machine->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('machine_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-lg-5">
                        <label class="form-label">Plan Code</label>
                        <input
                            type="text"
                            name="plan_code"
                            class="form-control form-control-lg"
                            value="{{ old('plan_code', $maintenance_plan->plan_code ?? '') }}"
                            {{ $isEdit ? 'readonly' : '' }}
                            placeholder="Leave blank to auto-generate"
                        >
                        <div class="form-text">{{ $isEdit ? 'Plan code remains fixed after creation.' : 'If blank, the system will generate a code.' }}</div>
                        @error('plan_code') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            name="plan_name"
                            class="form-control form-control-lg"
                            value="{{ old('plan_name', $maintenance_plan->plan_name ?? '') }}"
                            placeholder="Example: Monthly Preventive Maintenance"
                            required
                        >
                        @error('plan_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="erp-form-card">
                <div class="erp-form-card__head">
                    <div>
                        <h5>Maintenance Type</h5>
                        <p>Choose the plan purpose and schedule unit. These controls are optimized for quick mobile selection.</p>
                    </div>
                </div>

                <div class="erp-choice-grid erp-choice-grid--compact">
                    @foreach ($maintenanceTypes as $value => $meta)
                        <label class="erp-choice-card">
                            <input
                                type="radio"
                                name="maintenance_type"
                                value="{{ $value }}"
                                {{ $selectedMaintenanceType === $value ? 'checked' : '' }}
                                required
                            >
                            <span class="erp-choice-card__body">
                                <strong>{{ $meta['label'] }}</strong>
                                <small>{{ $meta['hint'] }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('maintenance_type') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

                <div class="mt-4">
                    <label class="form-label">Frequency Type <span class="text-danger">*</span></label>
                    <div class="erp-choice-grid" data-frequency-grid>
                        @foreach ($frequencyCards as $value => $meta)
                            <label class="erp-choice-card">
                                <input
                                    type="radio"
                                    name="frequency_type"
                                    value="{{ $value }}"
                                    {{ $selectedFrequency === $value ? 'checked' : '' }}
                                    required
                                >
                                <span class="erp-choice-card__body">
                                    <strong>{{ $meta['label'] }}</strong>
                                    <small>{{ $meta['hint'] }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('frequency_type') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Frequency Value <span class="text-danger">*</span></label>
                        <input
                            type="number"
                            name="frequency_value"
                            class="form-control form-control-lg"
                            value="{{ old('frequency_value', $maintenance_plan->frequency_value ?? 1) }}"
                            min="1"
                            inputmode="numeric"
                            required
                        >
                        <div class="form-text" data-frequency-hint>
                            Example: 1 monthly, 2 weekly, or 500 operating hours.
                        </div>
                        @error('frequency_value') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Alert Days Before</label>
                        <input
                            type="number"
                            name="alert_days_before"
                            class="form-control form-control-lg"
                            value="{{ old('alert_days_before', $maintenance_plan->alert_days_before ?? 7) }}"
                            min="0"
                            inputmode="numeric"
                        >
                        @error('alert_days_before') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Estimated Duration (hours)</label>
                        <input
                            type="number"
                            name="estimated_duration_hours"
                            class="form-control form-control-lg"
                            value="{{ old('estimated_duration_hours', $maintenance_plan->estimated_duration_hours ?? '') }}"
                            step="0.25"
                            min="0"
                            inputmode="decimal"
                        >
                        @error('estimated_duration_hours') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="erp-form-card">
                <div class="erp-form-card__head">
                    <div>
                        <h5>Schedule and Shutdown</h5>
                        <p>Set the last completed date and next due date. Operating-hours plans do not require a calendar due date.</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Last Executed Date</label>
                        <input
                            type="date"
                            name="last_executed_date"
                            class="form-control form-control-lg"
                            value="{{ old('last_executed_date', optional($maintenance_plan->last_executed_date ?? null)->format('Y-m-d')) }}"
                        >
                        @error('last_executed_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-4" data-next-date-field>
                        <label class="form-label">Next Scheduled Date</label>
                        <input
                            type="date"
                            name="next_scheduled_date"
                            class="form-control form-control-lg"
                            value="{{ old('next_scheduled_date', optional($maintenance_plan->next_scheduled_date ?? null)->format('Y-m-d')) }}"
                        >
                        <div class="form-text">Leave blank to auto-calculate for date-based plans.</div>
                        @error('next_scheduled_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label d-block">Shutdown Requirement</label>
                        <label class="erp-toggle-card mt-1">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="requires_shutdown"
                                value="1"
                                {{ old('requires_shutdown', $maintenance_plan->requires_shutdown ?? 1) ? 'checked' : '' }}
                            >
                            <span>
                                <strong>Machine shutdown needed</strong>
                                <small>Enable if supervisors must stop the machine before starting this task.</small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="erp-form-card">
                <div class="erp-form-card__head">
                    <div>
                        <h5>Alerts and Checklist</h5>
                        <p>Select the people who should receive reminders and keep the checklist simple for mobile execution.</p>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Alert Users</label>
                    <div class="erp-check-grid">
                        @forelse ($users as $user)
                            <label class="erp-check-card">
                                <input
                                    type="checkbox"
                                    name="alert_user_ids[]"
                                    value="{{ $user->id }}"
                                    {{ in_array((string) $user->id, $selectedUsers, true) ? 'checked' : '' }}
                                >
                                <span>
                                    <strong>{{ $user->name }}</strong>
                                    <small>{{ $user->email }}</small>
                                </span>
                            </label>
                        @empty
                            <div class="text-muted small">No active users available for alert assignment.</div>
                        @endforelse
                    </div>
                    @error('alert_user_ids') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    @error('alert_user_ids.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Checklist Items</label>
                    <textarea
                        name="checklist_items_text"
                        class="form-control"
                        rows="5"
                        placeholder="One item per line&#10;Check oil level&#10;Inspect belts&#10;Clean filters"
                    >{{ $checklistText }}</textarea>
                    @error('checklist_items_text') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="form-label">Remarks</label>
                    <textarea
                        name="remarks"
                        class="form-control"
                        rows="4"
                        placeholder="Any extra instructions for the maintenance supervisor"
                    >{{ old('remarks', $maintenance_plan->remarks ?? '') }}</textarea>
                    @error('remarks') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="erp-mobile-form-actions">
                <button class="btn btn-primary btn-lg px-4">{{ $submitLabel }}</button>
                <a href="{{ route('maintenance.plans.index') }}" class="btn btn-outline-secondary btn-lg px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-maintenance-plan-form]');

    if (!form) {
        return;
    }

    var frequencyHint = form.querySelector('[data-frequency-hint]');
    var nextDateField = form.querySelector('[data-next-date-field]');

    var hints = {
        daily: 'Enter how many days between maintenance activities.',
        weekly: 'Enter how many weeks between maintenance activities.',
        monthly: 'Enter how many months between maintenance activities.',
        quarterly: 'Use 1 for every quarter, 2 for every 6 months, and so on.',
        half_yearly: 'Use 1 for every 6 months, 2 for yearly, and so on.',
        yearly: 'Enter how many years between maintenance activities.',
        operating_hours: 'Enter how many operating hours between maintenance activities.'
    };

    function updateScheduleUi() {
        var selected = form.querySelector('input[name="frequency_type"]:checked');
        var value = selected ? selected.value : 'monthly';

        if (frequencyHint) {
            frequencyHint.textContent = hints[value] || hints.monthly;
        }

        if (nextDateField) {
            nextDateField.classList.toggle('is-hidden', value === 'operating_hours');
        }
    }

    form.querySelectorAll('input[name="frequency_type"]').forEach(function (input) {
        input.addEventListener('change', updateScheduleUi);
    });

    updateScheduleUi();
});
</script>
@endpush
