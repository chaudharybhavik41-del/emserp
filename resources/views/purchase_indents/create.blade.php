@extends('layouts.erp')

@section('title', 'Create Purchase Indent')

@section('content')
                    <style>
                    /* Select2 inside table: match Bootstrap .form-select-sm height */
                    .select2-container { width: 100% !important; }

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

                    @media (max-width: 768px) {
                #items-table thead {
                    display: none;
                }

                #items-table tbody tr {
                    display: block;
                    border: 1px solid #dee2e6;
                    margin-bottom: 12px;
                    padding: 8px;
                    border-radius: 6px;
                    background: #fff;
                }

                #items-table tbody td {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    border: none;
                    padding: 6px 4px;
                }

                #items-table tbody td::before {
                    content: attr(data-label);
                    font-weight: 600;
                    font-size: 0.75rem;
                    color: #6c757d;
                    white-space: nowrap;
                }

                #items-table tbody td:last-child {
                    justify-content: center;
                }
            }

                </style>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h1 class="h4 mb-0">Create Purchase Indent</h1>
                            <div class="small text-muted">Project is optional (for general store consumables).</div>
                        </div>
                        <div class="text-end">
                            <a href="{{ route('purchase-indents.index') }}" class="btn btn-sm btn-secondary">Back</a>
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

                    <form action="{{ route('purchase-indents.store') }}" method="POST" id="indent-form">
                        @csrf

                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row g-3">
                                    {{-- <div class="col-md-4">
                                        <label class="form-label">Project <span class="text-muted">(optional)</span></label>
                                        <select name="project_id" class="form-select form-select-sm">
                                            <option value="">-- General / Store (No Project) --</option>
                                            @foreach($projects as $project)
                                                <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>
                                                    {{ $project->code }} - {{ $project->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div> --}}
        <div class="col-md-6">
            <label class="form-label fw-semibold">
                Project <span class="text-muted">(optional)</span>
            </label>

            <select name="project_id" class="form-select form-select-sm project-select">

                <option value="">
                    -- General / Store (No Project) --
                </option>

                @foreach($projects as $project)
                    <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>
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
                                                <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                                    {{ $dept->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">Required By Date <span class="text-danger">*</span></label>
                                        <input type="date" name="required_by_date" class="form-control form-control-sm"
                                               value="{{ old('required_by_date') }}" required>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">Remarks</label>
                                        <textarea name="remarks" rows="2" class="form-control form-control-sm">{{ old('remarks') }}</textarea>
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
                                {{-- <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0" id="items-table">
                                        <thead class="table-light">
                                        <tr>
                                            <th style="width: 3%">#</th>
                                            <th style="width: 15%">Item *</th>
                                            <th style="width: 10%">Brand</th>
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
                                </div> --}}

                                <div class="table-responsive items-table-wrapper">
                                    <table class="table table-sm table-bordered mb-0" id="items-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="min-width:40px">#</th>
                                                <th style="min-width:220px">Item *</th>
                                                <th style="min-width:140px">Brand</th>
                                                <th style="min-width:120px">Grade</th>
                                                <th style="min-width:110px">Thick (mm)</th>
                                                <th style="min-width:110px">Density</th>
                                                <th style="min-width:110px">L (mm)</th>
                                                <th style="min-width:110px">W (mm)</th>
                                                <th style="min-width:120px">Wt/m (kg)</th>
                                                <th style="min-width:110px">Qty Pcs</th>
                                                <th style="min-width:160px">Order Qty *</th>
                                                <th style="min-width:90px">UOM</th>
                                                <th style="min-width:180px">Remarks</th>
                                                <th style="min-width:40px"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>

                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('purchase-indents.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary" id="submit-btn">Save Indent</button>
                        </div>
                    </form>


                    <!-- jQuery FIRST -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Then Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

                    <script>
                      $(document).ready(function () {
                            $('.project-select').select2({
                                placeholder: "Select Project",
                                allowClear: true,
                                width: '100%'
                            });

                            // Focus search input when dropdown opens
                            $(document).on('select2:open', function () {
                                document.querySelector('.select2-container--open .select2-search__field').focus();
                            });
                        });

                    </script>


                    <script>

                const ITEM_META = {!! $itemMetaJson !!};

                        /**
                         * ✅ Performance:
                         * - ITEM_META_MAP: O(1) lookups instead of ITEM_META.find(...) for every change
                         * - ITEM_OPTIONS_HTML: build <option> list once; reuse for every new row
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
                                    dropdownParent: $el.closest('td'), // 🔥 FIX
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

                            // ✅ AUTO FOCUS SEARCH BOX WHEN OPEN
                            const $el = jQuery(el);
                            $el.off('select2:open.brandFocus')
                                .on('select2:open.brandFocus', function () {
                                    setTimeout(() => {
                                        document
                                            .querySelector('.select2-container--open .select2-search__field')
                                            ?.focus();
                                    }, 0);
                                });
                        }

                        function refreshBrandOptions(tr) {
                            const itemId = tr.querySelector('.item-select').value;
                            const meta = findItemMeta(itemId);
                            const brandSelect = tr.querySelector('.brand-select');

                            brandSelect.innerHTML = '<option value=""></option>';

                            if (meta && Array.isArray(meta.brands) && meta.brands.length) {
                                meta.brands.forEach(b => {
                                    const opt = document.createElement('option');
                                    opt.value = b;
                                    opt.textContent = b;
                                    brandSelect.appendChild(opt);
                                });
                            }

                            initBrandSelect(brandSelect);
                        }

                        // function makeRow(idx) {
                        //     return `
                        //         <tr data-row="${idx}" data-manual="0">
                        //             <td class="align-middle text-muted">${idx + 1}</td>
                        //             <td>
                        //                 <select name="items[${idx}][item_id]" class="form-select form-select-sm item-select" required>
                        //                     <option value="">-- Select --</option>
                        //                     ${ITEM_OPTIONS_HTML}
                        //                 </select>
                        //             </td>
                        //             <td>
                        //                 <select name="items[${idx}][brand]" class="form-select form-select-sm brand-select">
                        //                     <option value=""></option>
                        //                 </select>
                        //             </td>
                        //             <td>
                        //                 <input type="text" name="items[${idx}][grade]" class="form-control form-control-sm grade-input">
                        //             </td>
                        //             <td>
                        //                 <input type="number" step="0.01" name="items[${idx}][thickness_mm]" class="form-control form-control-sm thickness-input">
                        //             </td>
                        //             <td class="align-middle">
                        //                 <span class="badge bg-light text-dark border density-badge">-</span>
                        //             </td>
                        //             <td>
                        //                 <input type="number" step="0.01" name="items[${idx}][length_mm]" class="form-control form-control-sm length-input">
                        //             </td>
                        //             <td>
                        //                 <input type="number" step="0.01" name="items[${idx}][width_mm]" class="form-control form-control-sm width-input">
                        //             </td>
                        //             <td class="align-middle">
                        //                 <span class="badge bg-light text-dark border wpm-badge">-</span>
                        //             </td>
                        //             <td>
                        //                 <input type="number" step="0.001" name="items[${idx}][qty_pcs]" class="form-control form-control-sm qty-pcs-input">
                        //             </td>
                        //             <td>
                        //                 <input type="number" step="0.001" name="items[${idx}][order_qty]" class="form-control form-control-sm order-qty-input" required>
                        //                 <div class="small text-muted mt-1 calc-hint"></div>
                        //             </td>
                        //             <td>
                        //                 <input type="hidden" name="items[${idx}][uom_id]" class="uom-id-input">
                        //                 <span class="badge bg-light text-dark border uom-code-badge">-</span>
                        //             </td>
                        //             <td>
                        //                 <input type="text" name="items[${idx}][remarks]" class="form-control form-control-sm">
                        //             </td>
                        //             <td class="text-center align-middle">
                        //                 <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn" title="Remove">&times;</button>
                        //             </td>
                        //         </tr>
                        //     `;
                        // }
            function makeRow(idx) {
                return `
            <tr data-row="${idx}" data-manual="0">
                <td data-label="#">${idx + 1}</td>

                <td data-label="Item">
                    <select name="items[${idx}][item_id]" class="form-select form-select-sm item-select" required>
                        <option value="">-- Select --</option>
                        ${ITEM_OPTIONS_HTML}
                    </select>
                </td>

                <td data-label="Brand">
                    <select name="items[${idx}][brand]" class="form-select form-select-sm brand-select">
                        <option value=""></option>
                    </select>
                </td>

                <td data-label="Grade">
                    <input type="text" name="items[${idx}][grade]" class="form-control form-control-sm grade-input">
                </td>

                <td data-label="Thickness (mm)">
                    <input type="number" step="0.01" name="items[${idx}][thickness_mm]" class="form-control form-control-sm thickness-input">
                </td>

                <td data-label="Density">
                    <span class="badge bg-light text-dark border density-badge">-</span>
                </td>

                <td data-label="Length (mm)">
                    <input type="number" step="0.01" name="items[${idx}][length_mm]" class="form-control form-control-sm length-input">
                </td>

                <td data-label="Width (mm)">
                    <input type="number" step="0.01" name="items[${idx}][width_mm]" class="form-control form-control-sm width-input">
                </td>

                <td data-label="Wt/m">
                    <span class="badge bg-light text-dark border wpm-badge">-</span>
                </td>

                <td data-label="Qty Pcs">
                    <input type="number" step="0.001" name="items[${idx}][qty_pcs]" class="form-control form-control-sm qty-pcs-input">
                </td>

                <td data-label="Order Qty">
                    <input type="number" step="0.001" name="items[${idx}][order_qty]" class="form-control form-control-sm order-qty-input" required>
                    <div class="small text-muted calc-hint"></div>
                </td>

                <td data-label="UOM">
                    <input type="hidden" name="items[${idx}][uom_id]" class="uom-id-input">
                    <span class="badge bg-light text-dark border uom-code-badge">-</span>
                </td>

                <td data-label="Remarks">
                    <input type="text" name="items[${idx}][remarks]" class="form-control form-control-sm">
                </td>

                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">&times;</button>
                </td>
            </tr>`;
            }

                        function recalcRow(tr) {
                            const itemId = tr.querySelector('.item-select').value;
                            const meta = findItemMeta(itemId);

                            const densityBadge = tr.querySelector('.density-badge');
                            const wpmBadge = tr.querySelector('.wpm-badge');
                            const hint = tr.querySelector('.calc-hint');
                            const gradeInput = tr.querySelector('.grade-input');

                            const uomIdInput = tr.querySelector('.uom-id-input');
                            const uomCodeBadge = tr.querySelector('.uom-code-badge');

                            if (!meta) {
                                densityBadge.textContent = '-';
                                wpmBadge.textContent = '-';
                                hint.textContent = '';
                                if (uomIdInput) uomIdInput.value = '';
                                if (uomCodeBadge) uomCodeBadge.textContent = '-';
                                return;
                            }

                            densityBadge.textContent = meta.density ? meta.density : '-';
                            wpmBadge.textContent = meta.weight_per_meter ? meta.weight_per_meter : '-';

                            // ✅ Always set UOM from item
                            if (uomIdInput) uomIdInput.value = meta.uom_id ? meta.uom_id : '';
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

                            function addRow() {
                                tbody.insertAdjacentHTML('beforeend', makeRow(rowIndex));
                                const tr = tbody.querySelector(`tr[data-row="${rowIndex}"]`);

                                // ✅ Searchable item dropdown (Select2)
                                initItemSelect(tr.querySelector('.item-select'));

                                refreshBrandOptions(tr);

                                // ✅ Auto fill UOM & show badges immediately
                                recalcRow(tr);

                                rowIndex++;
                            }

                            addRow();
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
                                    refreshBrandOptions(tr);
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
                                        refreshBrandOptions(tr);
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
