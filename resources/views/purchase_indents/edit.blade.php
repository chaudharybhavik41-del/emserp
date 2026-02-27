@extends('layouts.erp')

@section('title', 'Edit Purchase Indent ' . $indent->code)

@section('content')
                        <style>
                            /* Select2 inside table: match Bootstrap .form-select-sm height */
                            .select2-container {
                                width: 100% !important;
                            }

                            .select2-container--default .select2-selection--single {
                                height: calc(1.5em + .5rem + 2px) !important;
                                padding: .25rem .5rem;
                            }

                            .select2-container--default .select2-selection--single .select2-selection__rendered {
                                line-height: 1.5em !important;
                                padding-left: 0 !important;
                                padding-right: 0 !important;
                            }

                            .select2-container--default .select2-selection--single .select2-selection__arrow {
                                height: calc(1.5em + .5rem + 2px) !important;
                            }

                            /* Make dropdown search look less like a separate textbox */
                            .select2-container--default .select2-search--dropdown {
                                padding: 4px 6px;
                                border-bottom: 1px solid #dee2e6;
                                background: #fff;
                            }

                            .select2-container--default .select2-search--dropdown .select2-search__field {
                                border: 0 !important;
                                outline: none !important;
                                box-shadow: none !important;
                                padding: 4px 6px !important;
                                background: #f8f9fa;
                                border-radius: 4px;
                            }

                            /* Scrollable Items Table */
                    .items-table-wrapper {
                        max-height: 450px;     /* Adjust height as needed */
                        overflow-y: auto;
                    }

                    /* Sticky Table Header */
                    #items-table thead th {
                        position: sticky;
                        top: 0;
                        background: #f8f9fa;   /* Same as table-light */
                        z-index: 5;
                    }
                .items-table-wrapper {
                    max-height: 450px;
                    overflow: auto;   /* both X and Y scroll */
                }

                .items-wrapper {
                max-height: 500px;
                overflow: auto;
                border-radius: 8px;
                border: 1px solid #dee2e6;
            }

            /* Header */
            #items-table thead th {
                position: sticky;
                top: 0;
                z-index: 10;
                background: #f8f9fa;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: .4px;
                white-space: nowrap;
            }

            /* Row hover */
            #items-table tbody tr:hover {
                background-color: #f1f3f5;
            }

            /* Inputs */
            #items-table .form-control-sm,
            #items-table .form-select-sm {
                font-size: 13px;
                border-radius: 4px;
            }

            /* Compact inputs */
            #items-table td {
                padding: 6px 6px;
                vertical-align: middle;
            }

            /* Badge style */
            #items-table .badge {
                font-size: 11px;
                font-weight: 500;
                border-radius: 4px;
            }

            /* Density & WPM subtle */
            .density-badge,
            .wpm-badge {
                background-color: #eef1f6 !important;
                color: #495057 !important;
            }

            /* UOM badge */
            .uom-code-badge {
                background-color: #e7f1ff !important;
                color: #0d6efd !important;
            }

            /* Order Qty Highlight */
            .order-qty-input {
                font-weight: 600;
            }

            /* Remove button proper style */
            .remove-row-btn {
                width: 26px;
                height: 26px;
                padding: 0;
                font-size: 16px;
                line-height: 1;
                border-radius: 50%;
            }

            /* Small calculation hint */
            .calc-hint {
                font-size: 11px;
                color: #6c757d;
            }

            #items-table td input,
            #items-table td select {
                width: 100%;
                min-width: 110px;
            }

                        </style>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h1 class="h4 mb-0">Edit Purchase Indent {{ $indent->code }}</h1>
                                <div class="small text-muted">Status: {{ ucfirst($indent->status) }}</div>
                            </div>
                            <div class="text-end">
                                <a href="{{ route('purchase-indents.show', $indent) }}" class="btn btn-sm btn-secondary">Cancel</a>
                            </div>
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show">
                                <strong>Please fix the following errors:</strong>
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        <form action="{{ route('purchase-indents.update', $indent) }}" method="POST" id="indent-form">
                            @csrf
                            @method('PUT')

                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">
                                                Project <span class="text-muted">(optional)</span>
                                            </label>

                                            <select name="project_id" class="form-select form-select-sm project-select">
                                                <option value="">-- General / Store (No Project) --</option>

                                                @foreach($projects as $project)
                                                    <option value="{{ $project->id }}" {{ old('project_id', $indent->project_id) == $project->id ? 'selected' : '' }}>
                                                        {{ $project->code }} - {{ $project->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>


                                        <div class="col-md-3">
                                            <label class="form-label">Department <span class="text-danger">*</span></label>
                                            <select name="department_id" class="form-select form-select-sm" required>
                                                <option value="">-- Select Department --</option>
                                                @foreach($departments as $dept)
                                                    <option value="{{ $dept->id }}" {{ old('department_id', $indent->department_id) == $dept->id ? 'selected' : '' }}>
                                                        {{ $dept->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Required By Date <span class="text-danger">*</span></label>
                                            <input type="date" name="required_by_date" class="form-control form-control-sm"
                                                value="{{ old('required_by_date', optional($indent->required_by_date)->format('Y-m-d')) }}"
                                                required>
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label">Remarks</label>
                                            <textarea name="remarks" rows="2"
                                                class="form-control form-control-sm">{{ old('remarks', $indent->remarks) }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <strong>Items</strong>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-row-btn">
                                        <i class="bi bi-plus-circle"></i> Add Row
                                    </button>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive  items-table-wrapper" >
                                        <table class="table table-sm table-bordered mb-0" id="items-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 3%">#</th>
                                                    <th style="width: 15%">Item *</th>
                                                    <th style="width: 20%">Brand</th>
                                                    <th style="width: 8%">Grade</th>
                                                    <th style="width: 7%">Thick (mm)</th>
                                                    <th style="width: 7%">Density</th>
                                                    <th style="width: 7%">L (mm)</th>
                                                    <th style="width: 7%">W (mm)</th>
                                                    <th style="width: 7%">Wt/m (kg)</th>
                                                    <th style="width: 7%">Qty Pcs</th>
                                                    <th style="width: 10%">Order Qty *</th>
                                                    <th style="width: 7%">UOM</th>
                                                    <th style="width: 12%">Remarks</th>
                                                    <th style="width: 3%"></th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('purchase-indents.show', $indent) }}" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary" id="submit-btn">Update Indent</button>
                            </div>
                        </form>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Then Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

                        <script>$(document).ready(function () {

                                $('.project-select').select2({
                                    placeholder: "Select Project",
                                    allowClear: true,
                                    width: '100%'
                                });

                                // Auto focus search input when dropdown opens
                                $(document).on('select2:open', function () {
                                    document.querySelector('.select2-container--open .select2-search__field').focus();
                                });

                            });
        </script>

                        <script>

                            const ITEM_META = {!! $itemMetaJson !!};
                            const EXISTING_ITEMS = @json($indent->items->values());

                            /**
                             * ✅ Performance:
                             * - ITEM_META_MAP: O(1) lookups instead of ITEM_META.find(...)
                             * - ITEM_OPTIONS_HTML: build <option> list once; reuse for every row (large item masters stay fast)
                             */
                            const ITEM_META_MAP = {};
                            ITEM_META.forEach(i => { ITEM_META_MAP[String(i.id)] = i; });

                            function escapeHtml(str) {
                                const s = String(str ?? '');
                                return s.replace(/[&<>"']/g, function (m) {
                                    return ({
                                        '&': '&amp;',
                                        '<': '&lt;',
                                        '>': '&gt;',
                                        '"': '&quot;',
                                        "'": '&#039;'
                                    })[m];
                                });
                            }


                            function splitCodeName(text) {
                                const t = String(text ?? '');
                                const idx = t.indexOf(' - ');
                                if (idx === -1) return { code: '', name: t };

                                return {
                                    code: t.slice(0, idx).trim(),
                                    name: t.slice(idx + 3).trim()
                                };
                            }

                            const ITEM_OPTIONS_HTML = ITEM_META.map(item => {
                                return `<option value="${item.id}">${escapeHtml(item.name)}</option>`;
                            }).join('');

                            let rowIndex = 0;

                            function findItemMeta(itemId) {
                                return ITEM_META_MAP[String(itemId)] || null;
                            }

                            function toNum(v) {
                                const n = parseFloat(v);
                                return isNaN(n) ? 0 : n;
                            }

                            function round(n, dp) {
                                const f = Math.pow(10, dp);
                                return Math.round((n + Number.EPSILON) * f) / f;
                            }

                            function initSelect2(el, options) {
                                if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) return;

                                const $el = window.jQuery(el);

                                if ($el.data('select2')) {
                                    $el.select2('destroy');
                                }

                                $el.select2(Object.assign({
                                    width: '100%',
                                    dropdownParent: window.jQuery('body'), // 🔥 VERY IMPORTANT FIX
                                    allowClear: true
                                }, options || {}));
                            }


                            function initItemSelect(el) {
                                initSelect2(el, {
                                    placeholder: 'Select item...',
                                    minimumResultsForSearch: 0,
                                    tags: false,

                                    // ✅ Show Name only after selection (hide code), keep code visible in dropdown list
                                    templateResult: function (data) {
                                        if (!data.id || !window.jQuery) return data.text;

                                        const p = splitCodeName(data.text);
                                        if (!p.code) return data.text;

                                        const $c = window.jQuery('<div class="d-flex flex-column"></div>');
                                        window.jQuery('<div class="fw-semibold"></div>').text(p.name).appendTo($c);
                                        window.jQuery('<div class="text-muted small"></div>').text(p.code).appendTo($c);
                                        return $c;
                                    },
                                    templateSelection: function (data) {
                                        if (!data.id) return data.text;
                                        const p = splitCodeName(data.text);
                                        return p.name || data.text;
                                    }
                                });

                                // If select2 is not loaded, stop here (page still works as normal select)
                                if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) return;

                                const $el = window.jQuery(el);

                                // ✅ User can type immediately (no need to click the search box)
                                $el.off('select2:open.indent').on('select2:open.indent', function () {
                                    setTimeout(() => {
                                        const field = document.querySelector('.select2-container--open .select2-search__field');
                                        if (field) field.focus();
                                    }, 0);
                                });

                                // ✅ When user starts typing on the closed select, open dropdown and filter
                                const $container = $el.next('.select2-container');
                                $container.find('.select2-selection')
                                    .off('keydown.indentType')
                                    .on('keydown.indentType', function (e) {
                                        if (e.key && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                                            $el.select2('open');

                                            // Put first key into search field; after that, user types normally
                                            setTimeout(() => {
                                                const $field = window.jQuery('.select2-container--open .select2-search__field');
                                                if ($field.length) {
                                                    $field.val(e.key).trigger('input');
                                                }
                                            }, 0);

                                            e.preventDefault();
                                        }
                                    });
                            }


                          function initBrandSelect(el) {

                                initSelect2(el, {
                                    tags: true,
                                    tokenSeparators: [','],
                                    placeholder: 'Select or type brand',
                                    minimumResultsForSearch: 0
                                });

                                if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) return;

                                const $el = window.jQuery(el);

                                // 🔥 When Brand is opened → focus search textbox
                                $el.off('select2:open.brandFocus')
                                    .on('select2:open.brandFocus', function () {
                                        setTimeout(() => {
                                            const field = document.querySelector(
                                                '.select2-container--open .select2-search__field'
                                            );
                                            if (field) field.focus();
                                        }, 0);
                                    });
                            }


                            function refreshBrandOptions(tr, selectedValue) {
                                const itemId = tr.querySelector('.item-select').value;
                                const meta = findItemMeta(itemId);
                                const brandSelect = tr.querySelector('.brand-select');

                                brandSelect.innerHTML = '<option value=""></option>';

                                if (meta && Array.isArray(meta.brands) && meta.brands.length) {
                                    meta.brands.forEach(b => {
                                        const opt = document.createElement('option');
                                        opt.value = b;
                                        opt.textContent = b;
                                        if (selectedValue && selectedValue === b) {
                                            opt.selected = true;
                                        }
                                        brandSelect.appendChild(opt);
                                    });
                                }

                                initBrandSelect(brandSelect);

                                if (selectedValue) {
                                    // If value isn't in list, keep it via select2 tags (or add option)
                                    const exists = Array.from(brandSelect.options).some(o => o.value === selectedValue);
                                    if (!exists) {
                                        const opt = document.createElement('option');
                                        opt.value = selectedValue;
                                        opt.textContent = selectedValue;
                                        opt.selected = true;
                                        brandSelect.appendChild(opt);
                                    }

                                    brandSelect.value = selectedValue;

                                    // Update Select2 UI if enabled
                                    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                                        window.jQuery(brandSelect).trigger('change.select2');
                                    }
                                }
                            }

                            function makeRow(idx, existingData = null) {
                                const idHidden = existingData && existingData.id ? `<input type="hidden" name="items[${idx}][id]" value="${existingData.id}">` : '';

                                const grade = existingData?.grade ?? '';
                                const t = existingData?.thickness_mm ?? '';
                                const l = existingData?.length_mm ?? '';
                                const w = existingData?.width_mm ?? '';
                                const pcs = existingData?.qty_pcs ?? '';
                                const orderQty = existingData?.order_qty ?? '';
                                const uomId = existingData?.uom_id ?? '';
                                const remarks = existingData?.remarks ?? '';
                                const brand = existingData?.brand ?? '';

                                return `
                                            <tr data-row="${idx}" data-manual="0" data-brand="${brand}">
                                                ${idHidden}
                                                <td class="align-middle text-muted">${idx + 1}</td>
                                                <td>
                                                    <select name="items[${idx}][item_id]" class="form-select form-select-sm item-select" required>
                                                        <option value="">-- Select --</option>
                                                        ${ITEM_OPTIONS_HTML}
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="items[${idx}][brand]" class="form-select form-select-sm brand-select">
                                                        <option value=""></option>
                                                    </select>
                                                </td>
                                                <td><input type="text" name="items[${idx}][grade]" class="form-control form-control-sm grade-input" value="${grade}"></td>
                                                <td><input type="number" step="0.01" name="items[${idx}][thickness_mm]" class="form-control form-control-sm thickness-input" value="${t}"></td>
                                                <td class="align-middle"><span class="badge bg-light text-dark border density-badge">-</span></td>
                                                <td><input type="number" step="0.01" name="items[${idx}][length_mm]" class="form-control form-control-sm length-input" value="${l}"></td>
                                                <td><input type="number" step="0.01" name="items[${idx}][width_mm]" class="form-control form-control-sm width-input" value="${w}"></td>
                                                <td class="align-middle"><span class="badge bg-light text-dark border wpm-badge">-</span></td>
                                                <td><input type="number" step="0.001" name="items[${idx}][qty_pcs]" class="form-control form-control-sm qty-pcs-input" value="${pcs}"></td>
                                                <td>
                                                    <input type="number" step="0.001" name="items[${idx}][order_qty]" class="form-control form-control-sm order-qty-input" required value="${orderQty}">
                                                    <div class="small text-muted mt-1 calc-hint"></div>
                                                </td>
                                                <td>
                                                    <input type="hidden" name="items[${idx}][uom_id]" class="uom-id-input" value="${uomId}">
                                                    <span class="badge bg-light text-dark border uom-code-badge">-</span>
                                                </td>
                                                <td><input type="text" name="items[${idx}][remarks]" class="form-control form-control-sm" value="${remarks}"></td>
                                                <td class="text-center align-middle">
                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn" title="Remove">&times;</button>
                                                </td>
                                            </tr>
                                        `;
                            }

                            function recalcRow(tr) {
                                const itemId = tr.querySelector('.item-select').value;
                                const meta = findItemMeta(itemId);

                                const densityBadge = tr.querySelector('.density-badge');
                                const wpmBadge = tr.querySelector('.wpm-badge');
                                const hint = tr.querySelector('.calc-hint');
                                const uomIdInput = tr.querySelector('.uom-id-input');
                                const uomCodeBadge = tr.querySelector('.uom-code-badge');
                                const gradeInput = tr.querySelector('.grade-input');

                                if (!meta) {
                                    densityBadge.textContent = '-';
                                    wpmBadge.textContent = '-';
                                    hint.textContent = '';
                                    return;
                                }

                                densityBadge.textContent = meta.density ? meta.density : '-';
                                wpmBadge.textContent = meta.weight_per_meter ? meta.weight_per_meter : '-';

                                if (meta.uom_id && uomIdInput) uomIdInput.value = meta.uom_id;
                                if (uomCodeBadge) uomCodeBadge.textContent = meta.uom_code ? meta.uom_code : '-';
                                if (meta.grade && !gradeInput.value) gradeInput.value = meta.grade;

                                const t = toNum(tr.querySelector('.thickness-input').value);
                                const l = toNum(tr.querySelector('.length-input').value);
                                const w = toNum(tr.querySelector('.width-input').value);
                                const pcs = toNum(tr.querySelector('.qty-pcs-input').value);

                                const orderQtyInput = tr.querySelector('.order-qty-input');
                                const manual = tr.getAttribute('data-manual') === '1';

                                let perPiece = null;
                                let total = null;
                                let used = '';

                                const wpm = meta.weight_per_meter ? toNum(meta.weight_per_meter) : 0;
                                if (wpm > 0 && l > 0) {
                                    const lengthM = l / 1000.0;
                                    perPiece = wpm * lengthM;
                                    total = perPiece * (pcs > 0 ? pcs : 1);
                                    used = 'kg/m';
                                } else {
                                    const dens = meta.density ? toNum(meta.density) : 0;
                                    if (dens > 0 && t > 0 && w > 0 && l > 0) {
                                        const tM = t / 1000.0;
                                        const wM = w / 1000.0;
                                        const lM = l / 1000.0;
                                        const vol = tM * wM * lM;
                                        perPiece = vol * dens;
                                        total = perPiece * (pcs > 0 ? pcs : 1);
                                        used = 'density';
                                    }
                                }

                                if (perPiece !== null && total !== null) {
                                    perPiece = round(perPiece, 4);
                                    total = round(total, 3);
                                    hint.textContent = `Auto: ${total} kg (per pcs ${perPiece} kg, ${used})`;
                                    if (!manual) {
                                        orderQtyInput.value = total;
                                    }
                                } else {
                                    hint.textContent = '';
                                }
                            }

                            document.addEventListener('DOMContentLoaded', function () {
                                const tbody = document.querySelector('#items-table tbody');
                                const addRowBtn = document.getElementById('add-row-btn');

                                function addRow(existing = null) {
                                    tbody.insertAdjacentHTML('beforeend', makeRow(rowIndex, existing));
                                    const tr = tbody.querySelector(`tr[data-row="${rowIndex}"]`);

                                    // ✅ Select the existing item BEFORE Select2 init
                                    const itemSelect = tr.querySelector('.item-select');
                                    if (existing && existing.item_id) {
                                        itemSelect.value = existing.item_id;
                                    }

                                    // ✅ Searchable item dropdown (Select2)
                                    initItemSelect(itemSelect);

                                    // Brand preselect
                                    const brand = tr.getAttribute('data-brand') || '';
                                    refreshBrandOptions(tr, brand);

                                    // If existing row has order_qty, treat as manual
                                    if (existing && existing.order_qty) {
                                        tr.setAttribute('data-manual', '1');
                                    }

                                    rowIndex++;
                                    recalcRow(tr);
                                }

                                if (EXISTING_ITEMS && EXISTING_ITEMS.length) {
                                    EXISTING_ITEMS.forEach(x => addRow(x));
                                } else {
                                    addRow();
                                }

                                addRowBtn.addEventListener('click', () => addRow());

                                // Remove row
                                tbody.addEventListener('click', function (e) {
                                    if (e.target.classList.contains('remove-row-btn')) {
                                        const tr = e.target.closest('tr');
                                        if (tbody.querySelectorAll('tr').length > 1) {
                                            tr.remove();
                                        } else {
                                            alert('At least one item is required.');
                                        }
                                    }
                                });

                                // ✅ IMPORTANT:
                                // Select2 triggers events via jQuery, so use delegated jQuery handler when available.
                                if (window.jQuery) {
                                    const $tbody = window.jQuery(tbody);

                                    $tbody.on('change select2:select select2:clear', '.item-select', function () {
                                        const tr = this.closest('tr');
                                        if (!tr) return;

                                        tr.setAttribute('data-manual', '0');

                                        // Refresh brand list and clear selection on item change
                                        refreshBrandOptions(tr, '');

                                        recalcRow(tr);
                                    });

                                    $tbody.on('input', '.order-qty-input', function () {
                                        const tr = this.closest('tr');
                                        if (!tr) return;
                                        tr.setAttribute('data-manual', '1');
                                    });

                                    $tbody.on('input', '.thickness-input, .length-input, .width-input, .qty-pcs-input', function () {
                                        const tr = this.closest('tr');
                                        if (!tr) return;

                                        tr.setAttribute('data-manual', '0');
                                        recalcRow(tr);
                                    });
                                } else {
                                    // Fallback (no jQuery/select2)
                                    tbody.addEventListener('change', function (e) {
                                        const tr = e.target.closest('tr');
                                        if (!tr) return;

                                        if (e.target.classList.contains('item-select')) {
                                            tr.setAttribute('data-manual', '0');
                                            refreshBrandOptions(tr, '');
                                            recalcRow(tr);
                                        }
                                    });

                                    tbody.addEventListener('input', function (e) {
                                        const tr = e.target.closest('tr');
                                        if (!tr) return;

                                        if (e.target.classList.contains('order-qty-input')) {
                                            tr.setAttribute('data-manual', '1');
                                            return;
                                        }

                                        if (
                                            e.target.classList.contains('thickness-input') ||
                                            e.target.classList.contains('length-input') ||
                                            e.target.classList.contains('width-input') ||
                                            e.target.classList.contains('qty-pcs-input')
                                        ) {
                                            tr.setAttribute('data-manual', '0');
                                            recalcRow(tr);
                                        }
                                    });
                                }
                            });

                        </script>
@endsection