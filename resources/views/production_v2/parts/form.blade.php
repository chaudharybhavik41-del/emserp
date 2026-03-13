@extends('layouts.erp')

@section('title', $part->exists ? 'Edit Production V2 Part' : 'Create Production V2 Part')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $part->exists ? 'Edit Part Definition' : 'Create Part Definition' }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.design_nav', ['project' => $project, 'active' => 'parts'])

    <div class="alert alert-info">
        Design owns part definition and raw attributes here. Route assignment and process planning are handled in the production module after design data is prepared.
        <a href="{{ route('projects.production-v2.route-planning.index', ['project' => $project->id]) }}" class="alert-link">Open Production Route Planning</a>
    </div>

    <form method="POST" action="{{ $part->exists ? route('projects.production-v2.parts.update', ['project' => $project->id, 'part' => $part->id]) : route('projects.production-v2.parts.store', ['project' => $project->id]) }}">
        @csrf
        @if($part->exists)
            @method('PUT')
        @endif
        <input type="hidden" name="revision_no" value="{{ old('revision_no', $part->revision_no ?: 1) }}">

        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-2">
                        <label class="form-label">Revision</label>
                        <input class="form-control" value="R{{ old('revision_no', $part->revision_no ?: 1) }}" readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Part Code</label>
                        <input name="part_code" class="form-control @error('part_code') is-invalid @enderror" value="{{ old('part_code', $part->part_code) }}" required>
                        @error('part_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Part Name</label>
                        <input name="part_name" class="form-control @error('part_name') is-invalid @enderror" value="{{ old('part_name', $part->part_name) }}" required>
                        @error('part_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Part Type</label>
                        <select name="part_type" class="form-select @error('part_type') is-invalid @enderror" data-erp-select data-hide-search="true">
                            @foreach(['cuttable_plate', 'section', 'bought_out', 'fabricated', 'consumable'] as $type)
                                <option value="{{ $type }}" @selected(old('part_type', $part->part_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('part_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="mt-2 d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-dark part-type-quick" data-part-type="cuttable_plate">Plate</button>
                            <button type="button" class="btn btn-sm btn-outline-dark part-type-quick" data-part-type="section">Section</button>
                            <button type="button" class="btn btn-sm btn-outline-dark part-type-quick" data-part-type="bought_out">Bought-out</button>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Required Qty</label>
                        <input type="number" step="0.001" min="0" name="required_qty" class="form-control @error('required_qty') is-invalid @enderror" value="{{ old('required_qty', $part->required_qty) }}" required>
                        @error('required_qty')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">UOM</label>
                        <select name="uom_id" class="form-select @error('uom_id') is-invalid @enderror" data-erp-select data-placeholder="Select UOM" data-allow-clear="1">
                            <option value="">Select UOM</option>
                            @foreach($uoms as $uom)
                                <option value="{{ $uom->id }}" @selected((string) old('uom_id', $part->uom_id) === (string) $uom->id)>{{ $uom->code }}</option>
                            @endforeach
                        </select>
                        @error('uom_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Material Grade</label>
                        <input name="material_grade" class="form-control" value="{{ old('material_grade', $part->material_grade) }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Material Category</label>
                        <input name="material_category" class="form-control" value="{{ old('material_category', $part->material_category) }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Material Item</label>
                        <select id="material_item_id" name="material_item_id" class="form-select @error('material_item_id') is-invalid @enderror" data-erp-select data-placeholder="Select material item" data-allow-clear="1">
                            <option value="">Select Material Item</option>
                            @foreach($items as $item)
                                <option
                                    value="{{ $item->id }}"
                                    data-grade="{{ $item->grade }}"
                                    data-thickness="{{ $item->thickness }}"
                                    data-density="{{ $item->density }}"
                                    data-weight-per-meter="{{ $item->weight_per_meter }}"
                                    data-uom-id="{{ $item->uom_id }}"
                                    data-type-code="{{ $item->type?->code }}"
                                    data-category-code="{{ $item->category?->code }}"
                                    @selected((string) old('material_item_id', $part->material_item_id) === (string) $item->id)
                                >
                                    {{ ($item->code ? $item->code . ' - ' : '') . $item->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('material_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">For plates and sections, item master grade, thickness, density, and wt/m are used to default part data and calculate unit weight.</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Drawing Ref</label>
                        <input name="drawing_ref" class="form-control" value="{{ old('drawing_ref', $part->drawing_ref) }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" data-erp-select data-hide-search="true">
                            @foreach(['draft', 'reviewed', 'approved'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $part->status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Thickness (mm)</label>
                        <input id="thickness_mm" type="number" step="0.001" min="0" name="thickness_mm" class="form-control" value="{{ old('thickness_mm', $part->thickness_mm) }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Width (mm)</label>
                        <input id="width_mm" type="number" step="0.001" min="0" name="width_mm" class="form-control" value="{{ old('width_mm', $part->width_mm) }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Length (mm)</label>
                        <input id="length_mm" type="number" step="0.001" min="0" name="length_mm" class="form-control" value="{{ old('length_mm', $part->length_mm) }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Unit Weight (kg)</label>
                        <input id="unit_weight_kg" type="number" step="0.001" min="0" name="unit_weight_kg" class="form-control" value="{{ old('unit_weight_kg', $part->unit_weight_kg) }}">
                        <div class="form-text" id="item_weight_help">Select a material item to auto-calculate from item master where possible.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control">{{ old('description', $part->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" rows="2" class="form-control">{{ old('remarks', $part->remarks) }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="row g-3">
                            <div class="col-12 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" id="is_interchangeable" name="is_interchangeable" @checked(old('is_interchangeable', $part->is_interchangeable))><label class="form-check-label" for="is_interchangeable">Interchangeable</label></div></div>
                            <div class="col-12 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" id="is_cuttable" name="is_cuttable" @checked(old('is_cuttable', $part->is_cuttable))><label class="form-check-label" for="is_cuttable">Cuttable</label></div></div>
                            <div class="col-12 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" id="is_section_item" name="is_section_item" @checked(old('is_section_item', $part->is_section_item))><label class="form-check-label" for="is_section_item">Section Item</label></div></div>
                            <div class="col-12 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" id="is_bought_out" name="is_bought_out" @checked(old('is_bought_out', $part->is_bought_out))><label class="form-check-label" for="is_bought_out">Bought Out</label></div></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2 position-sticky bottom-0 bg-body">
                <button type="submit" class="btn btn-primary">{{ $part->exists ? 'Update' : 'Create' }}</button>
                <a href="{{ route('projects.production-v2.parts.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemSelect = document.getElementById('material_item_id');
    const partType = document.querySelector('select[name="part_type"]');
    const partTypeQuickButtons = document.querySelectorAll('.part-type-quick');
    const gradeInput = document.querySelector('input[name="material_grade"]');
    const uomSelect = document.querySelector('select[name="uom_id"]');
    const thicknessInput = document.getElementById('thickness_mm');
    const widthInput = document.getElementById('width_mm');
    const lengthInput = document.getElementById('length_mm');
    const unitWeightInput = document.getElementById('unit_weight_kg');
    const weightHelp = document.getElementById('item_weight_help');

    if (! itemSelect || ! partType || !gradeInput || !thicknessInput || !widthInput || !lengthInput || !unitWeightInput) {
        return;
    }

    const toNumber = (value) => {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : null;
    };

    const updateCalculatedWeight = () => {
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const selectedPartType = (partType.value || '').toLowerCase();
        const density = toNumber(selectedOption?.dataset.density);
        const weightPerMeter = toNumber(selectedOption?.dataset.weightPerMeter);
        const thickness = toNumber(thicknessInput.value);
        const width = toNumber(widthInput.value);
        const length = toNumber(lengthInput.value);

        let computedWeight = null;
        let helpText = 'Select a material item to auto-calculate from item master where possible.';

        if (selectedPartType === 'cuttable_plate' && density && thickness && width && length) {
            computedWeight = (density * thickness * width * length) / 1000000000;
            helpText = `Calculated from item density ${density} kg/m3 and plate size.`;
        } else if (selectedPartType === 'section' && weightPerMeter && length) {
            computedWeight = weightPerMeter * (length / 1000);
            helpText = `Calculated from item wt/m ${weightPerMeter}.`;
        }

        if (computedWeight !== null && computedWeight > 0) {
            unitWeightInput.value = computedWeight.toFixed(3);
        }

        if (weightHelp) {
            weightHelp.textContent = helpText;
        }
    };

    const updateFromItem = () => {
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        if (! selectedOption || ! selectedOption.value) {
            if (weightHelp) {
                weightHelp.textContent = 'Select a material item to auto-calculate from item master where possible.';
            }
            return;
        }

        if (selectedOption.dataset.grade) {
            gradeInput.value = selectedOption.dataset.grade;
        }

        if (selectedOption.dataset.uomId && uomSelect && ! uomSelect.value) {
            uomSelect.value = selectedOption.dataset.uomId;
            uomSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const selectedPartType = (partType.value || '').toLowerCase();
        if ((selectedPartType === 'cuttable_plate' || selectedPartType === 'section') && selectedOption.dataset.thickness) {
            thicknessInput.value = selectedOption.dataset.thickness;
        }

        updateCalculatedWeight();
    };

    itemSelect.addEventListener('change', updateFromItem);
    partType.addEventListener('change', updateCalculatedWeight);
    partTypeQuickButtons.forEach((button) => {
        button.addEventListener('click', () => {
            partType.value = button.dataset.partType || '';
            partType.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
    thicknessInput.addEventListener('input', updateCalculatedWeight);
    widthInput.addEventListener('input', updateCalculatedWeight);
    lengthInput.addEventListener('input', updateCalculatedWeight);

    if (itemSelect.value) {
        updateFromItem();
    }
});
</script>
@endpush
