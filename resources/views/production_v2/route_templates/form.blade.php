@extends('layouts.erp')

@section('title', $routeTemplate->exists ? 'Edit Production V2 Route Template' : 'Create Production V2 Route Template')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $routeTemplate->exists ? 'Edit Route Template' : 'Create Route Template' }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @php
        $oldSteps = old('steps');
        if ($oldSteps === null) {
            $oldSteps = $routeTemplate->exists
                ? $routeTemplate->steps->map(fn ($row) => [
                    'operation_master_id' => $row->operation_master_id,
                    'sequence_no' => $row->sequence_no,
                    'is_mandatory' => $row->is_mandatory,
                    'qc_gate_required' => $row->qc_gate_required,
                    'qc_gate_mode' => $row->qc_gate_mode,
                    'qc_gate_type' => $row->qc_gate_type,
                    'qc_gate_remarks' => $row->qc_gate_remarks,
                    'remarks' => $row->remarks,
                ])->all()
                : [[
                    'operation_master_id' => '',
                    'sequence_no' => 1,
                    'is_mandatory' => 1,
                    'qc_gate_required' => 0,
                    'qc_gate_mode' => '',
                    'qc_gate_type' => '',
                    'qc_gate_remarks' => '',
                    'remarks' => '',
                ]];
        }
    @endphp

    @include('production_v2.partials.module_nav', ['project' => $project, 'active' => 'route_templates'])

    <div class="alert alert-info">
        Production planning controls process applicability here. Create only the stages that are actually needed for the target item type, then assign the template from the released production route plan.
    </div>

    <form method="POST" action="{{ $routeTemplate->exists ? route('projects.production-v2.route-templates.update', ['project' => $project->id, 'routeTemplate' => $routeTemplate->id]) : route('projects.production-v2.route-templates.store', ['project' => $project->id]) }}">
        @csrf
        @if($routeTemplate->exists)
            @method('PUT')
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Template Code</label>
                        <input name="template_code" class="form-control" value="{{ old('template_code', $routeTemplate->template_code) }}" required>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label">Template Name</label>
                        <input name="template_name" class="form-control" value="{{ old('template_name', $routeTemplate->template_name) }}" required>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Applies To</label>
                        <select name="applies_to" id="applies_to" class="form-select" data-erp-select data-hide-search="true">
                            <option value="part" @selected(old('applies_to', $routeTemplate->applies_to) === 'part')>Part</option>
                            <option value="assembly" @selected(old('applies_to', $routeTemplate->applies_to) === 'assembly')>Assembly</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" data-erp-select data-hide-search="true">
                            @foreach(['draft', 'approved', 'active', 'obsolete'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $routeTemplate->status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" rows="2" class="form-control">{{ old('remarks', $routeTemplate->remarks) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Route Steps</span>
                <div class="d-flex gap-2">
                    <a href="{{ route('projects.production-v2.operation-masters.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Process Masters</a>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-route-step">Add Step</button>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-dark route-preset" data-applies-to="part" data-operations="cutting,drilling">Cut + Drill</button>
                    <button type="button" class="btn btn-sm btn-outline-dark route-preset" data-applies-to="part" data-operations="cutting,drilling,beveling">Cut + Drill + Bevel</button>
                    <button type="button" class="btn btn-sm btn-outline-dark route-preset" data-applies-to="assembly" data-operations="fitup,welding,inspection">Fit-up + Weld + Inspect</button>
                    <button type="button" class="btn btn-sm btn-outline-dark route-preset" data-applies-to="assembly" data-operations="fitup,welding,inspection,trial_assembly">Fit-up + Weld + Inspect + Trial</button>
                    <button type="button" class="btn btn-sm btn-outline-dark route-preset" data-applies-to="assembly" data-operations="fitup,welding,inspection,blasting,painting">Fit-up + Weld + Inspect + Blast + Paint</button>
                </div>
                <div class="small text-body-secondary mb-3">Quick presets reduce typing. Apply a preset, then adjust sequence or remarks row by row.</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="route-step-table">
                        <thead>
                            <tr>
                                <th style="width: 38%">Operation</th>
                                <th style="width: 12%">Seq</th>
                                <th style="width: 12%">Mandatory</th>
                                <th style="width: 14%">QC Gate</th>
                                <th style="width: 14%">Gate Type</th>
                                <th>Remarks</th>
                                <th style="width: 5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($oldSteps as $index => $row)
                            <tr>
                                <td>
                                    <select name="steps[{{ $index }}][operation_master_id]" class="form-select route-operation-select" data-erp-select data-placeholder="Select operation">
                                        <option value="">Select operation</option>
                                        @foreach($partOperations as $operation)
                                            <option value="{{ $operation->id }}" data-applies-to="part" data-operation-code="{{ $operation->code }}" @selected((string) ($row['operation_master_id'] ?? '') === (string) $operation->id)>{{ $operation->name }}</option>
                                        @endforeach
                                        @foreach($assemblyOperations as $operation)
                                            <option value="{{ $operation->id }}" data-applies-to="assembly" data-operation-code="{{ $operation->code }}" @selected((string) ($row['operation_master_id'] ?? '') === (string) $operation->id)>{{ $operation->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" min="1" name="steps[{{ $index }}][sequence_no]" class="form-control" value="{{ $row['sequence_no'] ?? ($index + 1) }}"></td>
                                <td class="text-center"><input type="checkbox" value="1" name="steps[{{ $index }}][is_mandatory]" @checked((bool) ($row['is_mandatory'] ?? true))></td>
                                <td>
                                    <div class="form-check mb-2">
                                        <input type="checkbox" value="1" name="steps[{{ $index }}][qc_gate_required]" class="form-check-input qc-gate-required" @checked((bool) ($row['qc_gate_required'] ?? false))>
                                    </div>
                                    <select name="steps[{{ $index }}][qc_gate_mode]" class="form-select form-select-sm qc-gate-mode" data-erp-select data-hide-search="true">
                                        <option value="">No gate</option>
                                        @foreach($gateModes as $value => $label)
                                            <option value="{{ $value }}" @selected(($row['qc_gate_mode'] ?? '') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="steps[{{ $index }}][qc_gate_type]" class="form-select form-select-sm qc-gate-type mb-2" data-erp-select data-hide-search="true">
                                        <option value="">Select type</option>
                                        @foreach($gateTypes as $value => $label)
                                            <option value="{{ $value }}" @selected(($row['qc_gate_type'] ?? '') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input name="steps[{{ $index }}][qc_gate_remarks]" class="form-control form-control-sm qc-gate-remarks" value="{{ $row['qc_gate_remarks'] ?? '' }}" placeholder="gate remarks">
                                </td>
                                <td><input name="steps[{{ $index }}][remarks]" class="form-control" value="{{ $row['remarks'] ?? '' }}"></td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex gap-2 position-sticky bottom-0 bg-body">
                <button type="submit" class="btn btn-primary">{{ $routeTemplate->exists ? 'Update' : 'Create' }}</button>
                <a href="{{ route('projects.production-v2.route-templates.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>

    <template id="route-step-template">
        <tr>
            <td>
                <select class="form-select route-operation-select" data-erp-select data-placeholder="Select operation">
                    <option value="">Select operation</option>
                    @foreach($partOperations as $operation)
                        <option value="{{ $operation->id }}" data-applies-to="part" data-operation-code="{{ $operation->code }}">{{ $operation->name }}</option>
                    @endforeach
                    @foreach($assemblyOperations as $operation)
                        <option value="{{ $operation->id }}" data-applies-to="assembly" data-operation-code="{{ $operation->code }}">{{ $operation->name }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" min="1" class="form-control route-sequence"></td>
            <td class="text-center"><input type="checkbox" value="1" class="route-mandatory" checked></td>
            <td>
                <div class="form-check mb-2">
                    <input type="checkbox" value="1" class="form-check-input qc-gate-required">
                </div>
                <select class="form-select form-select-sm qc-gate-mode" data-erp-select data-hide-search="true">
                    <option value="">No gate</option>
                    @foreach($gateModes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select class="form-select form-select-sm qc-gate-type mb-2" data-erp-select data-hide-search="true">
                    <option value="">Select type</option>
                    @foreach($gateTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input class="form-control form-control-sm qc-gate-remarks" placeholder="gate remarks">
            </td>
            <td><input class="form-control route-remarks"></td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
        </tr>
    </template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const appliesTo = document.getElementById('applies_to');
    const tableBody = document.querySelector('#route-step-table tbody');
    const template = document.getElementById('route-step-template');
    const addButton = document.getElementById('add-route-step');
    const presetButtons = document.querySelectorAll('.route-preset');

    const refreshNames = () => {
        [...tableBody.querySelectorAll('tr')].forEach((row, index) => {
            row.querySelector('.route-operation-select, select[name*="[operation_master_id]"]')?.setAttribute('name', `steps[${index}][operation_master_id]`);
            row.querySelector('.route-sequence, input[name*="[sequence_no]"]')?.setAttribute('name', `steps[${index}][sequence_no]`);
            row.querySelector('.route-mandatory, input[name*="[is_mandatory]"]')?.setAttribute('name', `steps[${index}][is_mandatory]`);
            row.querySelector('.qc-gate-required, input[name*="[qc_gate_required]"]')?.setAttribute('name', `steps[${index}][qc_gate_required]`);
            row.querySelector('.qc-gate-mode, select[name*="[qc_gate_mode]"]')?.setAttribute('name', `steps[${index}][qc_gate_mode]`);
            row.querySelector('.qc-gate-type, select[name*="[qc_gate_type]"]')?.setAttribute('name', `steps[${index}][qc_gate_type]`);
            row.querySelector('.qc-gate-remarks, input[name*="[qc_gate_remarks]"]')?.setAttribute('name', `steps[${index}][qc_gate_remarks]`);
            row.querySelector('.route-remarks, input[name*="[remarks]"]')?.setAttribute('name', `steps[${index}][remarks]`);
        });
    };

    const filterOptions = () => {
        const target = appliesTo?.value || 'assembly';
        tableBody.querySelectorAll('.route-operation-select').forEach((select) => {
            [...select.options].forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                option.hidden = option.dataset.appliesTo !== target;
            });
        });
    };

    const refreshPresetState = () => {
        const target = appliesTo?.value || 'assembly';
        presetButtons.forEach((button) => {
            button.classList.toggle('d-none', button.dataset.appliesTo !== target);
        });
    };

    const refreshGateState = () => {
        tableBody.querySelectorAll('tr').forEach((row) => {
            const requiredInput = row.querySelector('.qc-gate-required, input[name*="[qc_gate_required]"]');
            const modeInput = row.querySelector('.qc-gate-mode, select[name*="[qc_gate_mode]"]');
            const typeInput = row.querySelector('.qc-gate-type, select[name*="[qc_gate_type]"]');
            const remarksInput = row.querySelector('.qc-gate-remarks, input[name*="[qc_gate_remarks]"]');
            const enabled = Boolean(requiredInput?.checked);

            [modeInput, typeInput, remarksInput].forEach((element) => {
                if (!element) {
                    return;
                }
                element.disabled = !enabled;
                if (!enabled && element.tagName === 'SELECT') {
                    element.value = '';
                }
                if (!enabled && element.tagName === 'INPUT') {
                    element.value = '';
                }
            });
        });
    };

    const buildPresetRow = (operationCode, index) => {
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('tr');
        const select = row.querySelector('.route-operation-select');
        const sequenceInput = row.querySelector('.route-sequence');

        [...select.options].forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            option.hidden = option.dataset.appliesTo !== (appliesTo?.value || 'assembly');
            if (option.dataset.operationCode === operationCode) {
                select.value = option.value;
            }
        });

        sequenceInput.value = index + 1;
        return row;
    };

    addButton?.addEventListener('click', () => {
        const clone = template.content.cloneNode(true);
        tableBody.appendChild(clone);
        refreshNames();
        filterOptions();
        refreshGateState();
    });

    presetButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const operations = (button.dataset.operations || '').split(',').map((value) => value.trim()).filter(Boolean);
            if (!operations.length) {
                return;
            }

            tableBody.innerHTML = '';
            operations.forEach((operationCode, index) => {
                tableBody.appendChild(buildPresetRow(operationCode, index));
            });
            refreshNames();
            filterOptions();
            refreshGateState();
        });
    });

    tableBody?.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-row');
        if (!button) {
            return;
        }

        const rows = tableBody.querySelectorAll('tr');
        if (rows.length <= 1) {
            rows[0].querySelectorAll('input').forEach((input) => {
                if (input.type === 'checkbox') {
                    input.checked = true;
                } else {
                    input.value = '';
                }
            });
            rows[0].querySelectorAll('select').forEach((select) => select.value = '');
            return;
        }

        button.closest('tr')?.remove();
        refreshNames();
        refreshGateState();
    });

    appliesTo?.addEventListener('change', () => {
        filterOptions();
        refreshPresetState();
    });

    tableBody?.addEventListener('change', (event) => {
        if (event.target.matches('.qc-gate-required, input[name*="[qc_gate_required]"]')) {
            refreshGateState();
        }
    });

    refreshNames();
    filterOptions();
    refreshPresetState();
    refreshGateState();
});
</script>
@endpush
