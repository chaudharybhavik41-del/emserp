
@extends('layouts.erp')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Add Template Item</h4>
        <small class="text-muted">
            Template: {{ $bomTemplate->template_code }} - {{ $bomTemplate->name }}
        </small>
    </div>
    <a href="{{ route('bom-templates.show', $bomTemplate) }}" class="btn btn-outline-secondary btn-sm">
        Back to Template
    </a>
</div>

<div class="card">
    <div class="card-body">
        @if($parentItem)
            <div class="alert alert-info py-2">
                Parent Assembly:
                <strong>{{ $parentItem->item_code ?: 'N/A' }} - {{ $parentItem->description }}</strong>
            </div>
        @endif

        <form method="POST" action="{{ route('bom-templates.items.store', $bomTemplate) }}">
            @csrf

            <input type="hidden" name="parent_item_id" value="{{ $parentItem?->id }}">

            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Item Code</label>
                    <input type="text" name="item_code" class="form-control" value="{{ old('item_code', $item->item_code) }}">
                    @error('item_code') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Assembly Type</label>
                    <input type="text" name="assembly_type" class="form-control" value="{{ old('assembly_type', $item->assembly_type) }}" placeholder="GIRDER, PLATFORM, ...">
                    @error('assembly_type') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sequence No</label>
                    <input type="number" name="sequence_no" class="form-control" value="{{ old('sequence_no', $item->sequence_no) }}">
                    @error('sequence_no') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Material Category *</label>
                    <select name="material_category" class="form-select">
                        @foreach($materialCategories as $cat)
                            <option value="{{ $cat->value }}" @selected(old('material_category', $item->material_category?->value) === $cat->value)>
                                {{ ucfirst(str_replace('_', ' ', $cat->value)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('material_category') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description *</label>
                <textarea name="description" class="form-control" rows="2" required>{{ old('description', $item->description) }}</textarea>
                @error('description') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Linked Item (Raw Material)</label>
                    @include('bom-templates.items.partials._raw_item_select', [
                        'rawItems' => $rawItems,
                        'item' => $item,
                    ])
                    @error('item_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">UOM</label>
                    <select name="uom_id" class="form-select">
                        <option value="">-- Select UOM --</option>
                        @foreach($uoms as $uom)
                            <option value="{{ $uom->id }}" @selected(old('uom_id', $item->uom_id) == $uom->id)>
                                {{ $uom->code }}
                            </option>
                        @endforeach
                    </select>
                    @error('uom_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Grade</label>
                    <input type="text" name="grade" class="form-control" value="{{ old('grade', $item->grade) }}" placeholder="e.g. IS2062 E250">
                    @error('grade') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>

            <h6 class="mt-4">Dimensions (optional)</h6>
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Thickness (mm)</label>
                    <input type="number" step="0.01" name="dimensions[thickness_mm]" class="form-control" value="{{ old('dimensions.thickness_mm', $item->dimensions['thickness_mm'] ?? '') }}">
                    @error('dimensions.thickness_mm') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Width (mm)</label>
                    <input type="number" step="0.01" name="dimensions[width_mm]" class="form-control" value="{{ old('dimensions.width_mm', $item->dimensions['width_mm'] ?? '') }}">
                    @error('dimensions.width_mm') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Length (mm)</label>
                    <input type="number" step="0.01" name="dimensions[length_mm]" class="form-control" value="{{ old('dimensions.length_mm', $item->dimensions['length_mm'] ?? '') }}">
                    @error('dimensions.length_mm') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Section (e.g. ISMC 300)</label>
                    <input type="text" name="dimensions[section]" class="form-control" value="{{ old('dimensions.section', $item->dimensions['section'] ?? '') }}">
                    @error('dimensions.section') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Span Length (m)</label>
                    <input type="number" step="0.01" name="dimensions[span_length_m]" class="form-control" value="{{ old('dimensions.span_length_m', $item->dimensions['span_length_m'] ?? '') }}">
                    @error('dimensions.span_length_m') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Depth (mm)</label>
                    <input type="number" step="0.01" name="dimensions[depth_mm]" class="form-control" value="{{ old('dimensions.depth_mm', $item->dimensions['depth_mm'] ?? '') }}">
                    @error('dimensions.depth_mm') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Leaves</label>
                    <input type="number" step="1" name="dimensions[leaves]" class="form-control" value="{{ old('dimensions.leaves', $item->dimensions['leaves'] ?? '') }}">
                    @error('dimensions.leaves') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>

            <h6 class="mt-4">Quantities & Weights</h6>
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Quantity *</label>
                    <input type="number" step="0.01" name="quantity" class="form-control" value="{{ old('quantity', $item->quantity) }}" required>
                    @error('quantity') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit Weight (kg)</label>
                    <input type="number" step="0.001" name="unit_weight" class="form-control" value="{{ old('unit_weight', $item->unit_weight) }}">
                    @error('unit_weight') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Total Weight (kg)</label>
                    <input type="number" step="0.001" name="total_weight" class="form-control" value="{{ old('total_weight', $item->total_weight) }}">
                    @error('total_weight') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Scrap %</label>
                    <input type="number" step="0.01" name="scrap_percentage" class="form-control" value="{{ old('scrap_percentage', $item->scrap_percentage) }}">
                    @error('scrap_percentage') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>


            <hr class="my-4">
            <h6>KPI Metrics</h6>
            <div class="text-muted small mb-3">
                Used for production analysis (area m², cutting meters, welding meters). Leave unit fields blank to auto-calculate where possible.
            </div>

            @php
                $kpiQty = (float) old('quantity', $item->quantity ?? 0);

                $kpiUnitArea = old('unit_area_m2', $item->unit_area_m2 ?? null);
                $kpiTotalArea = old('total_area_m2', $item->total_area_m2 ?? (is_numeric($kpiUnitArea) ? ((float) $kpiUnitArea * $kpiQty) : null));

                $kpiUnitCut = old('unit_cut_length_m', $item->unit_cut_length_m ?? null);
                $kpiTotalCut = old('total_cut_length_m', $item->total_cut_length_m ?? (is_numeric($kpiUnitCut) ? ((float) $kpiUnitCut * $kpiQty) : null));

                $kpiUnitWeld = old('unit_weld_length_m', $item->unit_weld_length_m ?? null);
                $kpiTotalWeld = old('total_weld_length_m', $item->total_weld_length_m ?? (is_numeric($kpiUnitWeld) ? ((float) $kpiUnitWeld * $kpiQty) : null));
            @endphp

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="unit_area_m2">Unit Area (m²)</label>
                    <input type="number" step="0.0001" min="0" name="unit_area_m2" id="unit_area_m2" class="form-control" value="{{ old('unit_area_m2', $item->unit_area_m2) }}">
                    <div class="form-text">Auto for plates/sections. Leave blank to auto.</div>
                    @error('unit_area_m2') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Total Area (m²)</label>
                    <input type="number" step="0.0001" class="form-control" value="{{ $kpiTotalArea }}" disabled>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="unit_cut_length_m">Unit Cut Length (m)</label>
                    <input type="number" step="0.0001" min="0" name="unit_cut_length_m" id="unit_cut_length_m" class="form-control" value="{{ old('unit_cut_length_m', $item->unit_cut_length_m) }}">
                    <div class="form-text">Auto for plates (perimeter × factor).</div>
                    @error('unit_cut_length_m') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Total Cut Length (m)</label>
                    <input type="number" step="0.0001" class="form-control" value="{{ $kpiTotalCut }}" disabled>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="unit_weld_length_m">Unit Weld Length (m)</label>
                    <input type="number" step="0.0001" min="0" name="unit_weld_length_m" id="unit_weld_length_m" class="form-control" value="{{ old('unit_weld_length_m', $item->unit_weld_length_m) }}">
                    <div class="form-text">Manual (depends on joint design).</div>
                    @error('unit_weld_length_m') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Total Weld Length (m)</label>
                    <input type="number" step="0.0001" class="form-control" value="{{ $kpiTotalWeld }}" disabled>
                </div>
            </div>

            <h6 class="mt-4">Procurement</h6>
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Procurement Type *</label>
                    <select name="procurement_type" class="form-select">
                        @foreach(\App\Enums\BomItemProcurementType::cases() as $pt)
                            <option value="{{ $pt->value }}" @selected(old('procurement_type', $item->procurement_type?->value) === $pt->value)>
                                {{ ucfirst($pt->value) }}
                            </option>
                        @endforeach
                    </select>
                    @error('procurement_type') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Material Source *</label>
                    <select name="material_source" class="form-select">
                        @foreach(\App\Enums\BomItemMaterialSource::cases() as $ms)
                            <option value="{{ $ms->value }}" @selected(old('material_source', $item->material_source?->value) === $ms->value)>
                                {{ ucfirst(str_replace('_', ' ', $ms->value)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('material_source') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $item->remarks) }}</textarea>
                    @error('remarks') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                Save Item
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Prevent mouse wheel from changing number inputs while scrolling
        document.querySelectorAll('input[type="number"]').forEach(function (el) {
            el.addEventListener('wheel', function (e) {
                if (document.activeElement === el) {
                    e.preventDefault();
                }
            }, { passive: false });
        });

        const catSelect = document.querySelector('select[name="material_category"]');
        const qtyInput = document.querySelector('input[name="quantity"]');

        const intCats = new Set([
            'fabricated_assembly',
            'steel_plate',
            'steel_section',
        ]);

        function isIntCategory() {
            return catSelect && intCats.has((catSelect.value || '').toLowerCase());
        }

        function updateQtyStep() {
            if (!qtyInput) return;
            qtyInput.step = isIntCategory() ? '1' : '0.01';
        }

        if (catSelect) {
            catSelect.addEventListener('change', updateQtyStep);
        }

        if (qtyInput) {
            qtyInput.addEventListener('blur', function () {
                if (!isIntCategory()) return;
                if (qtyInput.value === '') return;
                const n = parseFloat(qtyInput.value);
                if (!Number.isFinite(n)) return;
                qtyInput.value = String(Math.round(n));
            });
        }

        updateQtyStep();
    });
</script>
@endpush
