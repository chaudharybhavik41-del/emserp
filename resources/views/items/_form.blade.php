
@php
    /** @var \App\Models\Item|null $item */
    $isEdit = isset($item) && $item->exists;

    $selectedTypeId        = old('material_type_id', $item->material_type_id ?? '');
    $selectedCategoryId    = old('material_category_id', $item->material_category_id ?? '');
    $selectedSubcategoryId = old('material_subcategory_id', $item->material_subcategory_id ?? '');
    $selectedUomId         = old('uom_id', $item->uom_id ?? '');
    $defaultReorder       = $defaultReorder ?? null;
    $reorderMinQty        = old('reorder_min_qty', $defaultReorder?->min_qty ?? '');
    $reorderTargetQty     = old('reorder_target_qty', $defaultReorder?->target_qty ?? '');

@endphp

<form method="POST"
      action="{{ $isEdit ? route('items.update', $item) : route('items.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    {{-- Type / Category / Subcategory / UOM --}}
    <div class="row mb-3">
        <div class="col-md-3">
            <label for="material_type_id" class="form-label">
                Material Type <span class="text-danger">*</span>
            </label>
            <select name="material_type_id"
                    id="material_type_id"
                    class="form-select form-select-sm @error('material_type_id') is-invalid @enderror">
                <option value="">-- Select Type --</option>
                @foreach($types as $type)
                    <option value="{{ $type->id }}"
                        {{ (string) $selectedTypeId === (string) $type->id ? 'selected' : '' }}>
                        {{ $type->code }} - {{ $type->name }}
                    </option>
                @endforeach
            </select>
            @error('material_type_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="material_category_id" class="form-label">
                Category <span class="text-danger">*</span>
            </label>
            <select name="material_category_id"
                    id="material_category_id"
                    class="form-select form-select-sm @error('material_category_id') is-invalid @enderror">
                <option value="">-- Select Category --</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}"
                            data-type-id="{{ $category->material_type_id }}"
                        {{ (string) $selectedCategoryId === (string) $category->id ? 'selected' : '' }}>
                        {{ $category->code }} - {{ $category->name }}
                    </option>
                @endforeach
            </select>
            <div class="form-text">Filtered by selected material type.</div>
            @error('material_category_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="material_subcategory_id" class="form-label">
                Subcategory <span class="text-danger">*</span>
            </label>
            <select name="material_subcategory_id"
                    id="material_subcategory_id"
                    class="form-select form-select-sm @error('material_subcategory_id') is-invalid @enderror">
                <option value="">-- Select Subcategory --</option>
                @foreach($subcategories as $subcategory)
                    <option value="{{ $subcategory->id }}"
                            data-category-id="{{ $subcategory->material_category_id }}"
                        {{ (string) $selectedSubcategoryId === (string) $subcategory->id ? 'selected' : '' }}>
                        {{ $subcategory->code }} - {{ $subcategory->name }}
                    </option>
                @endforeach
            </select>
            <div class="form-text">Filtered by category.</div>
            @error('material_subcategory_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="uom_id" class="form-label">
                Base UOM <span class="text-danger">*</span>
            </label>
            <select name="uom_id"
                    id="uom_id"
                    class="form-select form-select-sm @error('uom_id') is-invalid @enderror">
                <option value="">-- Select UOM --</option>
                @foreach($uoms as $uom)
                    <option value="{{ $uom->id }}"
                        {{ (string) $selectedUomId === (string) $uom->id ? 'selected' : '' }}>
                        {{ $uom->code }} - {{ $uom->name }}
                    </option>
                @endforeach
            </select>
            @error('uom_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- Code / Name / Short Name / Active --}}
    <div class="row mb-3">
        <div class="col-md-3">
            <label class="form-label">Item Code</label>
            @if($isEdit)
                <input type="hidden" name="code" value="{{ $item->code }}">
                <input type="text"
                       class="form-control form-control-sm"
                       value="{{ $item->code }}"
                       disabled>
            @else
                <input type="text"
                       class="form-control form-control-sm"
                       value="Will be generated automatically"
                       disabled>
            @endif
            <div class="form-text">
                Code is auto-generated from category/subcategory; user cannot edit.
            </div>
        </div>

        <div class="col-md-4">
            <label for="name" class="form-label">
                Item Name <span class="text-danger">*</span>
            </label>
            <input type="text"
                   name="name"
                   id="name"
                   class="form-control form-control-sm @error('name') is-invalid @enderror"
                   value="{{ old('name', $item->name ?? '') }}"
                   maxlength="150">
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="short_name" class="form-label">Short Name</label>
            <input type="text"
                   name="short_name"
                   id="short_name"
                   class="form-control form-control-sm @error('short_name') is-invalid @enderror"
                   value="{{ old('short_name', $item->short_name ?? '') }}"
                   maxlength="100">
            @error('short_name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check">
                <input type="checkbox"
                       name="is_active"
                       id="is_active"
                       class="form-check-input"
                       value="1"
                    {{ old('is_active', $item->is_active ?? true) ? 'checked' : '' }}>
                <label for="is_active" class="form-check-label">Active</label>
            </div>
        </div>
    </div>

    {{-- Grade / Spec / Thickness / Size --}}
    <div class="row mb-3">
        <div class="col-md-3">
            <label for="grade" class="form-label">Grade</label>
            <input type="text"
                   name="grade"
                   id="grade"
                   class="form-control form-control-sm @error('grade') is-invalid @enderror"
                   value="{{ old('grade', $item->grade ?? '') }}"
                   maxlength="100">
            @error('grade')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="spec" class="form-label">Specification</label>
            <input type="text"
                   name="spec"
                   id="spec"
                   class="form-control form-control-sm @error('spec') is-invalid @enderror"
                   value="{{ old('spec', $item->spec ?? '') }}"
                   maxlength="100">
            @error('spec')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="thickness" class="form-label">Thickness (mm)</label>
            <input type="number"
                   step="0.001"
                   name="thickness"
                   id="thickness"
                   class="form-control form-control-sm @error('thickness') is-invalid @enderror"
                   value="{{ old('thickness', $item->thickness ?? '') }}"
                   min="0">
            @error('thickness')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="size" class="form-label">Size / Section</label>
            <input type="text"
                   name="size"
                   id="size"
                   class="form-control form-control-sm @error('size') is-invalid @enderror"
                   value="{{ old('size', $item->size ?? '') }}"
                   maxlength="100">
            @error('size')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- Density / Weight per Meter --}}
    <div class="row mb-3">
        <div class="col-md-3">
            <label for="density" class="form-label">Density</label>
            <input type="number"
                   step="0.0001"
                   name="density"
                   id="density"
                   class="form-control form-control-sm @error('density') is-invalid @enderror"
                   value="{{ old('density', $item->density ?? '') }}"
                   min="0">
            @error('density')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Optional.</div>
        </div>

        <div class="col-md-3">
            <label for="weight_per_meter" class="form-label">Weight / Meter</label>
            <input type="number"
                   step="0.0001"
                   name="weight_per_meter"
                   id="weight_per_meter"
                   class="form-control form-control-sm @error('weight_per_meter') is-invalid @enderror"
                   value="{{ old('weight_per_meter', $item->weight_per_meter ?? '') }}"
                   min="0">
            @error('weight_per_meter')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Used for sections / structural items.</div>
        </div>


        <div class="col-md-3">
            <label for="surface_area_per_meter" class="form-label">Surface Area / Meter (m²/m)</label>
            <input type="number"
                   step="0.0001"
                   name="surface_area_per_meter"
                   id="surface_area_per_meter"
                   class="form-control form-control-sm @error('surface_area_per_meter') is-invalid @enderror"
                   value="{{ old('surface_area_per_meter', $item->surface_area_per_meter ?? '') }}"
                   min="0">
            @error('surface_area_per_meter')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Used for surface area KPIs for sections.</div>
        </div>
    </div>

    {{-- Description --}}
    <div class="mb-3">
        <label for="description" class="form-label">Description</label>
        <textarea name="description"
                  id="description"
                  class="form-control form-control-sm @error('description') is-invalid @enderror"
                  rows="3">{{ old('description', $item->description ?? '') }}</textarea>
        @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Brands (tags) --}}
    <div class="mb-3">
        <label for="brands" class="form-label">Brands</label>
        <select name="brands[]"
                id="brands"
                class="form-select form-select-sm select2-tags @error('brands') is-invalid @enderror"
                multiple>
            @php
                $selectedBrands = collect(old('brands', $item->brands ?? []))->filter()->values()->all();
            @endphp

            @foreach($selectedBrands as $brand)
                <option value="{{ $brand }}" selected>{{ $brand }}</option>
            @endforeach

            @foreach(($brandTags ?? collect()) as $brand)
                @if(!in_array($brand, $selectedBrands, true))
                    <option value="{{ $brand }}">{{ $brand }}</option>
                @endif
            @endforeach
        </select>
        <div class="form-text">
            Type to add new brands; existing ones appear as suggestions.
        </div>
        @error('brands')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>


    {{-- Reorder levels (Low Stock) --}}
    <div class="row mb-3">
        <div class="col-md-3">
            <label for="reorder_min_qty" class="form-label">Reorder Min Qty</label>
            <input type="number"
                   step="0.001"
                   name="reorder_min_qty"
                   id="reorder_min_qty"
                   class="form-control form-control-sm @error('reorder_min_qty') is-invalid @enderror"
                   value="{{ $reorderMinQty }}"
                   min="0">
            @error('reorder_min_qty')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Low Stock triggers when Available ≤ Min.</div>
        </div>

        <div class="col-md-3">
            <label for="reorder_target_qty" class="form-label">Reorder Target Qty</label>
            <input type="number"
                   step="0.001"
                   name="reorder_target_qty"
                   id="reorder_target_qty"
                   class="form-control form-control-sm @error('reorder_target_qty') is-invalid @enderror"
                   value="{{ $reorderTargetQty }}"
                   min="0">
            @error('reorder_target_qty')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Suggested Indent qty = Target − Available.</div>
        </div>

        <div class="col-md-6 d-flex align-items-end">
            <div class="form-text">
                Applies to <strong>ALL brands combined</strong> (Option A). Used in Store → Low Stock and auto indent generation.
            </div>
        </div>
    </div>

    {{-- HSN & GST --}}
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="hsn_code" class="form-label">HSN Code</label>
            <input type="text"
                   name="hsn_code"
                   id="hsn_code"
                   class="form-control form-control-sm @error('hsn_code') is-invalid @enderror"
                   value="{{ old('hsn_code', $item->hsn_code ?? '') }}"
                   maxlength="20">
            @error('hsn_code')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="gst_rate_percent" class="form-label">GST %</label>
            <input type="number"
                   step="0.01"
                   name="gst_rate_percent"
                   id="gst_rate_percent"
                   class="form-control form-control-sm @error('gst_rate_percent') is-invalid @enderror"
                   value="{{ old('gst_rate_percent', $item->gst_rate_percent ?? '') }}"
                   min="0"
                   max="100">
            @error('gst_rate_percent')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="gst_effective_from" class="form-label">GST Effective From</label>
            <input type="date"
                   name="gst_effective_from"
                   id="gst_effective_from"
                   class="form-control form-control-sm"
                   value="{{ old('gst_effective_from') }}">
            <div class="form-text">
                Optional – used to store GST rate history.
            </div>
        </div>
    </div>

    {{-- Accounting Treatment (Override) --}}
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="accounting_usage_override" class="form-label">Accounting Treatment (Override)</label>
            <select name="accounting_usage_override" id="accounting_usage_override" class="form-select form-select-sm @error('accounting_usage_override') is-invalid @enderror">
                <option value="">Use Material Type Default</option>
                <option value="tool_stock" {{ old('accounting_usage_override', $item->accounting_usage_override ?? '') == 'tool_stock' ? 'selected' : '' }}>Short-term Tool Stock (Inventory)</option>
                <option value="fixed_asset" {{ old('accounting_usage_override', $item->accounting_usage_override ?? '') == 'fixed_asset' ? 'selected' : '' }}>Long-term Fixed Asset (Depreciable)</option>
                <option value="inventory" {{ old('accounting_usage_override', $item->accounting_usage_override ?? '') == 'inventory' ? 'selected' : '' }}>Inventory (Default)</option>
                <option value="expense" {{ old('accounting_usage_override', $item->accounting_usage_override ?? '') == 'expense' ? 'selected' : '' }}>Direct Expense</option>
            </select>
            @error('accounting_usage_override')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
                Use <strong>Tool Stock</strong> for grinders/drills etc (issued & returned). Use <strong>Fixed Asset</strong> for cranes/CNC etc (depreciation).
            </div>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-text">
                This affects accounting posting: Tool Stock → <code>INV-TOOLS</code>. Fixed Asset → <code>FA-MACHINERY</code>.
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('items.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
        <button type="submit" class="btn btn-primary btn-sm">
            {{ $isEdit ? 'Update Item' : 'Create Item' }}
        </button>
    </div>
</form>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var typeSelect        = document.getElementById('material_type_id');
            var categorySelect    = document.getElementById('material_category_id');
            var subcategorySelect = document.getElementById('material_subcategory_id');

            function filterCategoriesByType() {
                if (!typeSelect || !categorySelect) return;

                var typeId = typeSelect.value;

                Array.prototype.forEach.call(categorySelect.options, function (option) {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }

                    var optTypeId = option.getAttribute('data-type-id');
                    var visible   = !typeId || optTypeId === typeId;

                    option.hidden = !visible;
                    if (!visible && option.selected) {
                        option.selected = false;
                    }
                });

                filterSubcategoriesByCategory();
            }

            function filterSubcategoriesByCategory() {
                if (!categorySelect || !subcategorySelect) return;

                var categoryId = categorySelect.value;

                Array.prototype.forEach.call(subcategorySelect.options, function (option) {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }

                    var optCatId = option.getAttribute('data-category-id');
                    var visible  = !categoryId || optCatId === categoryId;

                    option.hidden = !visible;
                    if (!visible && option.selected) {
                        option.selected = false;
                    }
                });
            }

            if (typeSelect) {
                typeSelect.addEventListener('change', filterCategoriesByType);
            }
            if (categorySelect) {
                categorySelect.addEventListener('change', filterSubcategoriesByCategory);
            }

            filterCategoriesByType();
            filterSubcategoriesByCategory();

            // Brands select2 tags (jQuery + select2 are loaded globally in erp.blade.php)
            if (window.jQuery && typeof jQuery.fn.select2 !== 'undefined') {
                jQuery('#brands').select2({
                    tags: true,
                    tokenSeparators: [','],
                    placeholder: 'Select or type brands',
                    width: '100%',
                    allowClear: true
                });
            }
        });
    </script>
@endpush
