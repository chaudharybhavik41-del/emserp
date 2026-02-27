@extends('layouts.erp')

@section('title', 'Create Material Receipt (GRN)')

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

                    <div class="col-md-3">
                        <label class="form-label">PO Number</label>
                        <input type="text" name="po_number"
                               value="{{ old('po_number') }}"
                               class="form-control form-control-sm">
                    </div>

                    <div class="col-md-3">
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
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-4">
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
                <h5 class="card-title mb-0 h6">GRN Line Items</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="add-line-btn">
                    + Add Line
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle" id="lines-table">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 18%">Item</th>
                            <th style="width: 10%">Category</th>
                            <th style="width: 8%">Grade</th>
                        <th style="width: 9%">Brand</th>
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

    {{-- Inline scripts so they always load, no dependency on @stack --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let lineIndex = 0;

            function makeRow(index) {
                return `
                    <tr data-row-index="${index}">
                        <td>
                            <select name="lines[${index}][item_id]" class="form-select form-select-sm" required>
                                <option value="">-- Select --</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="lines[${index}][material_category]" class="form-select form-select-sm" required>
                                <option value="">-- Select --</option>
                                @foreach($materialCategories as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="text"
                                   name="lines[${index}][grade]"
                                   class="form-control form-control-sm"
                                   placeholder="Grade">
                        </td>
                        <td>
                            <input type="text"
                                   name="lines[${index}][brand]"
                                   class="form-control form-control-sm"
                                   placeholder="Brand">
                        </td>
                        <td>
                            <input type="number"
                                   name="lines[${index}][thickness_mm]"
                                   class="form-control form-control-sm"
                                   min="0" step="1">
                        </td>
                        <td>
                            <input type="number"
                                   name="lines[${index}][width_mm]"
                                   class="form-control form-control-sm"
                                   min="0" step="1">
                        </td>
                        <td>
                            <input type="number"
                                   name="lines[${index}][length_mm]"
                                   class="form-control form-control-sm"
                                   min="0" step="1">
                        </td>
                        <td>
                            <input type="text"
                                   name="lines[${index}][section_profile]"
                                   class="form-control form-control-sm"
                                   placeholder="ISMB300 etc.">
                        </td>
                        <td>
                            <input type="number"
                                   name="lines[${index}][qty_pcs]"
                                   class="form-control form-control-sm"
                                   min="1" step="1" value="1" required>
                        </td>
                        <td>
                            <input type="number"
                                   name="lines[${index}][received_weight_kg]"
                                   class="form-control form-control-sm"
                                   min="0" step="0.001">
                        </td>
                        <td>
                            <select name="lines[${index}][uom_id]" class="form-select form-select-sm" required>
                                <option value="">-- UOM --</option>
                                @foreach($uoms as $uom)
                                    <option value="{{ $uom->id }}">{{ $uom->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="text"
                                   name="lines[${index}][remarks]"
                                   class="form-control form-control-sm">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line-btn">
                                &times;
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

            const addBtn = document.getElementById('add-line-btn');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    addLineRow();
                });
            }

            const tbody = document.querySelector('#lines-table tbody');
            if (tbody) {
                tbody.addEventListener('click', function (e) {
                    if (e.target.classList.contains('remove-line-btn')) {
                        const row = e.target.closest('tr');
                        if (row) row.remove();
                    }
                });
            }

            // Add initial row on load
            addLineRow();

            // Auto-select client from project
            const projectSelect = document.getElementById('project-select');
            const clientSelect = document.getElementById('client-select');
            const projectClientMap = @json($projectClientMap ?? []);

            function updateClientFromProject() {
                if (!projectSelect || !clientSelect) return;
                const projectId = projectSelect.value;
                const clientId = projectClientMap[projectId];
                if (clientId) {
                    clientSelect.value = String(clientId);
                }
            }

            if (projectSelect) {
                projectSelect.addEventListener('change', updateClientFromProject);
                updateClientFromProject(); // run once on load
            }
        });
    </script>
@endsection
