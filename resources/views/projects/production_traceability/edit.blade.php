@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-upc-scan"></i> Traceability Capture</h2>
            <div class="text-muted small">
                DPR #{{ $dpr->id }} |
                Activity: <span class="fw-semibold">{{ $activity->name }}</span> |
                Plan: <span class="fw-semibold">{{ $dpr->plan?->plan_number }}</span>
            </div>
        </div>
        <a href="{{ route('projects.production-dprs.show', [$project, $dpr]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    @php
        // Controller already passes these flags, but keep fallback.
        $isCutting = isset($isCutting) ? (bool)$isCutting : (str_contains(strtoupper($activity->code ?? ''), 'CUT') || str_contains(strtoupper($activity->name ?? ''), 'CUT'));
        $isFitup = isset($isFitup) ? (bool)$isFitup : (bool) ($activity->is_fitupp ?? false);

        $traceLines = $traceLines ?? $dpr->lines;
        $capturedPieces = $capturedPieces ?? [];

        $cuttingPlanPlates = $cuttingPlanPlates ?? [];
        $cuttingPlanPlateMap = $cuttingPlanPlateMap ?? [];

        $activeTab = old('mode', 'batch');
        if (!in_array($activeTab, ['batch','single'])) { $activeTab = 'batch'; }

        $oldBatchRemnants = old('batch_remnants', []);
        $initialRemnantRows = max(2, is_array($oldBatchRemnants) ? count($oldBatchRemnants) : 2);

        // If DPR header already has selected mother plate (store), prefill it.
        $defaultMotherPlateId = old('batch_mother_stock_item_id', (string)($dpr->mother_stock_item_id ?? ''));
    @endphp

    @if($isCutting)
        <div class="alert alert-info">
            <div class="fw-semibold">Cutting traceability</div>
            <div class="small">
                Recommended: use <b>Batch Mode</b> when you cut multiple different parts from a single plate.
                You can also add multiple remnant pieces from the same plate.
            </div>
        </div>

        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab==='batch' ? 'active' : '' }}" id="tab-batch" data-bs-toggle="tab" data-bs-target="#pane-batch" type="button" role="tab">
                    Batch Mode (1 plate -> many parts)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab==='single' ? 'active' : '' }}" id="tab-single" data-bs-toggle="tab" data-bs-target="#pane-single" type="button" role="tab">
                    Single Mode (1 plate per row)
                </button>
            </li>
        </ul>

        <div class="tab-content border border-top-0 p-3 bg-white">
            {{-- ==========================
                 Batch Mode
                 ========================== --}}
            <div class="tab-pane fade {{ $activeTab==='batch' ? 'show active' : '' }}" id="pane-batch" role="tabpanel" aria-labelledby="tab-batch">
                <form method="POST" action="{{ route('projects.production-dprs.traceability.update', [$project, $dpr]) }}">
                    @csrf
                    <input type="hidden" name="mode" value="batch">

                    @if(!empty($cuttingPlanPlates))
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Load from Cutting Plan Plate (optional)</label>
                                    <select name="batch_cutting_plan_plate_id" id="cutPlanPlateSelect" class="form-select">
                                        <option value="">-- not using cutting plan --</option>
                                        @foreach($cuttingPlanPlates as $cp)
                                            @php
                                                $optId = (string)($cp['id'] ?? '');
                                                $optLabel = $cp['plate_label'] ?? ('Plate#' . $optId);
                                                $optPlan = $cp['plan_name'] ?? ('Plan#' . ($cp['plan_id'] ?? ''));
                                                $optThk = $cp['thickness_mm'] ?? '-';
                                                $optW = $cp['width_mm'] ?? '-';
                                                $optL = $cp['length_mm'] ?? '-';
                                                $optPno = $cp['plate_number'] ?? '-';
                                            @endphp
                                            <option value="{{ $optId }}" {{ (string)old('batch_cutting_plan_plate_id')===$optId ? 'selected' : '' }}>
                                                [{{ $optPlan }}] {{ $optLabel }} | Thk: {{ $optThk }} | {{ $optW }}x{{ $optL }} | Plate No: {{ $optPno }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="text-muted small">
                                        Select a design cutting plan plate to auto-fill "Pieces from this plate" per item. You can still edit the quantities if actual cutting differs.
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary w-100" id="btnLoadCutPlan">Load Qty</button>
                                    <button type="button" class="btn btn-outline-secondary w-100" id="btnClearBatchQty">Clear Qty</button>
                                </div>
                            </div>
                            <div id="cutPlanInfo" class="small text-muted mt-2"></div>
                            <div id="cutPlanWarn" class="alert alert-warning d-none mt-2 mb-0"></div>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Mother Plate / Stock Item</label>
                            <select name="batch_mother_stock_item_id" class="form-select" required>
                                <option value="">-- select --</option>
                                @foreach($stockItems as $s)
                                    <option
                                        value="{{ $s->id }}"
                                        data-plate-number="{{ $s->plate_number ?? '' }}"
                                        data-heat-number="{{ $s->heat_number ?? '' }}"
                                        data-mtc-number="{{ $s->mtc_number ?? '' }}"
                                        data-thickness-mm="{{ $s->thickness_mm ?? '' }}"
                                        data-grade="{{ $s->grade ?? '' }}"
                                        {{ (string)$defaultMotherPlateId===(string)$s->id ? 'selected' : '' }}
                                    >
                                        #{{ $s->id }} | {{ $s->item?->name }}
                                        | Plate: {{ $s->plate_number ?? '-' }}
                                        | Heat: {{ $s->heat_number ?? '-' }}
                                        | MTC: {{ $s->mtc_number ?? '-' }}
                                        | Avl Wt: {{ $s->weight_kg_available ?? '-' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="text-muted small">Only stock items with status=available are listed. This selected stock will be consumed once for this batch.</div>
                        </div>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th style="width:120px;">DPR Qty (Nos)</th>
                                    <th style="width:120px;">Tagged</th>
                                    <th style="width:120px;">Remaining</th>
                                    <th style="width:170px;">Pieces from this plate</th>
                                    <th style="width:160px;">Piece Wt (kg)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($traceLines as $i => $ln)
                                    @php
                                        $reqQty = (float)($ln->qty ?? 0);
                                        $reqPieces = (int) round($reqQty);
                                        $already = (int)($capturedPieces[$ln->id] ?? 0);
                                        $remaining = max(0, $reqPieces - $already);
                                        $disabled = ($remaining <= 0);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $ln->planItem?->item_code }}</div>
                                            <div class="text-muted small">{{ $ln->planItem?->description }}</div>

                                            <input type="hidden" name="batch_lines[{{ $i }}][dpr_line_id]" value="{{ $ln->id }}">
                                        </td>
                                        <td>{{ $reqPieces }}</td>
                                        <td>{{ $already }}</td>
                                        <td>{{ $remaining }}</td>
                                        <td>
                                            <input
                                                type="number"
                                                name="batch_lines[{{ $i }}][piece_count]"
                                                class="form-control form-control-sm"
                                                value="{{ old('batch_lines.'.$i.'.piece_count', 0) }}"
                                                min="0"
                                                max="{{ $remaining }}"
                                                data-bom-item-id="{{ $ln->planItem?->bom_item_id ?? '' }}"
                                                data-item-code="{{ $ln->planItem?->item_code ?? '' }}"
                                                {{ $disabled ? 'disabled' : '' }}
                                            >
                                            <div class="text-muted small">Enter only the qty cut from this selected plate.</div>
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                step="0.001"
                                                name="batch_lines[{{ $i }}][piece_weight_kg]"
                                                class="form-control form-control-sm"
                                                value="{{ old('batch_lines.'.$i.'.piece_weight_kg') }}"
                                                placeholder="optional"
                                                {{ $disabled ? 'disabled' : '' }}
                                            >
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            No completed items found in this DPR.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <h5 class="mb-2"><i class="bi bi-recycle"></i> Remnants from this plate (optional)</h5>
                        <div class="text-muted small mb-2">
                            Add one row for each remnant piece. If a row is fully blank, it will be ignored.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle" id="remnantsTable">
                                <thead>
                                    <tr>
                                        <th style="width:140px;">Width (mm)</th>
                                        <th style="width:140px;">Length (mm)</th>
                                        <th style="width:160px;">Weight (kg)</th>
                                        <th style="width:120px;">Usable?</th>
                                        <th>Remarks</th>
                                        <th style="width:80px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @for($r = 0; $r < $initialRemnantRows; $r++)
                                        @php
                                            $oldR = is_array($oldBatchRemnants) ? ($oldBatchRemnants[$r] ?? []) : [];
                                        @endphp
                                        <tr>
                                            <td>
                                                <input type="number" name="batch_remnants[{{ $r }}][width_mm]" class="form-control form-control-sm" value="{{ $oldR['width_mm'] ?? '' }}" placeholder="optional">
                                            </td>
                                            <td>
                                                <input type="number" name="batch_remnants[{{ $r }}][length_mm]" class="form-control form-control-sm" value="{{ $oldR['length_mm'] ?? '' }}" placeholder="optional">
                                            </td>
                                            <td>
                                                <input type="number" step="0.001" name="batch_remnants[{{ $r }}][weight_kg]" class="form-control form-control-sm" value="{{ $oldR['weight_kg'] ?? '' }}" placeholder="optional">
                                            </td>
                                            <td>
                                                @php $usableVal = (string)($oldR['is_usable'] ?? '1'); @endphp
                                                <select name="batch_remnants[{{ $r }}][is_usable]" class="form-select form-select-sm">
                                                    <option value="1" {{ $usableVal==='1' ? 'selected' : '' }}>Yes</option>
                                                    <option value="0" {{ $usableVal==='0' ? 'selected' : '' }}>No (Scrap)</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="batch_remnants[{{ $r }}][remarks]" class="form-control form-control-sm" value="{{ $oldR['remarks'] ?? '' }}" placeholder="optional">
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-rem-remove">Remove</button>
                                            </td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-outline-secondary btn-sm" id="addRemnantBtn">
                            <i class="bi bi-plus-circle"></i> Add Remnant
                        </button>
                    </div>

                    <button class="btn btn-primary mt-3">
                        <i class="bi bi-save"></i> Save Cutting Batch
                    </button>
                </form>
            </div>

            {{-- ==========================
                 Single Mode (Legacy)
                 ========================== --}}
            <div class="tab-pane fade {{ $activeTab==='single' ? 'show active' : '' }}" id="pane-single" role="tabpanel" aria-labelledby="tab-single">
                <form method="POST" action="{{ route('projects.production-dprs.traceability.update', [$project, $dpr]) }}">
                    @csrf
                    <input type="hidden" name="mode" value="single">

                    <div class="alert alert-secondary mb-3">
                        Single mode: one mother stock per row. Use this when each item is cut from a separate plate/stock.
                        If you need one plate -> many parts, use Batch Mode.
                    </div>

                    <div class="card">
                        <div class="card-body table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th style="width:120px;">DPR Qty</th>
                                        <th style="width:120px;">Tagged</th>
                                        <th style="width:120px;">Remaining</th>
                                        <th style="width:340px;">Mother Stock</th>
                                        <th style="width:140px;">Pieces</th>
                                        <th style="width:140px;">Piece Wt (kg)</th>
                                        <th style="width:140px;">Rem W</th>
                                        <th style="width:140px;">Rem L</th>
                                        <th style="width:160px;">Rem Wt (kg)</th>
                                        <th style="width:120px;">Usable?</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($traceLines as $i => $ln)
                                        @php
                                            $reqQty = (float)($ln->qty ?? 0);
                                            $reqPieces = (int) round($reqQty);
                                            $already = (int)($capturedPieces[$ln->id] ?? 0);
                                            $remaining = max(0, $reqPieces - $already);
                                            $disabled = ($remaining <= 0);
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $ln->planItem?->item_code }}</div>
                                                <div class="text-muted small">{{ $ln->planItem?->description }}</div>
                                                <input type="hidden" name="rows[{{ $i }}][dpr_line_id]" value="{{ $ln->id }}">
                                            </td>
                                            <td>{{ $reqPieces }}</td>
                                            <td>{{ $already }}</td>
                                            <td>{{ $remaining }}</td>
                                            <td>
                                                <select name="rows[{{ $i }}][mother_stock_item_id]" class="form-select form-select-sm" {{ $disabled ? 'disabled' : '' }}>
                                                    <option value="">-- select --</option>
                                                    @foreach($stockItems as $s)
                                                        @php
                                                            $rowDefaultMother = old('rows.'.$i.'.mother_stock_item_id', (string)($dpr->mother_stock_item_id ?? ''));
                                                        @endphp
                                                        <option value="{{ $s->id }}" {{ (string)$rowDefaultMother===(string)$s->id ? 'selected' : '' }}>
                                                            #{{ $s->id }} | {{ $s->item?->name }}
                                                            | Plate: {{ $s->plate_number ?? '-' }}
                                                            | Heat: {{ $s->heat_number ?? '-' }}
                                                            | MTC: {{ $s->mtc_number ?? '-' }}
                                                            | Avl Wt: {{ $s->weight_kg_available ?? '-' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <div class="text-muted small">Only status=available shown.</div>
                                            </td>
                                            <td>
                                                <input
                                                    type="number"
                                                    name="rows[{{ $i }}][piece_count]"
                                                    class="form-control form-control-sm"
                                                    value="{{ old('rows.'.$i.'.piece_count', $remaining > 0 ? $remaining : 0) }}"
                                                    min="0"
                                                    max="{{ $remaining }}"
                                                    {{ $disabled ? 'disabled' : '' }}
                                                >
                                            </td>
                                            <td>
                                                <input type="number" step="0.001" name="rows[{{ $i }}][piece_weight_kg]" class="form-control form-control-sm" value="{{ old('rows.'.$i.'.piece_weight_kg') }}" placeholder="optional" {{ $disabled ? 'disabled' : '' }}>
                                            </td>
                                            <td>
                                                <input type="number" name="rows[{{ $i }}][remnant_width_mm]" class="form-control form-control-sm" value="{{ old('rows.'.$i.'.remnant_width_mm') }}" placeholder="optional" {{ $disabled ? 'disabled' : '' }}>
                                            </td>
                                            <td>
                                                <input type="number" name="rows[{{ $i }}][remnant_length_mm]" class="form-control form-control-sm" value="{{ old('rows.'.$i.'.remnant_length_mm') }}" placeholder="optional" {{ $disabled ? 'disabled' : '' }}>
                                            </td>
                                            <td>
                                                <input type="number" step="0.001" name="rows[{{ $i }}][remnant_weight_kg]" class="form-control form-control-sm" value="{{ old('rows.'.$i.'.remnant_weight_kg') }}" placeholder="optional" {{ $disabled ? 'disabled' : '' }}>
                                            </td>
                                            <td>
                                                @php $usableOld = old('rows.'.$i.'.remnant_is_usable', '1'); @endphp
                                                <select name="rows[{{ $i }}][remnant_is_usable]" class="form-select form-select-sm" {{ $disabled ? 'disabled' : '' }}>
                                                    <option value="1" {{ (string)$usableOld==='1' ? 'selected' : '' }}>Yes</option>
                                                    <option value="0" {{ (string)$usableOld==='0' ? 'selected' : '' }}>No (Scrap)</option>
                                                </select>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center text-muted py-4">No completed items found in this DPR.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>

                            <button class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Cutting Traceability
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
            (function () {
                // ------------------------------------------------------------
                // Cutting Plan auto-fill (Batch Mode)
                // ------------------------------------------------------------
                var plateMap = @json($cuttingPlanPlateMap);
                var cpSelect = document.getElementById('cutPlanPlateSelect');
                var btnLoad = document.getElementById('btnLoadCutPlan');
                var btnClear = document.getElementById('btnClearBatchQty');
                var motherSelect = document.querySelector('select[name="batch_mother_stock_item_id"]');
                var infoEl = document.getElementById('cutPlanInfo');
                var warnEl = document.getElementById('cutPlanWarn');

                function getPieceInputs() {
                    return Array.prototype.slice.call(document.querySelectorAll('input[name^="batch_lines"][name$="[piece_count]"]'));
                }

                function showWarn(msg) {
                    if (!warnEl) return;
                    if (!msg) {
                        warnEl.classList.add('d-none');
                        warnEl.textContent = '';
                        return;
                    }
                    warnEl.textContent = msg;
                    warnEl.classList.remove('d-none');
                }

                function applyCutPlan(plateId) {
                    if (!plateId) {
                        if (infoEl) infoEl.textContent = '';
                        showWarn('');
                        return;
                    }

                    var plate = plateMap[String(plateId)];
                    if (!plate) {
                        showWarn('Selected cutting plan plate not found. Refresh and try again.');
                        return;
                    }

                    var allocations = plate.allocations || {};
                    var inputs = getPieceInputs();

                    var present = {};
                    inputs.forEach(function (inp) {
                        var bomId = inp.getAttribute('data-bom-item-id') || '';
                        if (bomId) present[String(bomId)] = true;
                    });

                    inputs.forEach(function (inp) {
                        if (inp.disabled) return;
                        var bomId = inp.getAttribute('data-bom-item-id') || '';
                        if (!bomId) return;

                        var qty = allocations[String(bomId)];
                        if (qty === undefined || qty === null) qty = 0;

                        var max = parseInt(inp.getAttribute('max') || '0', 10);
                        if (isNaN(max) || max < 0) max = 0;

                        var val = parseInt(qty, 10);
                        if (isNaN(val) || val < 0) val = 0;
                        if (val > max) val = max;

                        inp.value = String(val);
                    });

                    // Auto-select mother plate if empty and plate_number exists
                    if (motherSelect && !motherSelect.value && plate.plate_number) {
                        var opts = motherSelect.options;
                        for (var i = 0; i < opts.length; i++) {
                            var opt = opts[i];
                            if ((opt.getAttribute('data-plate-number') || '') === String(plate.plate_number)) {
                                motherSelect.value = opt.value;
                                break;
                            }
                        }
                    }

                    if (infoEl) {
                        var label = plate.plate_label || ('Plate#' + plate.id);
                        var pno = plate.plate_number ? (' | Plate No: ' + plate.plate_number) : '';
                        infoEl.textContent = 'Loaded qty from: [' + (plate.plan_name || 'Cutting Plan') + '] ' + label + pno + '.';
                    }

                    var missing = 0;
                    for (var k in allocations) {
                        if (!present[String(k)]) missing++;
                    }

                    if (missing > 0) {
                        showWarn('Note: This cutting plan plate has allocations for ' + missing + ' item(s) that are not present in this DPR. Those allocations were ignored.');
                    } else {
                        showWarn('');
                    }
                }

                function clearBatchQty() {
                    var inputs = getPieceInputs();
                    inputs.forEach(function (inp) {
                        if (inp.disabled) return;
                        inp.value = '0';
                    });
                    showWarn('');
                    if (infoEl) infoEl.textContent = '';
                }

                if (cpSelect) {
                    cpSelect.addEventListener('change', function () {
                        applyCutPlan(cpSelect.value);
                    });
                }

                if (btnLoad && cpSelect) {
                    btnLoad.addEventListener('click', function () {
                        applyCutPlan(cpSelect.value);
                    });
                }

                if (btnClear) {
                    btnClear.addEventListener('click', function () {
                        clearBatchQty();
                    });
                }

                // ------------------------------------------------------------
                // Remnants table helper
                // ------------------------------------------------------------
                var table = document.getElementById('remnantsTable');
                var addBtn = document.getElementById('addRemnantBtn');

                if (!table || !addBtn) return;

                var nextIndex = {{ (int)$initialRemnantRows }};

                function rowHtml(idx) {
                    return ''
                        + '<tr>'
                        +   '<td><input type="number" name="batch_remnants[' + idx + '][width_mm]" class="form-control form-control-sm" placeholder="optional"></td>'
                        +   '<td><input type="number" name="batch_remnants[' + idx + '][length_mm]" class="form-control form-control-sm" placeholder="optional"></td>'
                        +   '<td><input type="number" step="0.001" name="batch_remnants[' + idx + '][weight_kg]" class="form-control form-control-sm" placeholder="optional"></td>'
                        +   '<td>'
                        +     '<select name="batch_remnants[' + idx + '][is_usable]" class="form-select form-select-sm">'
                        +       '<option value="1" selected>Yes</option>'
                        +       '<option value="0">No (Scrap)</option>'
                        +     '</select>'
                        +   '</td>'
                        +   '<td><input type="text" name="batch_remnants[' + idx + '][remarks]" class="form-control form-control-sm" placeholder="optional"></td>'
                        +   '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-rem-remove">Remove</button></td>'
                        + '</tr>';
                }

                addBtn.addEventListener('click', function () {
                    var tbody = table.querySelector('tbody');
                    if (!tbody) return;
                    var tr = document.createElement('tr');
                    tr.innerHTML = rowHtml(nextIndex);
                    tbody.appendChild(tr);
                    nextIndex++;
                });

                document.addEventListener('click', function (e) {
                    if (!e.target) return;
                    if (e.target.classList && e.target.classList.contains('btn-rem-remove')) {
                        e.preventDefault();
                        var tr = e.target.closest('tr');
                        if (tr) tr.remove();
                    }
                });
            })();
        </script>

    @elseif($isFitup)
        <div class="alert alert-info">
            Fitup traceability: create assembly and select which pieces are consumed.
        </div>

        <form method="POST" action="{{ route('projects.production-dprs.traceability.update', [$project, $dpr]) }}">
            @csrf

            <div class="card">
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Assembly Item</th>
                                <th style="width:220px;">Assembly Weight (kg)</th>
                                <th>Consume Pieces</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($traceLines as $i => $ln)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $ln->planItem?->item_code }}</div>
                                        <div class="text-muted small">Mark: {{ $ln->planItem?->assembly_mark ?? '-' }}</div>

                                        <input type="hidden" name="assemblies[{{ $i }}][dpr_line_id]" value="{{ $ln->id }}">
                                    </td>
                                    <td>
                                        <input type="number" step="0.001" name="assemblies[{{ $i }}][assembly_weight_kg]" class="form-control form-control-sm" placeholder="optional">
                                    </td>
                                    <td>
                                        <select class="form-select" name="assemblies[{{ $i }}][piece_ids][]" multiple size="8">
                                            @foreach($pieces as $p)
                                                <option value="{{ $p->id }}">
                                                    {{ $p->piece_number }} | Plate: {{ $p->plate_number ?? '-' }} | Heat: {{ $p->heat_number ?? '-' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="text-muted small">Only project available pieces shown.</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <button class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Fitup Traceability
                    </button>
                </div>
            </div>
        </form>
    @else
        <div class="alert alert-warning">
            This activity does not require traceability capture in Phase D.
        </div>
    @endif
</div>
@endsection
