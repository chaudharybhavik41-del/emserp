@extends('layouts.erp')

@section('title', 'Create Stock Adjustment')

@php
$openingLines = old('opening_lines');
if (!is_array($openingLines) || count($openingLines) === 0) {
    $openingLines = [
        [
            'item_id' => '',
            'brand' => '',
            'uom_id' => '',
            'quantity' => '',
            'unit_rate' => '',
            'remarks' => '',
        ]
    ];
}

$adjustmentLines = old('adjustment_lines');
if (!is_array($adjustmentLines) || count($adjustmentLines) === 0) {
    $adjustmentLines = [
        [
            'store_stock_item_id' => '',
            'quantity' => '',
            'remarks' => '',
        ]
    ];
}

$selectedType = old('adjustment_type', 'opening');
@endphp

@section('content')
    <style>
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .total-box {
            border: 2px solid #28a745;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            min-width: 100px;
            font-weight: bold;
            color: #28a745;
            background-color: #f8fff9;
        }

        .uom-readonly {
            pointer-events: none;
            background-color: #f8f9fa !important;
        }

        /* Ensure Select2 dropdown is above other elements */
        .select2-container--open {
            z-index: 9999;
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Create Stock Adjustment</h1>
        <a href="{{ route('store-stock-adjustments.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    @if ($errors->has('general'))
        <div class="alert alert-danger">{{ $errors->first('general') }}</div>
    @endif

    @if ($errors->any() && !$errors->has('general'))
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('store-stock-adjustments.store') }}">
        @csrf

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Adjustment Date</label>
                        <input type="date" name="adjustment_date" class="form-control form-control-sm"
                               value="{{ old('adjustment_date', now()->format('Y-m-d')) }}" required autofocus>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Adjustment Type</label>
                        <select name="adjustment_type" id="adjustment_type" class="form-select form-select-sm" required>
                            <option value="opening" @selected($selectedType === 'opening')>Opening</option>
                            <option value="increase" @selected($selectedType === 'increase')>Increase</option>
                            <option value="decrease" @selected($selectedType === 'decrease')>Decrease</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Project</label>
                        <select name="project_id" class="form-select form-select-sm project-select">
                            <option></option> {{-- Hidden Placeholder for Select2 --}}
                            <option value="none" @selected((string)old('project_id') === 'none' || !old('project_id'))>None / General</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>
                                    {{ $project->code ? ($project->code . ' - ') : '' }}{{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control form-control-sm" value="{{ old('reason') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2">{{ old('remarks') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3 js-adjustment-section" id="openingSection">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Opening Lines</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddOpeningLine">Add line</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="openingTable">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 24%;">Item</th>
                            <th style="width: 18%;">Brand</th>
                            <th style="width: 10%;">UOM</th>
                            <th class="text-end" style="width: 12%;">Qty</th>
                            <th class="text-end" style="width: 12%;">Unit Rate</th>
                            <th class="text-end" style="width: 12%;">Amount</th>
                            <th>Remarks</th>
                            <th style="width: 4%;"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($openingLines as $index => $line)
                            @php
    $qty = $line['quantity'] ?? '';
    $rate = $line['unit_rate'] ?? '';
    $amount = ($qty !== '' && $rate !== '' && is_numeric($qty) && is_numeric($rate))
        ? number_format((float) $qty * (float) $rate, 2, '.', '')
        : '';
                            @endphp
                            <tr>
                                <td>
                                    <select name="opening_lines[{{ $index }}][item_id]" class="form-select form-select-sm opening-item" required>
                                        <option value="">-- select --</option>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}" @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>
                                                {{ $item->code ? ($item->code . ' - ') : '' }}{{ $item->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="opening_lines[{{ $index }}][brand]" class="form-select form-select-sm opening-brand-select">
                                        @if(!empty($line['brand']))
                                            <option value="{{ $line['brand'] }}" selected>{{ $line['brand'] }}</option>
                                        @endif
                                    </select>
                                </td>
                                <td>
                                    <select name="opening_lines[{{ $index }}][uom_id]" class="form-select form-select-sm opening-uom uom-readonly" required tabindex="-1">
                                        <option value="">-- select --</option>
                                        @foreach($uoms as $uom)

                                        
                                            <option value="{{ $uom->id }}" @selected((string) ($line['uom_id'] ?? '') ===(string) $uom->id)>{{ $uom->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.001" min="0.001"
                                           name="opening_lines[{{ $index }}][quantity]"
                                           class="form-control form-control-sm text-end opening-qty"
                                           value="{{ $qty }}" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0"
                                           name="opening_lines[{{ $index }}][unit_rate]"
                                           class="form-control form-control-sm text-end opening-rate"
                                           value="{{ $rate }}">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm text-end opening-amount" value="{{ $amount }}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="opening_lines[{{ $index }}][remarks]" class="form-control form-control-sm"
                                           value="{{ $line['remarks'] ?? '' }}">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-row">✕</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light border-top-0">
                        <tr>
                            <th colspan="3" class="text-end align-middle">Total</th>
                            <th class="text-end align-middle">
                                <span class="total-box" id="totalOpeningQty">0</span>
                            </th>
                            <th></th>
                            <th class="text-end align-middle">
                                <span class="total-box" id="totalOpeningAmount">0.00</span>
                            </th>
                            <th></th>
                            <th></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3 js-adjustment-section" id="adjustmentSection">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Increase / Decrease Lines</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddAdjustmentLine">Add line</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="adjustmentTable">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 56%;">Stock Item</th>
                            <th class="text-end" style="width: 14%;">Qty</th>
                            <th>Remarks</th>
                            <th style="width: 4%;"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($adjustmentLines as $index => $line)
                            <tr>
                                <td>
                                    <select name="adjustment_lines[{{ $index }}][store_stock_item_id]" class="form-select form-select-sm adjustment-stock-item" required>
                                        <option value="">-- select stock --</option>
                                        @foreach($stockItems as $stockItem)
                                            @php
        $stockLabel = trim(
            ($stockItem->item?->code ? ($stockItem->item->code . ' - ') : '') .
            ($stockItem->item?->name ?? ('Stock #' . $stockItem->id))
        );
        $meta = [];
        if (!empty($stockItem->brand)) {
            $meta[] = 'Brand: ' . $stockItem->brand;
        }
        if ($stockItem->project?->code) {
            $meta[] = 'Project: ' . $stockItem->project->code;
        } elseif ($stockItem->project?->name) {
            $meta[] = 'Project: ' . $stockItem->project->name;
        }
        $availableQty = $stockItem->weight_kg_available ?? $stockItem->qty_pcs_available;
        if ($availableQty !== null) {
            $meta[] = 'Avail: ' . number_format((float) $availableQty, 3);
        }
                                            @endphp
                                            <option value="{{ $stockItem->id }}" 
                                                    data-uom-id="{{ $stockItem->item?->uom_id }}"
                                                    @selected((string) ($line['store_stock_item_id'] ?? '') === (string) $stockItem->id)>
                                                {{ $stockLabel }}{{ $meta ? ' | ' . implode(' | ', $meta) : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.001" min="0.001"
                                           name="adjustment_lines[{{ $index }}][quantity]"
                                           class="form-control form-control-sm text-end adjustment-qty"
                                           value="{{ $line['quantity'] ?? '' }}" required>
                                </td>
                                <td>
                                    <input type="text" name="adjustment_lines[{{ $index }}][remarks]" class="form-control form-control-sm"
                                           value="{{ $line['remarks'] ?? '' }}">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-row">✕</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('store-stock-adjustments.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    @push('scripts')
    <script>
        window.__itemMeta = {!! $itemMetaJson !!};
        window.__uomMeta  = {!! $uomMetaJson !!};

        function getItemMeta(itemId) {
            return window.__itemMeta && window.__itemMeta[itemId] ? window.__itemMeta[itemId] : null;
        }

        function getUomMeta(uomId) {
            return window.__uomMeta && window.__uomMeta[uomId] ? window.__uomMeta[uomId] : null;
        }

        function updateQtyStep(row, uomId) {
            if (!row) return;
            const meta = getUomMeta(uomId);
            const dec  = meta ? (meta.decimal_places ?? 0) : 0;
            const step = dec > 0 ? (1 / Math.pow(10, dec)).toFixed(dec) : '1';
            
            const qtyInput = row.querySelector('.opening-qty, .adjustment-qty');
            if (qtyInput) {
                qtyInput.setAttribute('step', step);
                qtyInput.setAttribute('min', step);
            }
        }

        function populateBrandSelect(selectEl, itemId, currentValue = '') {
            if (!selectEl) {
                return;
            }

            const meta = getItemMeta(itemId);
            const brands = meta && Array.isArray(meta.brands) ? meta.brands : [];
            const finalValue = currentValue || selectEl.value || '';

            selectEl.innerHTML = '<option value="">-- any --</option>';

            brands.forEach(function (brand) {
                const opt = document.createElement('option');
                opt.value = brand;
                opt.textContent = brand;
                if (brand === finalValue) {
                    opt.selected = true;
                }
                selectEl.appendChild(opt);
            });

            if (finalValue && !brands.includes(finalValue)) {
                const opt = document.createElement('option');
                opt.value = finalValue;
                opt.textContent = finalValue;
                opt.selected = true;
                selectEl.appendChild(opt);
            }
        }

        function recalcOpeningRow(row) {
            if (!row) {
                return;
            }

            const qty = parseFloat(row.querySelector('.opening-qty')?.value || '0') || 0;
            const rate = parseFloat(row.querySelector('.opening-rate')?.value || '0') || 0;
            const amountEl = row.querySelector('.opening-amount');

            if (amountEl) {
                amountEl.value = (qty > 0 && rate > 0) ? (qty * rate).toFixed(2) : '';
            }
            recalcOpeningTotals();
        }

        function recalcOpeningTotals() {
            let totalQty = 0;
            let totalAmount = 0;
            document.querySelectorAll('#openingTable tbody tr').forEach(function (row) {
                const itemSelect = row.querySelector('.opening-item');
                const uomId = row.querySelector('.opening-uom')?.value;
                const qtyInput = row.querySelector('.opening-qty');
                
                let qty = parseFloat(qtyInput?.value || '0') || 0;
                
                // Enforce decimals if UOM is known
                if (uomId && qtyInput) {
                    const meta = getUomMeta(uomId);
                    const dec = meta ? (meta.decimal_places ?? 0) : 0;
                    if (qtyInput.value !== '' && !isNaN(qty)) {
                        const fixed = qty.toFixed(dec);
                        if (parseFloat(qtyInput.value) !== parseFloat(fixed)) {
                            // Only update if it actually changes to avoid cursor jumps on every keystroke
                            // better to do this on change/blur, but totals need the correct value
                        }
                    }
                }

                const amount = parseFloat(row.querySelector('.opening-amount')?.value || '0') || 0;
                totalQty += qty;
                totalAmount += amount;
            });
            const qtyEl = document.getElementById('totalOpeningQty');
            const amountEl = document.getElementById('totalOpeningAmount');
            if (qtyEl) qtyEl.textContent = totalQty.toFixed(3);
            if (amountEl) amountEl.textContent = totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function initOpeningRow(row) {
            if (!row) return;

            const itemSelect = row.querySelector('.opening-item');
            const brandSelect = row.querySelector('.opening-brand-select');
            const uomSelect = row.querySelector('.opening-uom');

            // Sync on initial load if item is already selected
            if (itemSelect && itemSelect.value) {
                const meta = getItemMeta(itemSelect.value);
                if (meta) {
                    if (meta.uom_id && uomSelect) {
                        uomSelect.value = meta.uom_id;
                        updateQtyStep(row, meta.uom_id);
                    }
                    populateBrandSelect(brandSelect, itemSelect.value, brandSelect ? brandSelect.value : '');
                }
            }

            row.addEventListener('input', function () {
                recalcOpeningRow(row);
            });

            recalcOpeningRow(row);
            recalcOpeningTotals();
        }

        // Global event delegation for opening item changes
        // This handles meta population (UOM, Brands) for both existing and dynamic rows
        jQuery(document).on('change select2:select', '.opening-item', function () {
            const $row = jQuery(this).closest('tr');
            const itemId = jQuery(this).val();
            if (!itemId) return;

            const meta = getItemMeta(itemId);
            if (meta) {
                // Update UOM
                const uomSelect = $row.find('.opening-uom')[0];
                if (uomSelect && meta.uom_id) {
                    uomSelect.value = meta.uom_id;
                    updateQtyStep($row[0], meta.uom_id);
                    jQuery(uomSelect).trigger('change');
                }
                // Update Brands
                const brandSelect = $row.find('.opening-brand-select')[0];
                if (brandSelect) {
                    populateBrandSelect(brandSelect, itemId, '');
                }
            }
        });

        // Enforce decimals on blur/change
        jQuery(document).on('change blur', '.opening-qty, .adjustment-qty', function() {
            const $row = jQuery(this).closest('tr');
            const val = jQuery(this).val();
            if (!val || isNaN(parseFloat(val))) return;

            let uomId = null;
            if (jQuery(this).hasClass('opening-qty')) {
                uomId = $row.find('.opening-uom').val();
            } else {
                uomId = $row.find('.adjustment-stock-item option:selected').data('uom-id');
            }

            if (uomId) {
                const meta = getUomMeta(uomId);
                const dec = meta ? (meta.decimal_places ?? 0) : 0;
                const fixed = parseFloat(val).toFixed(dec);
                if (val !== fixed) {
                    jQuery(this).val(fixed);
                }
            }
        });

        // Handle stock item changes for adjustment lines
        jQuery(document).on('change select2:select', '.adjustment-stock-item', function () {
            const $row = jQuery(this).closest('tr');
            const $option = jQuery(this).find('option:selected');
            const uomId = $option.data('uom-id');
            if (uomId) {
                updateQtyStep($row[0], uomId);
            }
        });

        function toggleAdjustmentSections() {
            const type = document.getElementById('adjustment_type')?.value || 'opening';
            const openingSection = document.getElementById('openingSection');
            const adjustmentSection = document.getElementById('adjustmentSection');

            if (openingSection) {
                const isOpening = (type === 'opening');
                openingSection.style.display = isOpening ? '' : 'none';
                openingSection.querySelectorAll('input, select, textarea').forEach(el => {
                    el.disabled = !isOpening;
                });
            }

            if (adjustmentSection) {
                const isAdj = (type !== 'opening');
                adjustmentSection.style.display = isAdj ? '' : 'none';
                adjustmentSection.querySelectorAll('input, select, textarea').forEach(el => {
                    el.disabled = !isAdj;
                });
            }
        }

        document.querySelectorAll('#openingTable tbody tr').forEach(initOpeningRow);

        let nextOpeningIndex = {{ count($openingLines) }};
        document.getElementById('btnAddOpeningLine')?.addEventListener('click', function () {
            const tbody = document.querySelector('#openingTable tbody');
            if (!tbody) {
                return;
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <select name="opening_lines[${nextOpeningIndex}][item_id]" class="form-select form-select-sm opening-item" required>
                        <option value="">-- select --</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->code ? ($item->code . ' - ') : '' }}{{ $item->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <select name="opening_lines[${nextOpeningIndex}][brand]" class="form-select form-select-sm opening-brand-select">
                        <option value="">-- any --</option>
                    </select>
                </td>
                <td>
                    <select name="opening_lines[${nextOpeningIndex}][uom_id]" class="form-select form-select-sm opening-uom uom-readonly" required tabindex="-1">
                        <option value="">-- select --</option>
                        @foreach($uoms as $uom)
                            <option value="{{ $uom->id }}">{{ $uom->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="number" step="0.001" min="0.001" name="opening_lines[${nextOpeningIndex}][quantity]" class="form-control form-control-sm text-end opening-qty" required>
                </td>
                <td>
                    <input type="number" step="0.01" min="0" name="opening_lines[${nextOpeningIndex}][unit_rate]" class="form-control form-control-sm text-end opening-rate">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm text-end opening-amount" readonly>
                </td>
                <td>
                    <input type="text" name="opening_lines[${nextOpeningIndex}][remarks]" class="form-control form-control-sm">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-row">✕</button>
                </td>
            `;

            tbody.appendChild(tr);
            initOpeningRow(tr);
            nextOpeningIndex++;
        });

        let nextAdjustmentIndex = {{ count($adjustmentLines) }};
        document.getElementById('btnAddAdjustmentLine')?.addEventListener('click', function () {
            const tbody = document.querySelector('#adjustmentTable tbody');
            if (!tbody) {
                return;
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <select name="adjustment_lines[${nextAdjustmentIndex}][store_stock_item_id]" class="form-select form-select-sm adjustment-stock-item" required>
                        <option value="">-- select stock --</option>
                        @foreach($stockItems as $stockItem)
                            @php
    $stockLabel = trim(
        ($stockItem->item?->code ? ($stockItem->item->code . ' - ') : '') .
        ($stockItem->item?->name ?? ('Stock #' . $stockItem->id))
    );
    $meta = [];
    if (!empty($stockItem->brand)) {
        $meta[] = 'Brand: ' . $stockItem->brand;
    }
    if ($stockItem->project?->code) {
        $meta[] = 'Project: ' . $stockItem->project->code;
    } elseif ($stockItem->project?->name) {
        $meta[] = 'Project: ' . $stockItem->project->name;
    }
    $availableQty = $stockItem->weight_kg_available ?? $stockItem->qty_pcs_available;
    if ($availableQty !== null) {
        $meta[] = 'Avail: ' . number_format((float) $availableQty, 3);
    }
                            @endphp
                            <option value="{{ $stockItem->id }}" data-uom-id="{{ $stockItem->item?->uom_id }}">{{ $stockLabel }}{{ $meta ? ' | ' . implode(' | ', $meta) : '' }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="number" step="0.001" min="0.001" name="adjustment_lines[${nextAdjustmentIndex}][quantity]" class="form-control form-control-sm text-end adjustment-qty" required>
                </td>
                <td>
                    <input type="text" name="adjustment_lines[${nextAdjustmentIndex}][remarks]" class="form-control form-control-sm">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-row">✕</button>
                </td>
            `;

            tbody.appendChild(tr);
            nextAdjustmentIndex++;
        });

        document.addEventListener('click', function (event) {
            const btn = event.target.closest('.js-remove-row');
            if (!btn) {
                return;
            }

            const row = btn.closest('tr');
            const tbody = row ? row.parentElement : null;
            if (!row || !tbody) {
                return;
            }

            if (tbody.children.length <= 1) {
                return;
            }

            const isOpeningRow = row.closest('#openingTable') !== null;
            row.remove();
            if (isOpeningRow) {
                recalcOpeningTotals();
            }
        });

        document.getElementById('adjustment_type')?.addEventListener('change', toggleAdjustmentSections);
        toggleAdjustmentSections();

        // Initialize Select2 for Project and Item dropdowns
        function initSelect2() {
            const $ = jQuery;

            // Global fix for Select2 search auto-focus
            $(document).on('select2:open', () => {
                const searchField = document.querySelector('.select2-container--open .select2-search__field');
                if (searchField) {
                    setTimeout(() => {
                        searchField.focus();
                    }, 50);
                }
            });

            // Global fix for opening Select2 on focus (tabbing)
            $(document).on('focus', '.select2-selection--single', function (e) {
                const $select = $(this).closest(".select2-container").siblings('select:enabled');
                if ($select.length > 0 && !$select.prop('readonly')) {
                    $select.select2('open');
                }
            });

            // Project Select2
            const projectSelect = $('.project-select');
            if (projectSelect.length > 0 && !projectSelect.data('select2')) {
                projectSelect.select2({
                    width: '100%',
                    allowClear: true,
                    placeholder: 'Select project...',
                    selectOnClose: true
                });
            }

            // Item Select2 for existing rows
            $('.opening-item').each(function() {
                const $select = $(this);
                if (!$select.data('select2')) {
                    $select.select2({
                        width: '100%',
                        allowClear: false,
                        selectOnClose: true
                    });
                }
            });
        }

        // Initialize on page load
        $(document).ready(initSelect2);

        // Re-initialize Select2 when adding new opening line
        const originalAddOpeningLine = document.getElementById('btnAddOpeningLine');
        if (originalAddOpeningLine) {
            originalAddOpeningLine.addEventListener('click', function() {
                setTimeout(() => {
                    const $ = jQuery;
                    const lastItemSelect = $('#openingTable tbody tr:last-child .opening-item');
                    if (lastItemSelect.length > 0 && !lastItemSelect.data('select2')) {
                        lastItemSelect.select2({
                            width: '100%',
                            allowClear: false,
                            selectOnClose: true
                        });
                    }
                }, 100);
            });
        }
    </script>
    @endpush

@endsection
