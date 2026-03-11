@extends('layouts.erp')

@section('title', 'Edit Stock Adjustment ' . ($adjustment->reference_number ?? ''))

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
        <h1 class="h4 mb-0">
            Edit Stock Adjustment
            @if($adjustment->reference_number)
                - {{ $adjustment->reference_number }}
            @endif
        </h1>
        <a href="{{ route('store-stock-adjustments.show', $adjustment) }}" class="btn btn-sm btn-outline-secondary">Back</a>
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

    <form method="POST" action="{{ route('store-stock-adjustments.update', $adjustment) }}">
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Adjustment Date</label>
                        <input type="date" name="adjustment_date" class="form-control form-control-sm"
                               value="{{ old('adjustment_date', optional($adjustment->adjustment_date)->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Adjustment Type</label>
                        <input type="text" class="form-control form-control-sm" value="{{ ucfirst($adjustment->adjustment_type) }}" readonly disabled>
                        <input type="hidden" name="adjustment_type" value="{{ $adjustment->adjustment_type }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Project</label>
                        <select name="project_id" class="form-select form-select-sm project-select">
                            <option></option> {{-- Hidden Placeholder for Select2 --}}
                            <option value="none" @selected((string)old('project_id', $adjustment->project_id) === 'none' || !old('project_id', $adjustment->project_id))>None / General</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected((string) old('project_id', $adjustment->project_id) === (string) $project->id)>
                                    {{ $project->code ? ($project->code . ' - ') : '' }}{{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control form-control-sm" value="{{ old('reason', $adjustment->reason) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2">{{ old('remarks', $adjustment->remarks) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
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
                        @php $idx = 0; @endphp
                        @foreach($adjustment->lines as $line)
                            @php
                                $qty = old("opening_lines.$idx.quantity", $line->quantity);
                                $rate = old("opening_lines.$idx.unit_rate", $line->stockItem?->opening_unit_rate);
                                $amount = ($qty !== '' && $rate !== '' && is_numeric($qty) && is_numeric($rate))
                                    ? number_format((float) $qty * (float) $rate, 2, '.', '')
                                    : '';
                            @endphp
                            <tr>
                                <td>
                                    <input type="hidden" name="opening_lines[{{ $idx }}][line_id]" value="{{ $line->id }}">
                                    <input type="hidden" name="opening_lines[{{ $idx }}][stock_item_id]" value="{{ $line->store_stock_item_id }}">
                                    
                                    <select name="opening_lines[{{ $idx }}][item_id]" class="form-select form-select-sm opening-item" required @if($line->id) disabled @endif>
                                        <option value="">-- select --</option>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}" @selected((string) old("opening_lines.$idx.item_id", $line->item_id) === (string) $item->id)>
                                                {{ $item->code ? ($item->code . ' - ') : '' }}{{ $item->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if($line->id)
                                        <input type="hidden" name="opening_lines[{{ $idx }}][item_id]" value="{{ $line->item_id }}">
                                    @endif
                                </td>
                                <td>
                                    <select name="opening_lines[{{ $idx }}][brand]" class="form-select form-select-sm opening-brand-select">
                                        @php $b = old("opening_lines.$idx.brand", $line->brand); @endphp
                                        @if($b)
                                            <option value="{{ $b }}" selected>{{ $b }}</option>
                                        @endif
                                    </select>
                                </td>
                                <td>
                                    <select name="opening_lines[{{ $idx }}][uom_id]" class="form-select form-select-sm opening-uom uom-readonly" required tabindex="-1">
                                        <option value="">-- select --</option>
                                        @foreach($uoms as $uom)
                                            <option value="{{ $uom->id }}" @selected((string) old("opening_lines.$idx.uom_id", $line->uom_id) === (string) $uom->id)>{{ $uom->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.001" min="0.001"
                                           name="opening_lines[{{ $idx }}][quantity]"
                                           class="form-control form-control-sm text-end opening-qty"
                                           value="{{ $qty }}" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0"
                                           name="opening_lines[{{ $idx }}][unit_rate]"
                                           class="form-control form-control-sm text-end opening-rate"
                                           value="{{ $rate }}">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm text-end opening-amount" value="{{ $amount }}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="opening_lines[{{ $idx }}][remarks]" class="form-control form-control-sm"
                                           value="{{ old("opening_lines.$idx.remarks", $line->remarks) }}">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-row">✕</button>
                                </td>
                            </tr>
                            @php $idx++; @endphp
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

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="{{ route('store-stock-adjustments.show', $adjustment) }}" class="btn btn-outline-secondary">Cancel</a>
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
            
            const qtyInput = row.querySelector('.opening-qty');
            if (qtyInput) {
                qtyInput.setAttribute('step', step);
                qtyInput.setAttribute('min', step);
            }
        }

        function populateBrandSelect(selectEl, itemId, currentValue = '') {
            if (!selectEl) return;

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
            if (!row) return;

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
                const qty = parseFloat(row.querySelector('.opening-qty')?.value || '0') || 0;
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

        jQuery(document).on('change select2:select', '.opening-item', function () {
            const $row = jQuery(this).closest('tr');
            const itemId = jQuery(this).val();
            if (!itemId) return;

            const meta = getItemMeta(itemId);
            if (meta) {
                const uomSelect = $row.find('.opening-uom')[0];
                if (uomSelect && meta.uom_id) {
                    uomSelect.value = meta.uom_id;
                    updateQtyStep($row[0], meta.uom_id);
                    jQuery(uomSelect).trigger('change');
                }
                const brandSelect = $row.find('.opening-brand-select')[0];
                if (brandSelect) {
                    populateBrandSelect(brandSelect, itemId, '');
                }
            }
        });

        jQuery(document).on('change', '.opening-uom', function () {
            const $row = jQuery(this).closest('tr');
            updateQtyStep($row[0], jQuery(this).val());
        });

        // Enforce decimals on blur/change
        jQuery(document).on('change blur', '.opening-qty', function() {
            const $row = jQuery(this).closest('tr');
            const val = jQuery(this).val();
            if (!val || isNaN(parseFloat(val))) return;

            const uomId = $row.find('.opening-uom').val();
            if (uomId) {
                const meta = getUomMeta(uomId);
                const dec = meta ? (meta.decimal_places ?? 0) : 0;
                const fixed = parseFloat(val).toFixed(dec);
                if (val !== fixed) {
                    jQuery(this).val(fixed);
                }
            }
        });

        document.querySelectorAll('#openingTable tbody tr').forEach(initOpeningRow);

        let nextOpeningIndex = {{ $idx }};
        document.getElementById('btnAddOpeningLine')?.addEventListener('click', function () {
            const tbody = document.querySelector('#openingTable tbody');
            if (!tbody) return;

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
            
            // Re-init Select2 if needed
            const $ = jQuery;
            const $newSelect = $(tr).find('.opening-item');
            if ($newSelect.length > 0 && !$newSelect.data('select2')) {
                $newSelect.select2({
                    width: '100%',
                    allowClear: false,
                    selectOnClose: true
                });
            }
        });

        document.addEventListener('click', function (event) {
            const btn = event.target.closest('.js-remove-row');
            if (!btn) return;

            const row = btn.closest('tr');
            if (!row) return;

            const tbody = row.parentElement;
            if (!tbody || tbody.children.length <= 1) return;

            row.remove();
            recalcOpeningTotals();
        });

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

            $('.project-select').select2({
                width: '100%',
                allowClear: true,
                placeholder: '-- select project --',
                selectOnClose: true
            });

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

        $(document).ready(initSelect2);
    </script>
    @endpush

@endsection
