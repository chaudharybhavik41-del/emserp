@php
    /** @var \App\Models\BomItem $item */
    $isEdit = $item->exists;
@endphp

<input type="hidden" name="parent_item_id" value="{{ old('parent_item_id', $item->parent_item_id) }}">

<div class="mb-3">
    <label class="form-label">Parent</label>
    <input type="text"
           class="form-control"
           value="{{ $parentItem?->indented_description ?? 'Top-level assembly' }}"
           disabled>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <label class="form-label" for="item_code">Item Code</label>
        <input type="text"
               name="item_code"
               id="item_code"
               class="form-control"
               value="{{ old('item_code', $item->item_code) }}">
        @error('item_code') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="assembly_type">Assembly Type</label>
        <input type="text"
               name="assembly_type"
               id="assembly_type"
               class="form-control"
               value="{{ old('assembly_type', $item->assembly_type) }}"
               placeholder="GIRDER, PLATFORM, POLE...">
        @error('assembly_type') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label" for="sequence_no">Sequence No</label>
        <input type="number"
               step="1"
               min="0"
               name="sequence_no"
               id="sequence_no"
               class="form-control"
               value="{{ old('sequence_no', (!empty($item->sequence_no) && (int)$item->sequence_no > 0) ? $item->sequence_no : '') }}"
               placeholder="Auto">
        <small class="text-muted">Leave blank/0 to auto-assign.</small>
        @error('sequence_no') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="material_category">Material Category *</label>
        <select name="material_category" id="material_category" class="form-select">
            @foreach($materialCategories as $cat)
                <option value="{{ $cat->value }}"
                    @selected(old('material_category', $item->material_category?->value) === $cat->value)>
                    {{ ucfirst(str_replace('_', ' ', $cat->value)) }}
                </option>
            @endforeach
        </select>
        @error('material_category') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="description">Description *</label>
    <textarea name="description"
              id="description"
              class="form-control"
              rows="2"
              required>{{ old('description', $item->description) }}</textarea>
    @error('description') <div class="text-danger small">{{ $message }}</div> @enderror
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label" for="item_id">Linked Item</label>
        <select name="item_id" id="item_id" class="form-select">
            <option value="">-- Select Item --</option>
            @foreach($rawItems as $raw)
                <option value="{{ $raw->id }}"
                    @selected(old('item_id', $item->item_id) == $raw->id)>
                    {{ $raw->code }} - {{ $raw->name }} @if($raw->type) ({{ strtoupper($raw->type->code) }}) @endif
                </option>
            @endforeach
        </select>
        <small class="text-muted">
            For <strong>Steel Plate</strong> / <strong>Steel Section</strong>, this must be a RAW material item.<br>
            For <strong>Bought Out</strong> / <strong>Consumable</strong>, you can link any item (RAW or non-RAW).
        </small>
        @error('item_id') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="uom_id">UOM</label>
        <select name="uom_id" id="uom_id" class="form-select">
            <option value="">-- Select UOM --</option>
            @foreach($uoms as $uom)
                <option value="{{ $uom->id }}"
                    @selected(old('uom_id', $item->uom_id) == $uom->id)>
                    {{ $uom->code }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">
            If left blank, it will be picked from the selected item.
        </small>
        @error('uom_id') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="grade">Grade</label>
        <input type="text"
               name="grade"
               id="grade"
               class="form-control"
               value="{{ old('grade', $item->grade) }}"
               placeholder="e.g. IS2062 E250">
        <small class="text-muted">
            If left blank, it will be picked from the selected item.
        </small>
        @error('grade') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
</div>

<hr>
<h6>Dimensions (optional)</h6>
<small class="text-muted d-block mb-2">
    For plates: thickness + width + length + density → auto weight.  
    For sections: weight per meter + length → auto weight.
</small>

@php
    $dims = is_array($item->dimensions) ? $item->dimensions : [];
@endphp

<div class="row mb-3">
    <div class="col-md-3">
        <label class="form-label">Thickness (mm)</label>
        <input type="number"
               step="0.01"
               name="dimensions[thickness_mm]"
               class="form-control"
               value="{{ old('dimensions.thickness_mm', $dims['thickness_mm'] ?? '') }}">
        @error('dimensions.thickness_mm') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Width (mm)</label>
        <input type="number"
               step="0.01"
               name="dimensions[width_mm]"
               class="form-control"
               value="{{ old('dimensions.width_mm', $dims['width_mm'] ?? '') }}">
        @error('dimensions.width_mm') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Length (mm)</label>
        <input type="number"
               step="0.01"
               name="dimensions[length_mm]"
               class="form-control"
               value="{{ old('dimensions.length_mm', $dims['length_mm'] ?? '') }}">
        @error('dimensions.length_mm') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Section (e.g. ISMC 300)</label>
        <input type="text"
               name="dimensions[section]"
               class="form-control"
               value="{{ old('dimensions.section', $dims['section'] ?? '') }}">
        @error('dimensions.section') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <label class="form-label">Span Length (m)</label>
        <input type="number"
               step="0.01"
               name="dimensions[span_length_m]"
               class="form-control"
               value="{{ old('dimensions.span_length_m', $dims['span_length_m'] ?? '') }}">
        @error('dimensions.span_length_m') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Depth (mm)</label>
        <input type="number"
               step="0.01"
               name="dimensions[depth_mm]"
               class="form-control"
               value="{{ old('dimensions.depth_mm', $dims['depth_mm'] ?? '') }}">
        @error('dimensions.depth_mm') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Leaves</label>
        <input type="number"
               step="1"
               name="dimensions[leaves]"
               class="form-control"
               value="{{ old('dimensions.leaves', $dims['leaves'] ?? '') }}">
        @error('dimensions.leaves') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
</div>

<hr>
<h6>Quantities & Weights</h6>

<div class="row mb-3">
    <div class="col-md-3">
        <label class="form-label" for="quantity">Quantity *</label>
        <input type="number"
               step="0.01"
               name="quantity"
               id="quantity"
               class="form-control"
               value="{{ old('quantity', $item->quantity ?? 1) }}"
               required>
        @error('quantity') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="unit_weight">Unit Weight (kg)</label>
        <input type="number"
               step="0.001"
               name="unit_weight"
               id="unit_weight"
               class="form-control"
               value="{{ old('unit_weight', $item->unit_weight) }}">
        <small class="text-muted">
            If left blank and dimensions + density / weight-per-meter are available,
            it will be auto-calculated.
        </small>
        @error('unit_weight') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="total_weight">Total Weight (kg)</label>
        <input type="number"
               step="0.001"
               name="total_weight"
               id="total_weight"
               class="form-control"
               value="{{ old('total_weight', $item->total_weight) }}">
        <small class="text-muted">
            Auto-calculated as <strong>Qty × Unit</strong> (kept in sync on save when unit weight is available).
        </small>
        @error('total_weight') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="scrap_percentage">Scrap %</label>
        <input type="number"
               step="0.01"
               name="scrap_percentage"
               id="scrap_percentage"
               class="form-control"
               value="{{ old('scrap_percentage', $item->scrap_percentage ?? 0) }}">
        @error('scrap_percentage') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
</div>

<hr>

<hr class="my-4">

<h6>KPI Metrics</h6>
<div class="text-muted small mb-3">
    Used for production analysis (area m², cutting meters, welding meters). Leave unit fields blank to auto-calculate where possible.
</div>

@php
    $kpiQty = (float) old('quantity', $item->quantity ?? 0);

    $kpiUnitArea  = old('unit_area_m2', $item->unit_area_m2 ?? null);
    $kpiTotalArea = old('total_area_m2', $item->total_area_m2 ?? (is_numeric($kpiUnitArea) ? ((float) $kpiUnitArea * $kpiQty) : null));

    $kpiUnitCut  = old('unit_cut_length_m', $item->unit_cut_length_m ?? null);
    $kpiTotalCut = old('total_cut_length_m', $item->total_cut_length_m ?? (is_numeric($kpiUnitCut) ? ((float) $kpiUnitCut * $kpiQty) : null));

    $kpiUnitWeld  = old('unit_weld_length_m', $item->unit_weld_length_m ?? null);
    $kpiTotalWeld = old('total_weld_length_m', $item->total_weld_length_m ?? (is_numeric($kpiUnitWeld) ? ((float) $kpiUnitWeld * $kpiQty) : null));
@endphp

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <label class="form-label" for="unit_area_m2">Unit Area (m²)</label>
        <input type="number" step="0.0001" min="0" class="form-control" name="unit_area_m2" id="unit_area_m2" value="{{ old('unit_area_m2', $item->unit_area_m2) }}">
        <div class="form-text">Auto for plates/sections (if left blank, system will compute).</div>
        @error('unit_area_m2')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Total Area (m²)</label>
        <input type="number" step="0.0001" class="form-control" value="{{ $kpiTotalArea }}" disabled>
    </div>

    <div class="col-md-3">
        <label class="form-label" for="unit_cut_length_m">Unit Cut Length (m)</label>
        <input type="number" step="0.0001" min="0" class="form-control" name="unit_cut_length_m" id="unit_cut_length_m" value="{{ old('unit_cut_length_m', $item->unit_cut_length_m) }}">
        <div class="form-text">Auto for plates (perimeter × factor).</div>
        @error('unit_cut_length_m')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Total Cut Length (m)</label>
        <input type="number" step="0.0001" class="form-control" value="{{ $kpiTotalCut }}" disabled>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <label class="form-label" for="unit_weld_length_m">Unit Weld Length (m)</label>
        <input type="number" step="0.0001" min="0" class="form-control" name="unit_weld_length_m" id="unit_weld_length_m" value="{{ old('unit_weld_length_m', $item->unit_weld_length_m) }}">
        <div class="form-text">Manual (depends on joint design).</div>
        @error('unit_weld_length_m')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Total Weld Length (m)</label>
        <input type="number" step="0.0001" class="form-control" value="{{ $kpiTotalWeld }}" disabled>
    </div>
</div>

<hr>
<h6>Procurement</h6>

<div class="row mb-3">
    <div class="col-md-3">
        <label class="form-label" for="procurement_type">Procurement Type *</label>
        <select name="procurement_type" id="procurement_type" class="form-select">
            @foreach(\App\Enums\BomItemProcurementType::cases() as $pt)
                <option value="{{ $pt->value }}"
                    @selected(old('procurement_type', $item->procurement_type?->value) === $pt->value)>
                    {{ ucfirst($pt->value) }}
                </option>
            @endforeach
        </select>
        @error('procurement_type') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="material_source">Material Source *</label>
        <select name="material_source" id="material_source" class="form-select">
            @foreach(\App\Enums\BomItemMaterialSource::cases() as $ms)
                <option value="{{ $ms->value }}"
                    @selected(old('material_source', $item->material_source?->value) === $ms->value)>
                    {{ ucfirst(str_replace('_', ' ', $ms->value)) }}
                </option>
            @endforeach
        </select>
        @error('material_source') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="remarks">Remarks</label>
        <textarea name="remarks"
                  id="remarks"
                  class="form-control"
                  rows="2">{{ old('remarks', $item->remarks) }}</textarea>
        @error('remarks') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-12">
        <div class="form-check">
            <input type="hidden" name="is_billable" value="0">
            <input class="form-check-input" type="checkbox" id="is_billable" name="is_billable" value="1"
                   @checked(old('is_billable', $item->is_billable ?? 1))>
            <label class="form-check-label" for="is_billable">
                Billable / Include in Dispatch Weight (deliverable to client)
            </label>
            <div class="form-text">
                Uncheck for purely consumable items (e.g. welding rod, gas) that should not be counted in dispatch weight.
            </div>
        </div>
        @error('is_billable') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Prevent mouse wheel from changing number inputs while scrolling
        document.querySelectorAll('input[type="number"]').forEach(function (el) {
            el.addEventListener('wheel', function () {
                if (document.activeElement === el) {
                    el.blur();
                }
            });
        });

        // Quantity step: keep integer steps for plates/sections/assemblies
        const cat = document.getElementById('material_category');
        const qty = document.getElementById('quantity');
        if (cat && qty) {
            const integerCats = ['fabricated_assembly', 'steel_plate', 'steel_section'];
            const applyQtyStep = function () {
                const v = cat.value;
                qty.step = integerCats.includes(v) ? '1' : '0.01';
            };
            cat.addEventListener('change', applyQtyStep);
            applyQtyStep();
        }
    });
</script>
@endpush

