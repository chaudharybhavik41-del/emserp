@extends('layouts.erp')

@section('title', $assembly->exists ? 'Edit Production V2 Assembly' : 'Create Production V2 Assembly')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $assembly->exists ? 'Edit Assembly' : 'Create Assembly' }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @php
        $oldRequirements = old('requirements');
        if ($oldRequirements === null) {
            $oldRequirements = $assembly->exists
                ? $assembly->requirements->map(fn ($row) => [
                    'part_definition_id' => $row->part_definition_id,
                    'required_qty' => $row->required_qty,
                    'uom_id' => $row->uom_id,
                    'consumption_sequence' => $row->consumption_sequence,
                    'is_mandatory' => $row->is_mandatory,
                    'remarks' => $row->remarks,
                ])->all()
                : [['part_definition_id' => '', 'required_qty' => '', 'uom_id' => '', 'consumption_sequence' => 1, 'is_mandatory' => 1, 'remarks' => '']];
        }
    @endphp

    @include('production_v2.partials.design_nav', ['project' => $project, 'active' => 'assemblies'])

    <div class="alert alert-info">
        Design defines the assembly consumption map here. The parts below are engineering requirements only; actual WIP-to-segment consumption is confirmed later during production fit-up.
        <a href="{{ route('projects.production-v2.route-planning.index', ['project' => $project->id]) }}" class="alert-link">Open Production Route Planning</a>
    </div>

    <form method="POST" action="{{ $assembly->exists ? route('projects.production-v2.assemblies.update', ['project' => $project->id, 'assembly' => $assembly->id]) : route('projects.production-v2.assemblies.store', ['project' => $project->id]) }}">
        @csrf
        @if($assembly->exists)
            @method('PUT')
        @endif
        <input type="hidden" name="revision_no" value="{{ old('revision_no', $assembly->revision_no ?: 1) }}">

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-2">
                        <label class="form-label">Revision</label>
                        <input class="form-control" value="R{{ old('revision_no', $assembly->revision_no ?: 1) }}" readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Assembly Code</label>
                        <input name="assembly_code" class="form-control @error('assembly_code') is-invalid @enderror" value="{{ old('assembly_code', $assembly->assembly_code) }}" required>
                        @error('assembly_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Assembly Name</label>
                        <input name="assembly_name" class="form-control @error('assembly_name') is-invalid @enderror" value="{{ old('assembly_name', $assembly->assembly_name) }}" required>
                        @error('assembly_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Assembly Type</label>
                        <input name="assembly_type" class="form-control" value="{{ old('assembly_type', $assembly->assembly_type) }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Span No</label>
                        <input name="span_no" class="form-control" value="{{ old('span_no', $assembly->span_no) }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Leaf No</label>
                        <input name="leaf_no" class="form-control" value="{{ old('leaf_no', $assembly->leaf_no) }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Segment No</label>
                        <input name="segment_no" class="form-control" value="{{ old('segment_no', $assembly->segment_no) }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Girder No</label>
                        <input name="girder_no" class="form-control" value="{{ old('girder_no', $assembly->girder_no) }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Drawing Ref</label>
                        <input name="drawing_ref" class="form-control" value="{{ old('drawing_ref', $assembly->drawing_ref) }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Sequence</label>
                        <input type="number" min="0" name="sequence_no" class="form-control" value="{{ old('sequence_no', $assembly->sequence_no) }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label">Planned Qty</label>
                        <input type="number" step="0.001" min="0.001" name="planned_qty" class="form-control" value="{{ old('planned_qty', $assembly->planned_qty ?: 1) }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Planned Weight (kg)</label>
                        <input type="number" step="0.001" min="0" name="planned_weight_kg" class="form-control" value="{{ old('planned_weight_kg', $assembly->planned_weight_kg) }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" data-erp-select data-hide-search="true">
                            @foreach(['draft', 'reviewed', 'approved'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $assembly->status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" rows="2" class="form-control">{{ old('remarks', $assembly->remarks) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Assembly Part Requirements</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-requirement-row">
                    <i class="bi bi-plus-circle me-1"></i>Add Row
                </button>
            </div>
            <div class="card-body">
                <div class="small text-body-secondary mb-3">
                    Use quick row entry for repeated segment requirements. Designers can build the whole segment map here without pre-assigning cut pieces to segments.
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="requirement-table">
                        <thead>
                            <tr>
                                <th style="width: 32%">Part Definition</th>
                                <th style="width: 12%">Qty</th>
                                <th style="width: 12%">UOM</th>
                                <th style="width: 12%">Seq</th>
                                <th style="width: 12%">Mandatory</th>
                                <th>Remarks</th>
                                <th style="width: 5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($oldRequirements as $index => $row)
                            <tr>
                                <td>
                                    <select name="requirements[{{ $index }}][part_definition_id]" class="form-select" data-erp-select data-placeholder="Select part">
                                        <option value="">Select Part</option>
                                        @foreach($partDefinitions as $part)
                                            <option value="{{ $part->id }}" @selected((string)($row['part_definition_id'] ?? '') === (string)$part->id)>
                                                {{ $part->part_code }} - {{ $part->part_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="0.001" min="0" name="requirements[{{ $index }}][required_qty]" class="form-control" value="{{ $row['required_qty'] ?? '' }}"></td>
                                <td>
                                    <select name="requirements[{{ $index }}][uom_id]" class="form-select" data-erp-select data-placeholder="UOM" data-allow-clear="1">
                                        <option value="">UOM</option>
                                        @foreach($uoms as $uom)
                                            <option value="{{ $uom->id }}" @selected((string)($row['uom_id'] ?? '') === (string)$uom->id)>{{ $uom->code }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" min="0" name="requirements[{{ $index }}][consumption_sequence]" class="form-control" value="{{ $row['consumption_sequence'] ?? $index + 1 }}"></td>
                                <td class="text-center"><input type="checkbox" value="1" name="requirements[{{ $index }}][is_mandatory]" @checked((bool)($row['is_mandatory'] ?? true))></td>
                                <td><input name="requirements[{{ $index }}][remarks]" class="form-control" value="{{ $row['remarks'] ?? '' }}"></td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex gap-2 position-sticky bottom-0 bg-body">
                <button type="submit" class="btn btn-primary">{{ $assembly->exists ? 'Update' : 'Create' }}</button>
                <a href="{{ route('projects.production-v2.assemblies.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>

    <template id="requirement-row-template">
        <tr>
            <td>
                <select class="form-select requirement-part" data-erp-select data-placeholder="Select part">
                    <option value="">Select Part</option>
                    @foreach($partDefinitions as $part)
                        <option value="{{ $part->id }}">{{ $part->part_code }} - {{ $part->part_name }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" step="0.001" min="0" class="form-control requirement-qty"></td>
            <td>
                <select class="form-select requirement-uom" data-erp-select data-placeholder="UOM" data-allow-clear="1">
                    <option value="">UOM</option>
                    @foreach($uoms as $uom)
                        <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" min="0" class="form-control requirement-seq"></td>
            <td class="text-center"><input type="checkbox" value="1" class="requirement-mandatory" checked></td>
            <td><input class="form-control requirement-remarks"></td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
        </tr>
    </template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.querySelector('#requirement-table tbody');
    const template = document.querySelector('#requirement-row-template');
    const addButton = document.querySelector('#add-requirement-row');

    const refreshNames = () => {
        [...tableBody.querySelectorAll('tr')].forEach((row, index) => {
            row.querySelector('.requirement-part, select[name*="[part_definition_id]"]')?.setAttribute('name', `requirements[${index}][part_definition_id]`);
            row.querySelector('.requirement-qty, input[name*="[required_qty]"]')?.setAttribute('name', `requirements[${index}][required_qty]`);
            row.querySelector('.requirement-uom, select[name*="[uom_id]"]')?.setAttribute('name', `requirements[${index}][uom_id]`);
            row.querySelector('.requirement-seq, input[name*="[consumption_sequence]"]')?.setAttribute('name', `requirements[${index}][consumption_sequence]`);
            row.querySelector('.requirement-mandatory, input[name*="[is_mandatory]"]')?.setAttribute('name', `requirements[${index}][is_mandatory]`);
            row.querySelector('.requirement-remarks, input[name*="[remarks]"]')?.setAttribute('name', `requirements[${index}][remarks]`);
        });
    };

    addButton?.addEventListener('click', () => {
        const clone = template.content.cloneNode(true);
        tableBody.appendChild(clone);
        refreshNames();
    });

    tableBody?.addEventListener('click', (event) => {
        const btn = event.target.closest('.remove-row');
        if (!btn) {
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
            rows[0].querySelectorAll('select').forEach((select) => {
                select.value = '';
            });
            return;
        }

        btn.closest('tr')?.remove();
        refreshNames();
    });

    refreshNames();
});
</script>
@endpush
