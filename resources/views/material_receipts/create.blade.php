@extends('layouts.erp')

@section('title', 'Create Material Receipt (GRN)')
<style>
    .line-row:hover {
        background-color: #f8f9fa;
    }

.line-row td {
    vertical-align: middle;
}

.table thead th {
    white-space: nowrap;
    font-size: 13px;
}
</style>
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Create Material Receipt (GRN)</h1>
    </div>

    @if($errors->has('general'))
        <div class="alert alert-danger">
            {{ $errors->first('general') }}
        </div>
    @endif

    <form action="{{ route('material-receipts.store') }}" method="POST">
        @csrf

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Receipt Date</label>
                        <input type="date" name="receipt_date"
                               value="{{ old('receipt_date', now()->toDateString()) }}"
                               class="form-control form-control-sm">
                        @error('receipt_date')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label d-block">Material Type</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="is_client_material" id="mt-own"
                                   value="0" {{ old('is_client_material', '0') == '0' ? 'checked' : '' }}>
                            <label class="form-check-label" for="mt-own">Own Material</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="is_client_material" id="mt-client"
                                   value="1" {{ old('is_client_material') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="mt-client">Client Material</label>
                        </div>
                    </div>
                </div>

                {{-- PO row (hidden when Client Material) --}}
                <div class="row mb-3 mt-3" id="po-row">
                    <div class="col-md-4">
                        <label for="purchase_order_id" class="form-label">Purchase Order (Own Material)</label>
                        <select id="purchase_order_id"
                                name="purchase_order_id"
                                class="form-select form-select-sm">
                            <option value="">-- No PO / Manual GRN --</option>
                            @foreach($purchaseOrders as $po)
                                <option value="{{ $po->id }}"
                                        data-project-id="{{ $po->project_id }}"
                                        data-supplier-id="{{ $po->vendor_party_id }}"
                                        {{ old('purchase_order_id') == $po->id ? 'selected' : '' }}>
                                    {{ $po->code }}
                                    @if($po->project)
                                        - {{ $po->project->code }}
                                    @endif
                                    @if($po->vendor)
                                        - {{ $po->vendor->name }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            For own material receipts, select the PO to link GRN with purchase.
                        </div>
                        @error('purchase_order_id')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="po_number" class="form-label">PO Number (Text)</label>
                        <input type="text"
                               id="po_number"
                               name="po_number"
                               value="{{ old('po_number') }}"
                               class="form-control form-control-sm">
                        <div class="form-text">
                            Will auto-fill from Purchase Order when selected.
                        </div>
                        @error('po_number')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Project</label>
                        <select name="project_id" class="form-select form-select-sm" id="project-select">
                            <option value="">-- Select Project --</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}"
                                        data-client-id="{{ $project->client_party_id ?? '' }}"
                                        {{ old('project_id') == $project->id ? 'selected' : '' }}>
                                    {{ $project->code }} - {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('project_id')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    {{-- Supplier column (hidden when Client Material) --}}
                    <div class="col-md-4" id="supplier-col">
                        <label class="form-label">Supplier (for Own Material)</label>
                        <select name="supplier_id" class="form-select form-select-sm">
                            <option value="">-- Select Supplier --</option>
                            @foreach($suppliers as $party)
                                <option value="{{ $party->id }}" {{ old('supplier_id') == $party->id ? 'selected' : '' }}>
                                    {{ $party->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Client (auto from Project if set)</label>
                        <select name="client_party_id" class="form-select form-select-sm" id="client-select">
                            <option value="">-- Select Client --</option>
                            @foreach($clients as $party)
                                <option value="{{ $party->id }}" {{ old('client_party_id') == $party->id ? 'selected' : '' }}>
                                    {{ $party->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('client_party_id')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Vehicle Number</label>
                        <input type="text" name="vehicle_number"
                               value="{{ old('vehicle_number') }}"
                               class="form-control form-control-sm">
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-4">
                        <label class="form-label">Invoice Number</label>
                        <input type="text" name="invoice_number"
                               value="{{ old('invoice_number') }}"
                               class="form-control form-control-sm">
                        @error('invoice_number')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Invoice Date</label>
                        <input type="date" name="invoice_date"
                               value="{{ old('invoice_date', now()->toDateString()) }}"
                               class="form-control form-control-sm">
                        @error('invoice_date')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Challan Number</label>
                        <input type="text" name="challan_number"
                               value="{{ old('challan_number') }}"
                               class="form-control form-control-sm">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control form-control-sm" rows="2">{{ old('remarks') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <h5 class="card-title mb-0 h6">Line Items</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-line-btn">
                    + Add Line
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle" id="lines-table">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 18%">Item</th>
                            <th style="width: 10%">Brand</th>
                            <th style="width: 10%">Category</th>
                            <th style="width: 8%">Grade</th>
                            <th style="width: 7%">T (mm)</th>
                            <th style="width: 7%">W (mm)</th>
                            <th style="width: 7%">L (mm)</th>
                            <th style="width: 10%">Section</th>
                            <th style="width: 7%">Qty (pcs)</th>
                            <th style="width: 10%">Recv Wt (kg)</th>
                            <th style="width: 8%">UOM</th>
                            <th style="width: 12%">Remarks</th>
                            <th style="width: 4%"></th>
                        </tr>
                        </thead>
                        <tbody>
                        {{-- rows are added by JS --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex justify-content-between">
            <a href="{{ route('material-receipts.index') }}" class="btn btn-sm btn-outline-secondary">
                Cancel
            </a>
            <button type="submit" class="btn btn-sm btn-primary">
                Save GRN
            </button>
        </div>
    </form>

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- jQuery (if not already added) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let lineIndex = 0;

            function makeRow(index) {
                return `
        <tr data-row-index="${index}" class="align-middle line-row">

            <!-- Item -->
            <td style="min-width:180px;">
                <select name="lines[${index}][item_id]"
                        class="form-select form-select-sm item-select shadow-sm"
                        required>
                    <option value="">Select Item</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}">
                            {{ $item->name }}
                        </option>
                    @endforeach
                </select>

                <input type="hidden"
                       name="lines[${index}][purchase_order_item_id]"
                       class="js-po-item-id po-item-id-input">
            </td>

            <!-- Brand -->
            <td style="min-width:130px;">
                <input type="text"
                       name="lines[${index}][brand]"
                       class="form-control form-control-sm brand-input shadow-sm"
                       placeholder="Brand name">
            </td>

            <!-- Material Category -->
            <td style="min-width:150px;">
                <select name="lines[${index}][material_category]"
                        class="form-select form-select-sm material-category-select shadow-sm"
                        required>
                    <option value="">Category</option>
                    @foreach($materialCategories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </td>

            <!-- Grade -->
            <td style="min-width:100px;">
                <input type="text"
                       name="lines[${index}][grade]"
                       class="form-control form-control-sm shadow-sm"
                       placeholder="Grade">
            </td>

            <!-- Thickness -->
            <td style="width:90px;">
                <input type="number"
                       name="lines[${index}][thickness_mm]"
                       class="form-control form-control-sm text-center thickness-input shadow-sm"
                       placeholder="T"
                       min="0" step="1">
            </td>

            <!-- Width -->
            <td style="width:90px;">
                <input type="number"
                       name="lines[${index}][width_mm]"
                       class="form-control form-control-sm text-center width-input shadow-sm"
                       placeholder="W"
                       min="0" step="1">
            </td>

            <!-- Length -->
            <td style="width:90px;">
                <input type="number"
                       name="lines[${index}][length_mm]"
                       class="form-control form-control-sm text-center length-input shadow-sm"
                       placeholder="L"
                       min="0" step="1">
            </td>

            <!-- Section Profile -->
            <td style="min-width:130px;">
                <input type="text"
                       name="lines[${index}][section_profile]"
                       class="form-control form-control-sm shadow-sm"
                       placeholder="ISMB300">
            </td>

            <!-- Qty -->
            <td style="width:90px;">
                <input type="number"
                       name="lines[${index}][qty_pcs]"
                       class="form-control form-control-sm text-center qty-pcs-input shadow-sm"
                       min="1" step="1" value="1" required>
            </td>

            <!-- Weight -->
            <td style="width:110px;">
                <input type="number"
                       name="lines[${index}][received_weight_kg]"
                       class="form-control form-control-sm text-center received-weight-input shadow-sm"
                       placeholder="Kg"
                       min="0" step="0.001">
            </td>

            <!-- UOM -->
            <td style="min-width:100px;">
                <select name="lines[${index}][uom_id]"
                        class="form-select form-select-sm uom-select shadow-sm"
                        required>
                    <option value="">UOM</option>
                    @foreach($uoms as $uom)
                        <option value="{{ $uom->id }}">{{ $uom->name }}</option>
                    @endforeach
                </select>
            </td>

            <!-- Remarks -->
            <td style="min-width:150px;">
                <input type="text"
                       name="lines[${index}][remarks]"
                       class="form-control form-control-sm shadow-sm"
                       placeholder="Remarks">
            </td>

            <!-- Remove Button -->
            <td class="text-center" style="width:50px;">
                <button type="button"
                        class="btn btn-sm btn-outline-danger remove-line-btn rounded-circle"
                        title="Remove Row">
                    ✕
                </button>
            </td>
        </tr>
    `;
            }

            function addLineRow() {
                const tbody = document.querySelector('#lines-table tbody');
                if (!tbody) return;
                tbody.insertAdjacentHTML('beforeend', makeRow(lineIndex));
                lineIndex++;
            }

            // Recalculate received weight for a given row
            function recalcGrnLineWeight(row) {
                if (!row) return;

                const qtyInput    = row.querySelector('.qty-pcs-input');
                const weightInput = row.querySelector('.received-weight-input');
                const thickness   = row.querySelector('.thickness-input');
                const width       = row.querySelector('.width-input');
                const length      = row.querySelector('.length-input');

                if (!qtyInput || !weightInput) return;

                const qty = parseFloat(qtyInput.value || '0');
                if (!qty || qty <= 0) {
                    // Do not force anything when qty is empty/zero
                    return;
                }

                const density  = parseFloat(row.dataset.densityKgPerM3 || '');
                const tMm      = thickness ? parseFloat(thickness.value || '') : NaN;
                const wMm      = width ? parseFloat(width.value || '') : NaN;
                const lMm      = length ? parseFloat(length.value || '') : NaN;

                // First try full geometric calc: T x W x L x density
                if (!isNaN(density) && !isNaN(tMm) && !isNaN(wMm) && !isNaN(lMm)) {
                    const tM = tMm / 1000;
                    const wM = wMm / 1000;
                    const lM = lMm / 1000;

                    const pieceWeight = tM * wM * lM * density; // kg per piece
                    if (pieceWeight > 0) {
                        weightInput.value = (pieceWeight * qty).toFixed(3);
                        return;
                    }
                }

                // Fallback: per-piece weight derived from PO total (if any)
                const basePerPiece = parseFloat(weightInput.dataset.perPieceWeight || '');
                if (basePerPiece && basePerPiece > 0) {
                    weightInput.value = (basePerPiece * qty).toFixed(3);
                }
            }

            const addBtn = document.getElementById('add-line-btn');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    addLineRow();
                });
            }

            const tableBody = document.querySelector('#lines-table tbody');
            if (tableBody) {
                tableBody.addEventListener('click', function (e) {
                    if (e.target.closest('.remove-line-btn')) {
                        const row = e.target.closest('tr');
                        if (row) {
                            row.remove();
                        }
                    }
                });

                // Live recalc when qty or dimensions change
                tableBody.addEventListener('input', function (e) {
                    if (
                        e.target.classList.contains('qty-pcs-input') ||
                        e.target.classList.contains('width-input') ||
                        e.target.classList.contains('length-input') ||
                        e.target.classList.contains('thickness-input')
                    ) {
                        const row = e.target.closest('tr');
                        recalcGrnLineWeight(row);
                    }
                });

                // Auto-select category when item changes (based on material taxonomy)
                tableBody.addEventListener('change', function (e) {
                    if (!e.target.classList.contains('item-select')) return;

                    const row = e.target.closest('tr');
                    if (!row) return;

                    const itemId = e.target.value;
                    if (!itemId) return;

                    const categorySelect = row.querySelector('.material-category-select');
                    if (!categorySelect) return;

                    // Only auto-fill when empty (so user can override)
                    if (categorySelect.value) return;

                    const suggested = itemDefaultCategoryMap[itemId];
                    if (suggested) {
                        categorySelect.value = String(suggested);
                    }
                });
            }

            // Initial empty row
            addLineRow();

            // Header fields
            const projectSelect       = document.getElementById('project-select');
            const clientSelect        = document.getElementById('client-select');
            const supplierSelect      = document.querySelector('select[name="supplier_id"]');
            const poSelect            = document.getElementById('purchase_order_id');
            const poNumberInput       = document.getElementById('po_number');
            const ownMaterialRadio    = document.getElementById('mt-own');
            const clientMaterialRadio = document.getElementById('mt-client');
            const poRow               = document.getElementById('po-row');
            const supplierCol         = document.getElementById('supplier-col');

            const projectClientMap = @json($projectClientMap ?? []);
            const itemDefaultCategoryMap = @json($itemDefaultCategoryMap ?? []);

            function updateClientFromProject() {
                if (!projectSelect || !clientSelect) return;
                const projectId = projectSelect.value;
                const clientId  = projectClientMap[projectId];
                if (clientId) {
                    clientSelect.value = String(clientId);
                }
            }

            if (projectSelect) {
                projectSelect.addEventListener('change', updateClientFromProject);
                updateClientFromProject();
            }

            /**
             * Own vs Client UI toggle
             * - Client material: hide / clear PO + Supplier
             * - Own material: show PO + Supplier
             */
            function syncMaterialTypeUi() {
                const isClient = clientMaterialRadio && clientMaterialRadio.checked;

                if (isClient) {
                    // Hide PO + supplier
                    if (poRow) {
                        poRow.style.display = 'none';
                    }
                    if (supplierCol) {
                        supplierCol.style.display = 'none';
                    }

                    // Clear PO and supplier values so backend doesn't get stray IDs
                    if (poSelect) {
                        poSelect.value = '';
                    }
                    if (poNumberInput) {
                        poNumberInput.value = '';
                    }
                    if (supplierSelect) {
                        supplierSelect.value = '';
                    }
                } else {
                    // Own material: show PO + supplier
                    if (poRow) {
                        poRow.style.display = '';
                    }
                    if (supplierCol) {
                        supplierCol.style.display = '';
                    }
                }
            }

            if (ownMaterialRadio) {
                ownMaterialRadio.addEventListener('change', syncMaterialTypeUi);
            }
            if (clientMaterialRadio) {
                clientMaterialRadio.addEventListener('change', syncMaterialTypeUi);
            }

            async function loadLinesFromPurchaseOrder(purchaseOrderId) {
                const tbody = document.querySelector('#lines-table tbody');
                if (!tbody) return;

                // Clear current lines
                tbody.innerHTML = '';
                lineIndex = 0;

                if (!purchaseOrderId) {
                    addLineRow();
                    return;
                }

                try {
                    const url = '{{ route('purchase-orders.items-for-grn', ['purchaseOrder' => '__PO__']) }}'
                        .replace('__PO__', purchaseOrderId);

                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (!response.ok) {
                        console.error('Failed to load PO items for GRN', response.status);
                        addLineRow();
                        return;
                    }

                    const data = await response.json();

                    if (!data.items || !Array.isArray(data.items) || data.items.length === 0) {
                        addLineRow();
                        return;
                    }

                    data.items.forEach(function (item) {
                        const index = lineIndex;
                        tbody.insertAdjacentHTML('beforeend', makeRow(index));

                        const row = tbody.querySelector('tr[data-row-index="' + index + '"]');
                        if (!row) {
                            lineIndex++;
                            return;
                        }

                        const itemSelect     = row.querySelector('select[name="lines[' + index + '][item_id]"]');
                        const brandInput    = row.querySelector('input[name="lines[' + index + '][brand]"]');
                        const categorySelect = row.querySelector('select[name="lines[' + index + '][material_category]"]');
                        const gradeInput     = row.querySelector('input[name="lines[' + index + '][grade]"]');
                        const thkInput       = row.querySelector('input[name="lines[' + index + '][thickness_mm]"]');
                        const widthInput     = row.querySelector('input[name="lines[' + index + '][width_mm]"]');
                        const lenInput       = row.querySelector('input[name="lines[' + index + '][length_mm]"]');
                        const sectionInput   = row.querySelector('input[name="lines[' + index + '][section_profile]"]');
                        const qtyInput       = row.querySelector('input[name="lines[' + index + '][qty_pcs]"]');
                        const weightInput    = row.querySelector('input[name="lines[' + index + '][received_weight_kg]"]');
                        const uomSelect      = row.querySelector('select[name="lines[' + index + '][uom_id]"]');
                        const poItemIdInput  = row.querySelector('input[name="lines[' + index + '][purchase_order_item_id]"]');

                        // Store meta used for recalculation
                        const resolvedCategory = item.material_category || itemDefaultCategoryMap[item.item_id] || '';
                        row.dataset.materialCategory  = resolvedCategory;
                        if (categorySelect && resolvedCategory) {
                            categorySelect.value = String(resolvedCategory);
                        }
                        row.dataset.densityKgPerM3    = (item.density_kg_per_m3 ?? item.density ?? '').toString();
                        row.dataset.poQtyPcs          = (item.qty_pcs ?? '').toString();
                        row.dataset.poQuantity        = (item.quantity ?? '').toString();

                        if (itemSelect && item.item_id) {
                            itemSelect.value = String(item.item_id);
                        }

                        if (brandInput) {
                            brandInput.value = (item.brand ?? '').toString();
                        }

                        if (gradeInput && item.grade) {
                            gradeInput.value = item.grade;
                        }

                        if (thkInput && item.thickness_mm != null) {
                            thkInput.value = item.thickness_mm;
                        }

                        if (widthInput && item.width_mm != null) {
                            widthInput.value = item.width_mm;
                        }

                        if (lenInput && item.length_mm != null) {
                            lenInput.value = item.length_mm;
                        }

                        if (sectionInput) {
                            sectionInput.value = sectionInput.value || '';
                        }

                        // PO quantities
                        let poQtyPcs   = null;
                        let poQuantity = null;

                        if (typeof item.qty_pcs !== 'undefined' && item.qty_pcs !== null && item.qty_pcs !== '') {
                            poQtyPcs = parseFloat(item.qty_pcs);
                        }
                        if (typeof item.quantity !== 'undefined' && item.quantity !== null && item.quantity !== '') {
                            poQuantity = parseFloat(item.quantity);
                        }

                        if (qtyInput) {
                            if (poQtyPcs && poQtyPcs > 0) {
                                qtyInput.value = poQtyPcs;
                            } else {
                                qtyInput.value = 1;
                            }
                        }

                        // compute per-piece weight
                        let perPieceWeight = null;
                        if (poQtyPcs && poQtyPcs > 0 && poQuantity && poQuantity > 0) {
                            perPieceWeight = poQuantity / poQtyPcs;
                        }

                        const density  = parseFloat(row.dataset.densityKgPerM3 || '');
                        const tMm      = thkInput ? parseFloat(thkInput.value || '') : NaN;
                        const wMm      = widthInput ? parseFloat(widthInput.value || '') : NaN;
                        const lMm      = lenInput ? parseFloat(lenInput.value || '') : NaN;

                        if (!isNaN(density) && !isNaN(tMm) && !isNaN(wMm) && !isNaN(lMm)) {
                            const tM = tMm / 1000;
                            const wM = wMm / 1000;
                            const lM = lMm / 1000;
                            const plateWeightOnePc = tM * wM * lM * density;
                            if (plateWeightOnePc > 0) {
                                perPieceWeight = plateWeightOnePc;
                            }
                        }

                        if (weightInput) {
                            const qty = parseFloat(qtyInput ? qtyInput.value || '0' : '0') || 0;

                            if (perPieceWeight && qty > 0) {
                                weightInput.value = (perPieceWeight * qty).toFixed(3);
                                weightInput.dataset.perPieceWeight = perPieceWeight.toFixed(6);
                            } else if (poQuantity) {
                                weightInput.value = poQuantity;
                                weightInput.dataset.perPieceWeight = '';
                            } else {
                                weightInput.value = '';
                                weightInput.dataset.perPieceWeight = '';
                            }
                        }

                        if (uomSelect && item.uom_id != null) {
                            uomSelect.value = String(item.uom_id);
                        }

                        if (poItemIdInput && item.id != null) {
                            poItemIdInput.value = item.id;
                        }

                        // initial calc (in case any values adjusted above)
                        recalcGrnLineWeight(row);

                        lineIndex++;
                    });
                } catch (e) {
                    console.error('Error while loading PO items for GRN', e);
                    addLineRow();
                }
            }

            if (poSelect) {
                poSelect.addEventListener('change', function () {
                    const option = poSelect.options[poSelect.selectedIndex];

                    if (!option) {
                        if (poNumberInput) poNumberInput.value = '';
                        loadLinesFromPurchaseOrder(null);
                        return;
                    }

                    const projectId  = option.getAttribute('data-project-id');
                    const supplierId = option.getAttribute('data-supplier-id');
                    const poCode     = option.text.trim().split(' - ')[0];

                    if (projectId && projectSelect) {
                        projectSelect.value = projectId;
                        updateClientFromProject();
                    }

                    if (supplierId && supplierSelect) {
                        supplierSelect.value = supplierId;
                    }

                    if (poNumberInput && poCode) {
                        poNumberInput.value = poCode;
                    }

                    // Selecting a PO always means Own Material
                    if (ownMaterialRadio) {
                        ownMaterialRadio.checked = true;
                    }
                    if (clientMaterialRadio) {
                        clientMaterialRadio.checked = false;
                    }
                    syncMaterialTypeUi();

                    loadLinesFromPurchaseOrder(poSelect.value);
                });

                // Load lines on page load if a PO is already selected (after validation error)
                if (poSelect.value) {
                    loadLinesFromPurchaseOrder(poSelect.value);
                }
            }

            // Initial UI sync (respect old('is_client_material'))
            syncMaterialTypeUi();
        });
    </script>
@endsection
