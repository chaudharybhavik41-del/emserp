@extends('layouts.erp')

@section('title', 'Create Gate Pass')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Create Gate Pass</h1>
    </div>

    @if($errors->has('general'))
        <div class="alert alert-danger">
            {{ $errors->first('general') }}
        </div>
    @endif

    <form action="{{ route('gate-passes.store') }}" method="POST">
        @csrf

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Gate Pass Date</label>
                        <input type="date"
                               name="gatepass_date"
                               class="form-control form-control-sm @error('gatepass_date') is-invalid @enderror"
                               value="{{ old('gatepass_date', now()->format('Y-m-d')) }}">
                        @error('gatepass_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Time</label>
                        <input type="time"
                               name="gatepass_time"
                               class="form-control form-control-sm @error('gatepass_time') is-invalid @enderror"
                               value="{{ old('gatepass_time') }}">
                        @error('gatepass_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select name="type"
                                id="gatepass-type"
                                class="form-select form-select-sm @error('type') is-invalid @enderror">
                            <option value="project_material" {{ old('type') === 'machinery_maintenance' ? '' : 'selected' }}>
                                Project Material
                            </option>
                            <option value="machinery_maintenance" {{ old('type') === 'machinery_maintenance' ? 'selected' : '' }}>
                                Machinery Maintenance
                            </option>
                        </select>
                        @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Project</label>
                        <select name="project_id"
                                id="project_id"
                                class="form-select form-select-sm @error('project_id') is-invalid @enderror">
                            <option value="">General / Store / Outside Work</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}"
                                        data-address="{{ $project->site_location }}"
                                    {{ (string) old('project_id') === (string) $project->id ? 'selected' : '' }}>
                                    {{ $project->code }} - {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('project_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                
            {{-- Optional: Link to a Store Issue (Project Material only) --}}
            @if(isset($issues) && $issues->isNotEmpty())
                <div class="row g-3 mt-3" id="store-issue-link-row" style="display: none;">
                    <div class="col-md-6">
                        <label class="form-label form-label-sm">Link Store Issue (optional)</label>
                        <select name="store_issue_id" id="store_issue_id" class="form-select form-select-sm">
                            @php
                                $selectedIssueId = old('store_issue_id', request('store_issue_id'));
                            @endphp
                            <option value="">-- None --</option>
                            @foreach($issues as $iss)
                                <option value="{{ $iss->id }}" @selected((string)$selectedIssueId === (string)$iss->id)>
                                    {{ $iss->issue_number }} @if($iss->project) ({{ $iss->project->code }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Selecting an issue will auto-fill gate pass lines and link them to stock for better return tracking.
                        </div>
                    </div>
                </div>
            @endif

<div class="row g-3 mt-3">
                    <div class="col-md-3">
                        <label class="form-label">Contractor</label>
                        <select name="contractor_party_id"
                                class="form-select form-select-sm @error('contractor_party_id') is-invalid @enderror">
                            <option value="">-- Select Contractor --</option>
                            @foreach($parties as $party)
                                <option value="{{ $party->id }}"
                                    {{ (string) old('contractor_party_id') === (string) $party->id ? 'selected' : '' }}>
                                    {{ $party->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('contractor_party_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Used mainly for project material through contractors.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Party / Vendor</label>
                        <select name="to_party_id"
                                class="form-select form-select-sm @error('to_party_id') is-invalid @enderror">
                            <option value="">-- Select Party / Vendor --</option>
                            @foreach($parties as $party)
                                <option value="{{ $party->id }}"
                                    {{ (string) old('to_party_id') === (string) $party->id ? 'selected' : '' }}>
                                    {{ $party->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('to_party_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">For machinery maintenance, usually the service vendor.</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Vehicle No.</label>
                        <input type="text"
                               name="vehicle_number"
                               class="form-control form-control-sm @error('vehicle_number') is-invalid @enderror"
                               value="{{ old('vehicle_number') }}">
                        @error('vehicle_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Driver Name</label>
                        <input type="text"
                               name="driver_name"
                               class="form-control form-control-sm @error('driver_name') is-invalid @enderror"
                               value="{{ old('driver_name') }}">
                        @error('driver_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Transport Mode</label>
                        <input type="text"
                               name="transport_mode"
                               class="form-control form-control-sm @error('transport_mode') is-invalid @enderror"
                               value="{{ old('transport_mode', 'Vehicle') }}">
                        @error('transport_mode')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <label class="form-label">Address</label>
                        <textarea name="address"
                                  id="gatepass-address"
                                  rows="2"
                                  class="form-control form-control-sm @error('address') is-invalid @enderror"
                        >{{ old('address') }}</textarea>
                        @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Auto-fills from project site; editable for minor changes.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Header Returnable?</label>
                        <div class="form-check mt-1">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value="1"
                                   id="is_returnable"
                                   name="is_returnable"
                                {{ old('is_returnable') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_returnable">
                                Returnable materials / machinery
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Reason</label>
                        <input type="text"
                               name="reason"
                               class="form-control form-control-sm @error('reason') is-invalid @enderror"
                               value="{{ old('reason') }}">
                        @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks"
                                  rows="2"
                                  class="form-control form-control-sm @error('remarks') is-invalid @enderror"
                        >{{ old('remarks') }}</textarea>
                        @error('remarks')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Project Material Lines --}}
        <div class="card mb-3" id="project-material-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Project Material Lines</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-material-line-btn">
                    + Add Line
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th style="width: 30%">Item</th>
                            <th style="width: 10%">UOM</th>
                            <th style="width: 10%" class="text-end">Qty</th>
                            <th style="width: 10%">Returnable?</th>
                            <th style="width: 15%">Expected Return</th>
                            <th>Remarks</th>
                            <th style="width: 5%"></th>
                        </tr>
                        </thead>
                        <tbody id="material-lines-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Machinery Maintenance Lines --}}
        <div class="card mb-3" id="machinery-card" style="display: none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Machinery Lines</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-machine-line-btn">
                    + Add Line
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th style="width: 35%">Machine</th>
                            <th style="width: 10%" class="text-end">Qty</th>
                            <th style="width: 15%">Expected Return</th>
                            <th>Remarks</th>
                            <th style="width: 5%"></th>
                        </tr>
                        </thead>
                        <tbody id="machine-lines-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('gate-passes.index') }}" class="btn btn-sm btn-secondary">
                Cancel
            </a>
            <button type="submit" class="btn btn-sm btn-primary">
                Save Gate Pass
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeSelect = document.getElementById('gatepass-type');
            const projectCard = document.getElementById('project-material-card');
            const machineCard = document.getElementById('machinery-card');
            const storeIssueRow = document.getElementById('store-issue-link-row');
            const storeIssueSelect = document.getElementById('store_issue_id');

            function syncTypeVisibility() {
                const type = typeSelect.value;
                if (type === 'machinery_maintenance') {
                    projectCard.style.display = 'none';
                    machineCard.style.display = '';
                    if (storeIssueRow) storeIssueRow.style.display = 'none';
                } else {
                    projectCard.style.display = '';
                    machineCard.style.display = 'none';
                    if (storeIssueRow) storeIssueRow.style.display = '';
                }
            }

            if (typeSelect) {
                typeSelect.addEventListener('change', function () {
                    syncTypeVisibility();

                    // When switching types, ensure at least one line exists for active card
                    if (typeSelect.value === 'machinery_maintenance') {
                        if (machineBody && machineBody.children.length === 0) {
                            addMachineLine();
                        }
                    } else {
                        if (materialBody && materialBody.children.length === 0) {
                            if (storeIssueSelect && storeIssueSelect.value) {
                                loadIssueLines(storeIssueSelect.value);
                            } else {
                                addMaterialLine();
                            }
                        }
                    }
                });

                syncTypeVisibility();
            }

            // Address autofill from project
            const projectSelect = document.getElementById('project_id');
            const contractorSelect = document.getElementById('contractor_party_id');
            const addressField = document.getElementById('gatepass-address');

            function autoFillAddressFromProject() {
                if (!projectSelect || !addressField) return;
                const opt = projectSelect.selectedOptions[0];
                if (!opt) return;
                const addr = opt.getAttribute('data-address') || '';
                // Only auto-fill if address is empty or exactly matches previous project's address
                if (!addressField.value || addressField.dataset.autofilled === '1') {
                    addressField.value = addr;
                    addressField.dataset.autofilled = '1';
                }
            }

            if (projectSelect && addressField) {
                projectSelect.addEventListener('change', function () {
                    autoFillAddressFromProject();
                });

                // If project preselected and address empty, auto-fill on load
                if (!addressField.value) {
                    autoFillAddressFromProject();
                }

                // If user edits address manually, stop auto-overwriting
                addressField.addEventListener('input', function () {
                    if (addressField.value.trim() !== '') {
                        addressField.dataset.autofilled = '0';
                    }
                });
            }

            // Dynamic lines
            let materialIndex = 0;
            let machineIndex = 0;
            const materialBody = document.getElementById('material-lines-body');
            const machineBody = document.getElementById('machine-lines-body');

            function escapeHtml(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function clearMaterialLines() {
                if (!materialBody) return;
                materialBody.innerHTML = '';
                materialIndex = 0;
            }

            function addMaterialLine() {
                const idx = materialIndex++;
                const row = `
                    <tr data-row-index="${idx}">
                        <td>
                            <select name="lines[${idx}][item_id]" class="form-select form-select-sm" required>
                                <option value="">-- Select Item --</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->code }} - {{ $item->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="lines[${idx}][uom_id]" class="form-select form-select-sm" required>
                                <option value="">-- UOM --</option>
                                @foreach($uoms as $uom)
                                    <option value="{{ $uom->id }}">{{ $uom->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number"
                                   name="lines[${idx}][qty]"
                                   class="form-control form-control-sm text-end"
                                   min="0.001"
                                   step="0.001"
                                   value="1"
                                   required>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="lines[${idx}][is_returnable]" value="1" checked>
                        </td>
                        <td>
                            <input type="date"
                                   name="lines[${idx}][expected_return_date]"
                                   class="form-control form-control-sm">
                        </td>
                        <td>
                            <input type="text"
                                   name="lines[${idx}][remarks]"
                                   class="form-control form-control-sm">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line-btn">&times;</button>
                        </td>
                    </tr>
                `;
                materialBody.insertAdjacentHTML('beforeend', row);
            }

            function addIssueLinkedMaterialLine(line) {
                const idx = materialIndex++;

                const itemText = `${escapeHtml(line.item_code)} - ${escapeHtml(line.item_name)}`;
                const uomText = escapeHtml(line.uom_name);

                const qty = (typeof line.qty === 'number') ? line.qty : parseFloat(line.qty || '0');
                const maxQty = isFinite(qty) ? qty : 0;

                const row = `
                    <tr data-row-index="${idx}" data-linked="1">
                        <td>
                            <input type="hidden" name="lines[${idx}][store_issue_line_id]" value="${escapeHtml(line.id)}">
                            <input type="hidden" name="lines[${idx}][store_stock_item_id]" value="${escapeHtml(line.store_stock_item_id)}">
                            <input type="hidden" name="lines[${idx}][item_id]" value="${escapeHtml(line.item_id)}">

                            <div class="small fw-semibold">${itemText}</div>
                            <div class="small text-muted">Issue Line #${escapeHtml(line.id)} | Stock #${escapeHtml(line.store_stock_item_id)}</div>
                        </td>
                        <td>
                            <input type="hidden" name="lines[${idx}][uom_id]" value="${escapeHtml(line.uom_id)}">
                            <div class="small">${uomText}</div>
                        </td>
                        <td>
                            <input type="number"
                                   name="lines[${idx}][qty]"
                                   class="form-control form-control-sm text-end"
                                   min="0.001"
                                   step="0.001"
                                   value="${maxQty}"
                                   max="${maxQty}"
                                   required>
                            <div class="form-text small">Max: ${maxQty}</div>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="lines[${idx}][is_returnable]" value="1" checked>
                        </td>
                        <td>
                            <input type="date"
                                   name="lines[${idx}][expected_return_date]"
                                   class="form-control form-control-sm">
                        </td>
                        <td>
                            <input type="text"
                                   name="lines[${idx}][remarks]"
                                   class="form-control form-control-sm">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line-btn">&times;</button>
                        </td>
                    </tr>
                `;

                materialBody.insertAdjacentHTML('beforeend', row);
            }

            async function loadIssueLines(issueId) {
                if (!materialBody) return;

                clearMaterialLines();

                if (!issueId) {
                    addMaterialLine();
                    return;
                }

                const baseUrl = `{{ url('ajax/store-issues') }}`;
                const url = `${baseUrl}/${issueId}/lines`;

                try {
                    const res = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        }
                    });

                    if (!res.ok) {
                        throw new Error('Failed to load issue lines');
                    }

                    const data = await res.json();

                    // Auto-fill project + contractor if present in response
                    if (data.issue) {
                        if (projectSelect && data.issue.project_id) {
                            projectSelect.value = data.issue.project_id;
                            projectSelect.dispatchEvent(new Event('change'));
                        }
                        if (contractorSelect && data.issue.contractor_party_id) {
                            contractorSelect.value = data.issue.contractor_party_id;
                        }
                    }

                    if (Array.isArray(data.lines) && data.lines.length) {
                        data.lines.forEach(addIssueLinkedMaterialLine);
                    } else {
                        addMaterialLine();
                    }
                } catch (err) {
                    console.error(err);
                    addMaterialLine();
                }
            }

            function addMachineLine() {
                const idx = machineIndex++;
                const row = `
                    <tr data-row-index="${idx}">
                        <td>
                            <select name="lines[${idx}][machine_id]" class="form-select form-select-sm" required>
                                <option value="">-- Select Machine --</option>
                                @foreach($machines as $machine)
                                    <option value="{{ $machine->id }}">{{ $machine->code }} - {{ $machine->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number"
                                   name="lines[${idx}][qty]"
                                   class="form-control form-control-sm text-end"
                                   min="0.001"
                                   step="0.001"
                                   value="1"
                                   required>
                        </td>
                        <td>
                            <input type="date"
                                   name="lines[${idx}][expected_return_date]"
                                   class="form-control form-control-sm"
                                   value="{{ now()->addDays(7)->format('Y-m-d') }}">
                        </td>
                        <td>
                            <input type="text"
                                   name="lines[${idx}][remarks]"
                                   class="form-control form-control-sm">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line-btn">&times;</button>
                        </td>
                    </tr>
                `;
                machineBody.insertAdjacentHTML('beforeend', row);
            }

            document.getElementById('add-material-line-btn')?.addEventListener('click', function () {
                addMaterialLine();
            });

            document.getElementById('add-machine-line-btn')?.addEventListener('click', addMachineLine);

            materialBody?.addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-line-btn')) {
                    e.target.closest('tr')?.remove();
                }
            });
            machineBody?.addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-line-btn')) {
                    e.target.closest('tr')?.remove();
                }
            });

            if (storeIssueSelect) {
                storeIssueSelect.addEventListener('change', function () {
                    if (typeSelect.value !== 'project_material') return;
                    loadIssueLines(storeIssueSelect.value);
                });
            }

            // Start with one line in the active card
            if (typeSelect.value === 'machinery_maintenance') {
                addMachineLine();
            } else {
                if (storeIssueSelect && storeIssueSelect.value) {
                    loadIssueLines(storeIssueSelect.value);
                } else {
                    addMaterialLine();
                }
            }
        });
    </script>
@endpush



