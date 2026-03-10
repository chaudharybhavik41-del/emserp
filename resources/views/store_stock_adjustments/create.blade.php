@extends('layouts.erp')

@section('title', 'Create Stock Adjustment')

@php
    $openingLines = old('opening_lines');
    if (!is_array($openingLines) || count($openingLines) === 0) {
        $openingLines = [[
            'item_id' => '',
            'brand' => '',
            'uom_id' => '',
            'quantity' => '',
            'unit_rate' => '',
            'remarks' => '',
        ]];
    }

    $adjustmentLines = old('adjustment_lines');
    if (!is_array($adjustmentLines) || count($adjustmentLines) === 0) {
        $adjustmentLines = [[
            'store_stock_item_id' => '',
            'quantity' => '',
            'remarks' => '',
        ]];
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
                               value="{{ old('adjustment_date', now()->format('Y-m-d')) }}" required>
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
                        <select name="project_id" class="form-select form-select-sm">
                            <option value="">-- None --</option>
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
                                    <select name="opening_lines[{{ $index }}][uom_id]" class="form-select form-select-sm" required>
                                        <option value="">-- select --</option>
                                        @foreach($uoms as $uom)
                                            <option value="{{ $uom->id }}" @selected((string) ($line['uom_id'] ?? '') === (string) $uom->id)>{{ $uom->name }}</option>
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
                                    <select name="adjustment_lines[{{ $index }}][store_stock_item_id]" class="form-select form-select-sm" required>
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
                                            <option value="{{ $stockItem->id }}" @selected((string) ($line['store_stock_item_id'] ?? '') === (string) $stockItem->id)>
                                                {{ $stockLabel }}{{ $meta ? ' | ' . implode(' | ', $meta) : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.001" min="0.001"
                                           name="adjustment_lines[{{ $index }}][quantity]"
                                           class="form-control form-control-sm text-end"
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

    <script>
        window.__itemMeta = {!! $itemMetaJson !!};

        function getItemMeta(itemId) {
            return window.__itemMeta && window.__itemMeta[itemId] ? window.__itemMeta[itemId] : null;
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

            if (!amountEl) {
                return;
            }

            amountEl.value = (qty > 0 && rate > 0) ? (qty * rate).toFixed(2) : '';
        }

        function initOpeningRow(row) {
            if (!row) {
                return;
            }

            const itemSelect = row.querySelector('.opening-item');
            const brandSelect = row.querySelector('.opening-brand-select');
            const uomSelect = row.querySelector('select[name*="[uom_id]"]');

            if (itemSelect) {
                populateBrandSelect(brandSelect, itemSelect.value, brandSelect ? brandSelect.value : '');

                itemSelect.addEventListener('change', function () {
                    populateBrandSelect(brandSelect, this.value, '');
                    const meta = getItemMeta(this.value);
                    if (meta && meta.uom_id && uomSelect && !uomSelect.value) {
                        uomSelect.value = String(meta.uom_id);
                    }
                });
            }

            row.addEventListener('input', function () {
                recalcOpeningRow(row);
            });

            recalcOpeningRow(row);
        }

        function toggleAdjustmentSections() {
            const type = document.getElementById('adjustment_type')?.value || 'opening';
            const openingSection = document.getElementById('openingSection');
            const adjustmentSection = document.getElementById('adjustmentSection');

            if (openingSection) {
                openingSection.style.display = type === 'opening' ? '' : 'none';
            }

            if (adjustmentSection) {
                adjustmentSection.style.display = type === 'opening' ? 'none' : '';
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
                    <select name="opening_lines[${nextOpeningIndex}][uom_id]" class="form-select form-select-sm" required>
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
                    <select name="adjustment_lines[${nextAdjustmentIndex}][store_stock_item_id]" class="form-select form-select-sm" required>
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
                            <option value="{{ $stockItem->id }}">{{ $stockLabel }}{{ $meta ? ' | ' . implode(' | ', $meta) : '' }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="number" step="0.001" min="0.001" name="adjustment_lines[${nextAdjustmentIndex}][quantity]" class="form-control form-control-sm text-end" required>
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

            row.remove();
        });

        document.getElementById('adjustment_type')?.addEventListener('change', toggleAdjustmentSections);
        toggleAdjustmentSections();
    </script>
@endsection
