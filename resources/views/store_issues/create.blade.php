@extends('layouts.erp')

@section('title', 'Create Store Issue')

@section('content')
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h1 class="h4 mb-0">Create Store Issue</h1>
                                            </div>

                                            @if($errors->has('general'))
                                                <div class="alert alert-danger alert-dismissible fade show">
                                                    {{ $errors->first('general') }}
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                                </div>
                                            @endif

                                            <form action="{{ route('store-issues.store') }}" method="POST">
                                                @csrf

                                                <div class="card mb-3">
                                                    <div class="card-body">
                                                        <div class="row g-3">
                                                            <div class="col-md-3">
                                                                <label class="form-label">Issue Date</label>
                                                                <input type="date"
                                                                       name="issue_date"
                                                                       value="{{ old('issue_date', now()->format('Y-m-d')) }}"
                                                                       class="form-control form-control-sm @error('issue_date') is-invalid @enderror"
                                                                       required>
                                                                @error('issue_date')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>

                                                            {{-- Requisition selector (for reloading the same page with selected requisition) --}}
                                                            <div class="col-md-4">
                                                                <label class="form-label">Requisition (optional)</label>
                                                                <select id="requisition-selector" class="form-select form-select-sm">
                                                                    <option value="">-- No Requisition --</option>
                                                                    @foreach($requisitions as $req)
                                                                        <option value="{{ $req->id }}" {{ (int) $selectedRequisitionId === $req->id ? 'selected' : '' }}>
                                                                            #{{ $req->id }} - {{ $req->requisition_number ?? ('SR-' . $req->id) }}
                                                                            @if($req->project)
                                                                                [{{ $req->project->code }}]
                                                                            @endif
                                                                            @if(($req->issue_purpose ?? 'general') === 'machine_spare')
                                                                                [Spare: {{ $req->machine?->code ?: ('M#' . $req->machine_id) }}]
                                                                            @endif
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                                <div class="form-text small">
                                                                    Select requisition to load its pending lines.
                                                                </div>
                                                            </div>

                                                            <div class="col-md-5">
                                                                <label class="form-label">Remarks</label>
                                                                <textarea name="remarks" class="form-control form-control-sm @error('remarks') is-invalid @enderror"
                                                                    rows="2">{{ old('remarks') }}</textarea>
                                                                @error('remarks')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                            </div>

                                                            <div class="row g-3 mt-2">
                                                                @if(isset($selectedRequisition) && $selectedRequisition)
                                                                    <input type="hidden" name="store_requisition_id" value="{{ $selectedRequisition->id }}">
                                                                    <input type="hidden" name="issue_purpose" value="{{ $selectedRequisition->issue_purpose ?? 'general' }}">
                                                                    <input type="hidden" name="machine_id" value="{{ $selectedRequisition->machine_id }}">

                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Issue Purpose</label>
                                                                        <div class="form-control form-control-sm bg-light">
                                                                            @if(($selectedRequisition->issue_purpose ?? 'general') === 'machine_spare')
                                                                                Machine Spare
                                                                            @else
                                                                                General
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Machine</label>
                                                                        <div class="form-control form-control-sm bg-light">
                                                                            @if($selectedRequisition->machine)
                                                                                {{ $selectedRequisition->machine->code ? ($selectedRequisition->machine->code . ' - ') : '' }}{{ $selectedRequisition->machine->name }}
                                                                            @else
                                                                                -
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @else
                                                                    <div class="col-md-4">
                                                                        <label class="form-label">Issue Purpose</label>
                                                                        @php $purpose = old('issue_purpose', $selectedIssuePurpose ?? 'general'); @endphp
                                                                        <select id="issue_purpose" name="issue_purpose"
                                                                                class="form-select form-select-sm @error('issue_purpose') is-invalid @enderror">
                                                                            <option value="general" {{ $purpose === 'general' ? 'selected' : '' }}>General</option>
                                                                            <option value="machine_spare" {{ $purpose === 'machine_spare' ? 'selected' : '' }}>Machine Spare</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <label class="form-label">Machine (for Spare)</label>
                                                                        <select id="machine_id" name="machine_id"
                                                                            class="form-select form-select-sm select2 @error('machine_id') is-invalid @enderror">
                                                                            <option value="">-- Select Machine --</option>
                                                                            @foreach($machines as $machine)
                                                                                <option value="{{ $machine->id }}"
                                                                                    data-project-id="{{ (int) ($machine->current_project_id ?? 0) }}"
                                                                                    {{ (int) old('machine_id', (int) ($selectedMachineId ?? 0)) === (int) $machine->id ? 'selected' : '' }}>
                                                                                    {{ $machine->code ? ($machine->code . ' - ') : '' }}{{ $machine->name }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            <div class="row g-3 mt-2">
                                                                @if(isset($selectedRequisition) && $selectedRequisition)
                                                                    {{-- When requisition selected: project/contractor taken from requisition and fixed --}}

                                                                    <div class="col-md-4">
                                                                        <label class="form-label">Project</label>
                                                                        <div class="form-control form-control-sm bg-light">
                                                                            @if($selectedRequisition->project)
                                                                                {{ $selectedRequisition->project->code }} - {{ $selectedRequisition->project->name }}
                                                                            @else
                                                                                <span class="text-muted">No Project (general)</span>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <label class="form-label">Contractor</label>
                                                                        <div class="form-control form-control-sm bg-light">
                                                                            {{ $selectedRequisition->contractor->name ?? '-' }}
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <label class="form-label">Contractor / Person</label>
                                                                        <div class="form-control form-control-sm bg-light">
                                                                            {{ $selectedRequisition->contractor_person_name ?? '-' }}
                                                                        </div>
                                                                    </div>
                                                                @else
                                                                    {{-- General issue: project & contractor selectable --}}
                                                                    <div class="col-md-4">
                                                                        <label class="form-label">Project</label>
                                                                        <select id="project_id" name="project_id"
                                                                            class="form-select form-select-sm select2 @error('project_id') is-invalid @enderror">
                                                                            <option value="">-- Select Project --</option>
                                                                            @foreach($projects as $project)
                                                                                <option value="{{ $project->id }}" {{ (int) old('project_id') === $project->id ? 'selected' : '' }}>
                                                                                    {{ $project->code }} - {{ $project->name }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>

                                                                    <div class="col-md-4">
                                                                        <label class="form-label">Contractor</label>
                                                                        <select id="contractor_party_id" name="contractor_party_id"
                                                                            class="form-select form-select-sm select2 @error('contractor_party_id') is-invalid @enderror">
                                                                            <option value="">-- None / Self --</option>
                                                                            @foreach($contractors as $party)
                                                                                <option value="{{ $party->id }}" {{ (int) old('contractor_party_id') === $party->id ? 'selected' : '' }}>
                                                                                    {{ $party->name }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <label class="form-label">Contractor Person</label>
                                                                        <input type="text" name="contractor_person_name" value="{{ old('contractor_person_name') }}"
                                                                            class="form-control form-control-sm @error('contractor_person_name') is-invalid @enderror" maxlength="100">
                                                                        @error('contractor_person_name')
                                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                                        @enderror
                                                                    </div>
                                                                @endif
                                                            </div>
                                                            </div>
                                                            </div>

                                                            @if(isset($selectedRequisition) && $selectedRequisition)
                                                                {{-- Show requisition summary --}}
                                                                <div class="card mb-3">
                                                                    <div class="card-header py-2">
                                                                        <h5 class="card-title mb-0 h6">
                                                                            Requested Materials (Requisition
                                                                            {{ $selectedRequisition->requisition_number ?? ('SR-' . $selectedRequisition->id) }})
                                                                        </h5>
                                                                    </div>
                                                                    <div class="card-body p-0">
                                                                        <div class="table-responsive">
                                                                            <table class="table table-sm table-bordered mb-0 align-middle">
                                                                                <thead class="table-light">
                                                                                    <tr>
                                                                                        <th style="width: 36%">Item</th>
                                                                                        <th style="width: 10%">UOM</th>
                                                                                        <th style="width: 12%">Required</th>
                                                                                        <th style="width: 12%">Issued</th>
                                                                                        <th style="width: 12%">Pending</th>
                                                                                        <th style="width: 13%">Brand</th>
                                                                                        <th style="width: 5%">Line ID</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    @forelse($selectedRequisition->lines as $line)
                                                                                        @php
            $required = (float) ($line->required_qty ?? 0);
            $issued = (float) ($line->issued_qty ?? 0);
            $pending = max(0, $required - $issued);
            $brand = trim((string) ($line->preferred_make ?? ''));
                                                                                        @endphp
                                                                                        <tr>
                                                                                            <td>
                                                                                                {{ $line->item->name ?? ('Item #' . $line->item_id) }}<br>
                                                                                                <span class="small text-muted">{{ $line->description ?? '' }}</span>
                                                                                            </td>
                                                                                            <td>{{ $line->uom->name ?? '-' }}</td>
                                                                                            <td>{{ number_format($required, 3) }}</td>
                                                                                            <td>{{ number_format($issued, 3) }}</td>
                                                                                            <td>{{ number_format($pending, 3) }}</td>
                                                                                            <td>{{ $brand !== '' ? $brand : '-' }}</td>
                                                                                            <td>{{ $line->id }}</td>
                                                                                        </tr>
                                                                                    @empty
                                                                                        <tr>
                                                                                            <td colspan="7" class="text-center text-muted">
                                                                                                No lines on this requisition.
                                                                                            </td>
                                                                                        </tr>
                                                                                    @endforelse
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            <div class="card">
                                                                <div class="card-header d-flex justify-content-between align-items-center py-2">
                                                                    <h5 class="card-title mb-0 h6">Issue Lines (Select Stock Items)</h5>
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="add-line-btn">
                                                                        + Add Line
                                                                    </button>
                                                                </div>
                                                                <div class="card-body p-0">
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-bordered mb-0 align-middle" id="lines-table">
                                                                            <thead class="table-light">
                                                                                <tr>
                                                                                    <th style="width: 42%">Available Stock Item</th>
                                                                                    @if(isset($selectedRequisition) && $selectedRequisition)
                                                                                        <th style="width: 28%">Requisition Line</th>
                                                                                        <th style="width: 15%">Issue Qty (Req UOM)</th>
                                                                                    @else
                                                                                        <th style="width: 25%">Issue Qty</th>
                                                                                    @endif
                                                                                    <th style="width: 20%">Line Remarks</th>
                                                                                    <th style="width: 5%"></th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                {{-- rows added by JS --}}
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="mt-3 d-flex justify-content-between">
                                                                <a href="{{ route('store-issues.index') }}" class="btn btn-sm btn-outline-secondary">
                                                                    Cancel
                                                                </a>
                                                                <button type="submit" class="btn btn-sm btn-primary">
                                                                    Save Issue
                                                                </button>
                                                            </div>
                                                            </form>



                                                            @php
    // Prepare requisition lines for JS
    $reqLinesForJs = [];
    if (isset($selectedRequisition) && $selectedRequisition) {
        foreach ($selectedRequisition->lines as $line) {
            $required = (float) ($line->required_qty ?? 0);
            $issued = (float) ($line->issued_qty ?? 0);
            $pending = max(0, $required - $issued);

            $reqLinesForJs[] = [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'label' => ($line->item->code ?? '') . ' - ' . ($line->item->name ?? ('Item #' . $line->item_id)),
                'uom' => $line->uom->name ?? '',
                'pending_qty' => $pending,
                'brand' => trim((string) ($line->preferred_make ?? '')),
            ];
        }
    }

    // Prepare stock items for JS
    $stockItemsForJs = [];
    foreach ($stockItems as $stock) {
        $stockItemsForJs[] = [
            'id' => $stock->id,
            'item_id' => $stock->item_id,
            'item_code' => $stock->item->code ?? '',
            'item_name' => $stock->item->name ?? ('Item #' . $stock->item_id),
            'uom' => $stock->item->uom->name ?? '',
            'available' => (float) ($stock->weight_kg_available ?? 0),
            'project_code' => $stock->project->code ?? null,
            'brand' => trim((string) ($stock->brand ?? '')),
            'source_reference' => trim((string) ($stock->source_reference ?? '')),
            'source_type' => trim((string) ($stock->source_type ?? '')),
        ];
    }
                                                            @endphp
        @push('scripts')

                                                                   <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

                    <script>
                    document.addEventListener('DOMContentLoaded', function () {

                        let lineIndex = 0;

                        const REQUISITION_LINES = @json($reqLinesForJs ?? []);
                        const STOCK_ITEMS = @json($stockItemsForJs ?? []);
                        const hasRequisition = REQUISITION_LINES.length > 0;
                        const requisitionSelector = document.getElementById('requisition-selector');
                        const issuePurposeSelect = document.getElementById('issue_purpose');
                        const machineSelect = document.getElementById('machine_id');
                        const projectSelect = document.getElementById('project_id');

                        requisitionSelector?.addEventListener('change', function () {
                            const url = new URL(window.location.href);
                            if (this.value) {
                                url.searchParams.set('store_requisition_id', this.value);
                            } else {
                                url.searchParams.delete('store_requisition_id');
                                if (issuePurposeSelect?.value) {
                                    url.searchParams.set('issue_purpose', issuePurposeSelect.value);
                                }
                                if (machineSelect?.value) {
                                    url.searchParams.set('machine_id', machineSelect.value);
                                } else {
                                    url.searchParams.delete('machine_id');
                                }
                            }
                            window.location.href = url.toString();
                        });

                        function toggleMachineRequirement() {
                            if (!issuePurposeSelect || !machineSelect) return;
                            const isMachineSpare = issuePurposeSelect.value === 'machine_spare';
                            machineSelect.required = isMachineSpare;
                            if (!isMachineSpare) {
                                machineSelect.value = '';
                            }
                        }

                        issuePurposeSelect?.addEventListener('change', function () {
                            toggleMachineRequirement();
                            if (!hasRequisition) {
                                const url = new URL(window.location.href);
                                url.searchParams.set('issue_purpose', this.value || 'general');
                                if (machineSelect?.value) {
                                    url.searchParams.set('machine_id', machineSelect.value);
                                } else {
                                    url.searchParams.delete('machine_id');
                                }
                                window.location.href = url.toString();
                            }
                        });

                        machineSelect?.addEventListener('change', function () {
                            const opt = this.selectedOptions[0];
                            const projectId = parseInt(opt?.dataset?.projectId || '0', 10);
                            if (projectId > 0 && projectSelect && !projectSelect.value) {
                                projectSelect.value = String(projectId);
                                $(projectSelect).trigger('change');
                            }
                        });

                        /* ---------------- SELECT2 INIT ---------------- */

                        function initSelect2(scope = document) {
                            $(scope).find('.select2').select2({
                                width: '100%',
                                placeholder: "-- Select Stock Item --",
                                allowClear: true
                            });
                        }

                        function refreshSelect2(element) {
                            $(element).select2('destroy');
                            $(element).select2({
                                width: '100%',
                                placeholder: "-- Select Stock Item --",
                                allowClear: true
                            });
                        }

                        /* ---------------- HELPERS ---------------- */

                        function normBrand(v) {
                            return String(v || '').trim().toLowerCase();
                        }

                        function buildStockOptionsHtml(filterItemId, filterBrand) {

                            let html = '<option value="">-- Select Stock Item --</option>';
                            const brandNorm = normBrand(filterBrand);

                            STOCK_ITEMS.forEach(function (s) {

                                if (filterItemId && String(s.item_id) !== String(filterItemId)) return;
                                if ((s.available || 0) <= 0) return;
                                if (brandNorm && normBrand(s.brand) !== brandNorm) return;

                                let label = s.item_name;
                                if (s.item_code) {
                                    label = s.item_code + ' - ' + label;
                                }

                                if (s.brand) label += ' | Brand: ' + s.brand;
                                if (s.project_code) label += ' [' + s.project_code + ']';
                                else label += ' [GENERAL]';
                                if (s.source_reference) label += ' | Ref: ' + s.source_reference;
                                if (s.source_type) label += ' | Source: ' + String(s.source_type).toUpperCase();

                                label += ' - Avl: ' + (s.available || 0).toFixed(3) + ' ' + (s.uom || '');

                                html += `<option value="${s.id}"
                                            data-item-id="${s.item_id}"
                                            data-brand="${s.brand || ''}"
                                            data-available="${s.available || 0}">
                                            ${label}
                                        </option>`;
                            });

                            return html;
                        }

                        function buildRequisitionOptionsHtml() {

                            let html = '<option value="">-- Select Requisition Line --</option>';

                            REQUISITION_LINES.forEach(function (line) {

                                const pending = line.pending_qty || 0;
                                const brand = line.brand || '';

                                let label = line.label;
                                if (brand) label += ' | Brand: ' + brand;

                                label += ` (Pending: ${pending.toFixed(3)} ${line.uom || ''})`;

                                html += `<option value="${line.id}"
                                            data-item-id="${line.item_id}"
                                            data-brand="${brand}"
                                            data-pending="${pending}">
                                            ${label}
                                        </option>`;
                            });

                            return html;
                        }

                        /* ---------------- ROW CREATE ---------------- */

                        function makeRow(index) {

                            let row = `<tr data-row-index="${index}">`;

                            row += `
                                <td>
                                    <select name="lines[${index}][store_stock_item_id]"
                                            class="form-select form-select-sm stock-item-select select2"
                                            style="width:100%;" required>
                                    </select>
                                </td>
                            `;

                            if (hasRequisition) {
                                row += `
                                    <td>
                                        <select name="lines[${index}][store_requisition_line_id]"
                                                class="form-select form-select-sm requisition-line-select" required>
                                            ${buildRequisitionOptionsHtml()}
                                        </select>
                                        <div class="small text-muted pending-label"></div>
                                    </td>
                                    <td>
                                        <input type="number"
                                               name="lines[${index}][issue_qty]"
                                               class="form-control form-control-sm issue-qty-input"
                                               min="0.001" step="0.001">
                                    </td>
                                `;
                            } else {
                                row += `
                                    <td>
                                        <input type="number"
                                               name="lines[${index}][issue_qty]"
                                               class="form-control form-control-sm issue-qty-input"
                                               min="0.001" step="0.001">
                                    </td>
                                `;
                            }

                            row += `
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
                            </tr>`;

                            return row;
                        }

                        /* ---------------- APPLY REQUISITION ---------------- */

                        function applyRequisitionSelectionToRow(row) {

                            const reqSelect = row.querySelector('.requisition-line-select');
                            const stockSelect = row.querySelector('.stock-item-select');
                            const qtyInput = row.querySelector('.issue-qty-input');
                            const pendingLabel = row.querySelector('.pending-label');

                            if (!reqSelect || !stockSelect) return;

                            const opt = reqSelect.selectedOptions[0];

                            if (!opt || !opt.value) {
                                stockSelect.innerHTML = '<option value="">-- Select Stock Item --</option>';
                                stockSelect.disabled = true;
                                refreshSelect2(stockSelect);
                                return;
                            }

                            const itemId = opt.dataset.itemId;
                            const pending = parseFloat(opt.dataset.pending || 0);
                            const reqBrand = opt.dataset.brand || '';

                            stockSelect.innerHTML = buildStockOptionsHtml(itemId, reqBrand);
                            stockSelect.disabled = false;
                            refreshSelect2(stockSelect);

                            if (pendingLabel) {
                                pendingLabel.textContent = 'Pending: ' + pending.toFixed(3);
                            }

                            if (qtyInput) qtyInput.value = pending > 0 ? pending.toFixed(3) : '';
                        }

                        /* ---------------- ADD ROW ---------------- */

                        function addLineRow() {

                            const tbody = document.querySelector('#lines-table tbody');
                            if (!tbody) return;

                            tbody.insertAdjacentHTML('beforeend', makeRow(lineIndex));

                            const row = tbody.querySelector(`tr[data-row-index="${lineIndex}"]`);
                            lineIndex++;

                            const stockSelect = row.querySelector('.stock-item-select');

                            if (!hasRequisition) {
                                stockSelect.innerHTML = buildStockOptionsHtml(null, null);
                            } else {
                                stockSelect.disabled = true;
                            }

                            initSelect2(row);

                            if (hasRequisition) {
                                const reqSelect = row.querySelector('.requisition-line-select');
                                reqSelect.addEventListener('change', function () {
                                    applyRequisitionSelectionToRow(row);
                                });
                            }
                        }

                        /* ---------------- EVENTS ---------------- */

                        document.getElementById('add-line-btn')
                            ?.addEventListener('click', addLineRow);

                        document.querySelector('#lines-table tbody')
                            ?.addEventListener('click', function (e) {
                                if (e.target.closest('.remove-line-btn')) {
                                    e.target.closest('tr').remove();
                                }
                            });

                        /* ---------------- INIT ---------------- */

                        toggleMachineRequirement();
                        addLineRow();

                    });


                    </script>

                    <script>
            $(document).ready(function() {
                // Init all select2
                $('.select2').select2({
                    width: '100%',
                    placeholder: "-- Select Option --",
                    allowClear: true
                });

                if ($('#project_id').length) {
                    $('#project_id').select2('open');
                }
            });
            </script>
        @endpush

@endsection
