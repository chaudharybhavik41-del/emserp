@extends('layouts.erp')

@section('title', 'Create Store Requisition')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Create Store Requisition</h1>
    </div>

    @if($errors->has('general'))
        <div class="alert alert-danger">
            {{ $errors->first('general') }}
        </div>
    @endif

    <form action="{{ route('store-requisitions.store') }}" method="POST">
        @csrf

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Requisition Date</label>
                        <input type="date" name="requisition_date"
                               value="{{ old('requisition_date', now()->format('Y-m-d')) }}"
                               class="form-control form-control-sm @error('requisition_date') is-invalid @enderror" required>
                        @error('requisition_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Project</label>
                        <select name="project_id" id="project-id"
                                class="form-select form-select-sm @error('project_id') is-invalid @enderror" required>
                            <option value="">-- Select Project --</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}"
                                    {{ (int)old('project_id') === $project->id ? 'selected' : '' }}>
                                    {{ $project->code }} - {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('project_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Contractor</label>
                        <select name="contractor_party_id"
                                class="form-select form-select-sm @error('contractor_party_id') is-invalid @enderror">
                            <option value="">-- None / Self --</option>
                            @foreach($contractors as $party)
                                <option value="{{ $party->id }}"
                                    {{ (int)old('contractor_party_id') === $party->id ? 'selected' : '' }}>
                                    {{ $party->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('contractor_party_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text small">
                            Leave blank for in-house consumption.
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Person Name (Site / Contractor)</label>
                        <input type="text" name="contractor_person_name"
                               value="{{ old('contractor_person_name') }}"
                               class="form-control form-control-sm @error('contractor_person_name') is-invalid @enderror"
                               maxlength="100">
                        @error('contractor_person_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text small">
                            Name of person requesting material (if applicable).
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label">Requested By (User)</label>
                        <input type="text" class="form-control form-control-sm" value="{{ auth()->user()->name }}" disabled>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2">{{ old('remarks') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <h5 class="card-title mb-0 h6">Requested Materials</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="add-line-btn">
                    + Add Line
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle" id="lines-table">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 22%">Item</th>
                            <th style="width: 10%">UOM</th>
                            <th style="width: 10%">Required Qty</th>
                            <th style="width: 18%">Description</th>
                            <th style="width: 14%">Brand</th>
                            <th style="width: 14%">Segment Ref</th>
                            <th style="width: 8%">Remarks</th>
                            <th style="width: 4%"></th>
                        </tr>
                        </thead>
                        <tbody>
                        {{-- rows will be added by JS --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex justify-content-between">
            <a href="{{ route('store-requisitions.index') }}" class="btn btn-sm btn-outline-secondary">
                Cancel
            </a>
            <button type="submit" class="btn btn-sm btn-primary">
                Save Requisition
            </button>
        </div>
    </form>

    {{-- Select2 styles for item dropdown --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>

    <style>
        .select2-container--open { z-index: 3000 !important; }
        .select2-container .select2-selection--single { height: 31px; padding: 2px 6px; font-size: .875rem; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 26px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 26px; }
        .brand-help { line-height: 1.1; }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let lineIndex = 0;

            // Only non-raw items (non_raw_only=1) will be returned from this endpoint
            const ITEM_SEARCH_URL = '{{ route('ajax.items.search', ['non_raw_only' => 1]) }}';

            // AJAX endpoint that returns brands which have AVAILABLE stock for selected item
            const BRAND_OPTIONS_URL = '{{ route('ajax.store-requisitions.available-brands') }}';

            const tbody  = document.querySelector('#lines-table tbody');
            const addBtn = document.getElementById('add-line-btn');

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function getProjectId() {
                const el = document.getElementById('project-id');
                return el ? (el.value || '') : '';
            }

            async function refreshBrandOptionsForRow(row, itemId) {
                const brandSelect = row.querySelector('.brand-select');
                const helpEl = row.querySelector('.brand-help');
                if (!brandSelect) return;

                brandSelect.innerHTML = '<option value="">Loading…</option>';
                brandSelect.disabled = true;
                if (helpEl) helpEl.textContent = '';

                const projectId = getProjectId();

                const url = BRAND_OPTIONS_URL
                    + '?item_id=' + encodeURIComponent(itemId)
                    + (projectId ? ('&project_id=' + encodeURIComponent(projectId)) : '');

                try {
                    const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await resp.json();

                    const brands = Array.isArray(data.brands) ? data.brands : [];

                    let html = '<option value="">-- Any Brand --</option>';
                    brands.forEach(function (b) {
                        html += '<option value="' + escapeHtml(b) + '">' + escapeHtml(b) + '</option>';
                    });

                    brandSelect.innerHTML = html;
                    brandSelect.disabled = false;

                    if (brands.length === 0 && helpEl) {
                        helpEl.textContent = 'No available stock brands found for this item.';
                    }

                    // If only one brand is in stock, auto-select it (still user can change)
                    if (brands.length === 1) {
                        brandSelect.value = brands[0];
                    }
                } catch (e) {
                    brandSelect.innerHTML = '<option value="">-- Any Brand --</option>';
                    brandSelect.disabled = false;
                    if (helpEl) helpEl.textContent = 'Failed to load brands.';
                }
            }

            function makeRow(index) {
                return `
                    <tr data-row-index="${index}">
                        <td>
                            <select name="lines[${index}][item_id]"
                                    class="form-select form-select-sm item-select"
                                    data-row-index="${index}"
                                    required>
                                <option value="">Search item…</option>
                            </select>
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
                            <input type="number" name="lines[${index}][required_qty]" class="form-control form-control-sm"
                                   min="0.001" step="0.001" value="1" required>
                        </td>
                        <td>
                            <input type="text" name="lines[${index}][description]" class="form-control form-control-sm">
                        </td>
                        <td>
                            <select name="lines[${index}][preferred_make]" class="form-select form-select-sm brand-select">
                                <option value="">-- Any Brand --</option>
                            </select>
                            <div class="small text-muted brand-help"></div>
                        </td>
                        <td>
                            <input type="text" name="lines[${index}][segment_reference]" class="form-control form-control-sm">
                        </td>
                        <td>
                            <input type="text" name="lines[${index}][remarks]" class="form-control form-control-sm">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line-btn">
                                &times;
                            </button>
                        </td>
                    </tr>
                `;
            }

            function initItemSelect(row) {
                const selectEl = row.querySelector('.item-select');
                const uomSelect = row.querySelector('select[name$="[uom_id]"]');
                if (!selectEl) return;

                $(selectEl).select2({
                    dropdownParent: $('body'),
                    placeholder: 'Search item…',
                    allowClear: true,
                    ajax: {
                        url: ITEM_SEARCH_URL,
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { q: params.term || '' };
                        },
                        processResults: function (data) {
                            const results = (data && data.results) ? data.results : [];
                            return {
                                results: results.map(function (item) {
                                    return {
                                        id: item.id,
                                        text: item.text,
                                        uom_id: item.uom_id,
                                        uom_name: item.uom_name
                                    };
                                })
                            };
                        }
                    }
                });

                // Auto-fill UOM when an item is selected + load brands based on stock
                $(selectEl).on('select2:select', function (e) {
                    const data = e.params.data;
                    if (uomSelect && data.uom_id) {
                        uomSelect.value = String(data.uom_id);
                    }

                    // Refresh brand dropdown based on available stock of selected item
                    if (data && data.id) {
                        refreshBrandOptionsForRow(row, data.id);
                    }
                });

                $(selectEl).on('select2:clear', function () {
                    const brandSelect = row.querySelector('.brand-select');
                    const helpEl = row.querySelector('.brand-help');
                    if (brandSelect) {
                        brandSelect.innerHTML = '<option value="">-- Any Brand --</option>';
                    }
                    if (helpEl) helpEl.textContent = '';
                });
            }

            function addLineRow() {
                if (!tbody) return;
                tbody.insertAdjacentHTML('beforeend', makeRow(lineIndex));

                const row = tbody.querySelector('tr[data-row-index="' + lineIndex + '"]');
                lineIndex++;

                if (row) {
                    initItemSelect(row);
                }
            }

            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    addLineRow();
                });
            }

            if (tbody) {
                tbody.addEventListener('click', function (e) {
                    const removeBtn = e.target.closest('.remove-line-btn');
                    if (!removeBtn) return;

                    const row = removeBtn.closest('tr');
                    if (row) {
                        row.remove();
                    }
                });
            }

            // When project changes, refresh brand options for all selected items (scope rules depend on project)
            const projectEl = document.getElementById('project-id');
            if (projectEl) {
                projectEl.addEventListener('change', function () {
                    const rows = document.querySelectorAll('#lines-table tbody tr');
                    rows.forEach(function (row) {
                        const itemSelect = row.querySelector('.item-select');
                        const itemId = itemSelect ? $(itemSelect).val() : null;
                        if (itemId) {
                            refreshBrandOptionsForRow(row, itemId);
                        }
                    });
                });
            }

            // Add initial row
            addLineRow();
        });
    </script>
@endsection
