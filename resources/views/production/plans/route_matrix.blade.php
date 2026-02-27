@extends('layouts.erp')

@section('title', 'Route Matrix')

@section('content')
@php
    $projectId = (int) ($projectId ?? (request()->route('project')?->id ?? 0));
    $planId = (int) ($plan->id ?? 0);

    // $cellMap is built by the controller as:
    //   [item_id][activity_id] => ['id' => production_plan_item_activities.id, 'is_enabled' => bool]
    // If not provided for any reason, default to empty so the UI fails safely.
    $cellMap = is_array($cellMap ?? null) ? $cellMap : [];

    $activityCount = is_countable($activities ?? null) ? count($activities) : 0;
    $itemCount = is_countable($items ?? null) ? count($items) : 0;

    // Lookup maps for tooltips / quick display (id => label)
    $contractorMap = [];
    foreach (($contractors ?? []) as $c) {
        $cid = (int) ($c->id ?? 0);
        if ($cid <= 0) continue;
        $contractorMap[$cid] = trim((string)($c->code ?? '') . ' ' . (string)($c->name ?? ''));
    }

    $workerMap = [];
    foreach (($workers ?? []) as $w) {
        $wid = (int) ($w->id ?? 0);
        if ($wid <= 0) continue;
        $workerMap[$wid] = trim((string)($w->name ?? ''));
    }

    $uomMap = [];
    foreach (($uoms ?? []) as $u) {
        $uid = (int) ($u->id ?? 0);
        if ($uid <= 0) continue;
        $uomMap[$uid] = strtoupper((string)($u->code ?? ''));
    }

    $hasMachineId = !empty($hasMachineId);
    $machineMap = [];
    if ($hasMachineId) {
        foreach (($machines ?? []) as $m) {
            $mid = (int) ($m->id ?? 0);
            if ($mid <= 0) continue;
            $label = trim((string)($m->code ?? '') . ' ' . (string)(($m->short_name ?? '') ?: ($m->name ?? '')));
            $machineMap[$mid] = $label !== '' ? $label : ('Machine#'.$mid);
        }
    }
@endphp

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-grid-3x3-gap"></i> Route Matrix</h2>
            <div class="text-muted small">
                Plan: <strong>{{ $plan->plan_number }}</strong>
                <span class="mx-1">|</span>
                Status: <strong>{{ strtoupper($plan->status) }}</strong>
                <span class="mx-1">|</span>
                Items: <strong>{{ $itemCount }}</strong>
                <span class="mx-1">|</span>
                Activities: <strong>{{ $activityCount }}</strong>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ url('/projects/'.$projectId.'/production-plans/'.$planId) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Plan
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">There were some problems with your input.</div>
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info">
        <div class="fw-semibold">How to use</div>
        <ul class="mb-0">
            <li>Tick/untick activities for all items using the <strong>column</strong> toggle.</li>
            <li>Tick/untick all activities for one item using the <strong>row</strong> toggle.</li>
            <li>To assign <strong>Contractor / Worker / Machine / Rate</strong>, select the required cells first and then use <strong>Bulk Assign</strong> below.</li>
            <li>You can still fine-tune any single item using the per-item <strong>Route</strong> page.</li>
        </ul>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label form-label-sm">Search items</label>
                    <input type="text" class="form-control" id="itemSearch" placeholder="Search mark/code/description...">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Filter type</label>
                    <select class="form-select" id="typeFilter">
                        <option value="all">All</option>
                        <option value="part">Parts</option>
                        <option value="assembly">Assemblies</option>
                    </select>
                </div>
                <div class="col-md-3 text-md-end">
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                        <button type="button" class="btn btn-outline-secondary" id="btnSelectAll">
                            Select All
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnClearAll">
                            Clear All
                        </button>
                    </div>
                    <div class="mt-2 small text-muted">
                        Selected: <span id="selectedCount">0</span> / <span id="totalCount">0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <div class="fw-semibold"><i class="bi bi-magic"></i> Bulk Assign</div>
                    <div class="small text-muted">
                        Select the required route cells in the matrix (tick boxes / row / column toggles), then apply the fields below to all selected cells.
                    </div>
                </div>
                <div class="small text-muted">
                    Selected cells: <strong><span id="selectedCountAssign">0</span></strong> / <span id="totalCountAssign">0</span>
                </div>
            </div>

            <form method="POST" action="{{ url('/projects/'.$projectId.'/production-plans/'.$planId.'/route-matrix/assign') }}" id="assignForm" class="mt-3">
                @csrf
                <input type="hidden" name="selected_ids_json" id="selected_ids_json" value="">

                <div class="row g-2 align-items-end">
                    <div class="col-lg-3 col-md-4">
                        <label class="form-label form-label-sm">Contractor (Subcontractor)</label>
                        <select class="form-select form-select-sm" name="contractor_party_id">
                            <option value="__KEEP__" selected>— Keep as is —</option>
                            <option value="">— None —</option>
                            @foreach(($contractors ?? []) as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-4">
                        <label class="form-label form-label-sm">Company Worker</label>
                        <select class="form-select form-select-sm" name="worker_user_id">
                            <option value="__KEEP__" selected>— Keep as is —</option>
                            <option value="">— None —</option>
                            @foreach(($workers ?? []) as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if(!empty($hasMachineId))
                        <div class="col-lg-3 col-md-4">
                            <label class="form-label form-label-sm">Machine</label>
                            <select class="form-select form-select-sm" name="machine_id">
                                <option value="__KEEP__" selected>— Keep as is —</option>
                                <option value="">— None —</option>
                                @foreach(($machines ?? []) as $m)
                                    @php
                                        $mLabel = trim((string)($m->code ?? '') . ' ' . (string)(($m->short_name ?? '') ?: ($m->name ?? '')));
                                        if ($mLabel === '') { $mLabel = 'Machine#'.$m->id; }
                                    @endphp
                                    <option value="{{ $m->id }}">{{ $mLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-lg-2 col-md-3">
                        <label class="form-label form-label-sm">Rate</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="rate" placeholder="Keep as is">
                    </div>

                    <div class="col-lg-2 col-md-3">
                        <label class="form-label form-label-sm">Rate UOM</label>
                        <select class="form-select form-select-sm" name="rate_uom_id">
                            <option value="__KEEP__" selected>— Keep as is —</option>
                            <option value="">— None —</option>
                            @foreach(($uoms ?? []) as $u)
                                <option value="{{ $u->id }}">{{ $u->code }} — {{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-3">
                        <label class="form-label form-label-sm">Planned Date</label>
                        <input type="date" class="form-control form-control-sm" name="planned_date">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="clear_planned_date" id="clear_planned_date" value="1">
                            <label class="form-check-label small" for="clear_planned_date">Clear date</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-check2-circle"></i> Apply to Selected
                            </button>
                        </div>
                        <div class="mt-2 small text-muted">
                            Note: Bulk Assign updates the selected (checked) route cells and also enables them. Use <strong>Save Matrix</strong> below for bulk enable/disable changes.
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ url('/projects/'.$projectId.'/production-plans/'.$planId.'/route-matrix') }}" id="matrixForm">
                @csrf
                <input type="hidden" name="payload_json" id="payload_json" value="">

                <div class="table-responsive" style="max-height: 70vh;">
                    <table class="table table-sm table-hover align-middle mb-0" id="matrixTable">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 2;">
                            <tr>
                                <th style="min-width: 240px;">Item</th>
                                <th style="width: 90px;" class="text-center">Type</th>
                                <th style="width: 70px;" class="text-center" title="Toggle full row">Row</th>
                                @foreach($activities as $act)
                                    @php
                                        $actId = (int) ($act->id ?? 0);
                                        $actCode = (string) ($act->code ?? '');
                                        $actName = (string) ($act->name ?? '');
                                    @endphp
                                    <th class="text-center" style="min-width: 140px;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="fw-semibold" style="line-height: 1.1;">{{ $actCode }}</div>
                                            <div class="small text-muted" style="line-height: 1.1;">{{ $actName }}</div>
                                            <input type="checkbox"
                                                   class="form-check-input col-toggle mt-1"
                                                   data-activity-id="{{ $actId }}"
                                                   title="Toggle '{{ $actCode }}' for all items">
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $it)
                                @php
                                    $itemId = (int) ($it->id ?? 0);
                                    $type = (string) ($it->item_type ?? '');

                                    $label = '';
                                    if ($type === 'assembly') {
                                        $label = (string) ($it->assembly_mark ?? ('#'.$itemId));
                                    } else {
                                        $label = (string) ($it->item_code ?? ('#'.$itemId));
                                    }

                                    $desc = (string) ($it->description ?? '');
                                    $asm = (string) ($it->assembly_mark ?? '');

                                    $search = strtolower(trim($label.' '.$asm.' '.$desc.' '.$type));
                                @endphp

                                <tr class="matrix-row" data-item-type="{{ $type }}" data-search="{{ $search }}">
                                    <td>
                                        <div class="fw-semibold">{{ $label }}</div>
                                        @if($type !== 'assembly' && !empty($asm))
                                            <div class="small text-muted">Asm: {{ $asm }}</div>
                                        @endif
                                        @if(!empty($desc))
                                            <div class="small text-muted">{{ $desc }}</div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($type === 'assembly')
                                            <span class="badge bg-info">Asm</span>
                                        @else
                                            <span class="badge bg-secondary">Part</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox"
                                               class="form-check-input row-toggle"
                                               data-item-id="{{ $itemId }}"
                                               title="Toggle all activities for this item">
                                    </td>

                                    @foreach($activities as $act)
                                        @php
                                            $actId = (int) ($act->id ?? 0);
                                            $cell = $cellMap[$itemId][$actId] ?? null;
                                        @endphp

                                        <td class="text-center">
                                            @if($cell)
                                                @php
                                                    $tips = [];
                                                    $cId = (int) ($cell['contractor_party_id'] ?? 0);
                                                    if ($cId > 0) {
                                                        $tips[] = 'Contractor: ' . ($contractorMap[$cId] ?? ('#'.$cId));
                                                    }
                                                    $wId = (int) ($cell['worker_user_id'] ?? 0);
                                                    if ($wId > 0) {
                                                        $tips[] = 'Worker: ' . ($workerMap[$wId] ?? ('#'.$wId));
                                                    }
                                                    if (!empty($hasMachineId)) {
                                                        $mId = (int) ($cell['machine_id'] ?? 0);
                                                        if ($mId > 0) {
                                                            $tips[] = 'Machine: ' . ($machineMap[$mId] ?? ('#'.$mId));
                                                        }
                                                    }

                                                    $rateVal = $cell['rate'] ?? null;
                                                    $rateVal = ($rateVal === null || $rateVal === '') ? null : (float) $rateVal;
                                                    if ($rateVal !== null && $rateVal > 0) {
                                                        $ruomId = (int) ($cell['rate_uom_id'] ?? 0);
                                                        $ruom = $ruomId > 0 ? ($uomMap[$ruomId] ?? '') : '';
                                                        $rateDisp = rtrim(rtrim(number_format($rateVal, 2, '.', ''), '0'), '.');
                                                        $tips[] = 'Rate: ' . $rateDisp . ($ruom ? ('/'.$ruom) : '');
                                                    }

                                                    $pd = (string) ($cell['planned_date'] ?? '');
                                                    if ($pd !== '') {
                                                        $tips[] = 'Planned: ' . $pd;
                                                    }
                                                @endphp

                                                <div class="d-flex flex-column align-items-center">
                                                    <input type="checkbox"
                                                           class="form-check-input cell-toggle"
                                                           data-cell-id="{{ $cell['id'] }}"
                                                           data-item-id="{{ $itemId }}"
                                                           data-activity-id="{{ $actId }}"
                                                           {{ $cell['is_enabled'] ? 'checked' : '' }}>

                                                    @if(!empty($tips))
                                                        <span class="text-muted small mt-1" title="{{ implode(' | ', $tips) }}">
                                                            <i class="bi bi-info-circle"></i>
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + $activityCount }}" class="text-center text-muted py-4">
                                        No plan items found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 d-flex justify-content-end gap-2">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-check2-circle"></i> Save Matrix
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ url('/projects/'.$projectId.'/production-plans/'.$planId) }}">Cancel</a>
                </div>

                <div class="mt-2 small text-muted">
                    Tip: Use <strong>Save Matrix</strong> to update Enabled flags. Use <strong>Bulk Assign</strong> (above) to set Contractor/Worker/Machine/Rate/Planned Date for selected cells. Use per-item <strong>Route</strong> page for fine-tuning.
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    const qs  = (sel, ctx=document) => ctx.querySelector(sel);
    const qsa = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));

    const form = qs('#matrixForm');
    const payloadEl = qs('#payload_json');

    const itemSearch = qs('#itemSearch');
    const typeFilter = qs('#typeFilter');

    const btnSelectAll = qs('#btnSelectAll');
    const btnClearAll  = qs('#btnClearAll');

    const selectedCountEl = qs('#selectedCount');
    const totalCountEl = qs('#totalCount');

    const selectedCountAssignEl = qs('#selectedCountAssign');
    const totalCountAssignEl = qs('#totalCountAssign');

    const assignForm = qs('#assignForm');
    const selectedIdsEl = qs('#selected_ids_json');

    const cellCheckboxes = () => qsa('.cell-toggle');
    const rowToggles = () => qsa('.row-toggle');
    const colToggles = () => qsa('.col-toggle');

    function rowCells(itemId){
        return cellCheckboxes().filter(cb => cb.dataset.itemId === String(itemId));
    }

    function colCells(activityId){
        return cellCheckboxes().filter(cb => cb.dataset.activityId === String(activityId));
    }

    function setChecks(cbs, checked){
        cbs.forEach(cb => {
            if (cb.disabled) return;
            cb.checked = checked;
        });
    }

    function refreshRowStates(){
        rowToggles().forEach(rt => {
            const itemId = rt.dataset.itemId;
            const cbs = rowCells(itemId);
            if (!cbs.length){
                rt.disabled = true;
                rt.indeterminate = false;
                rt.checked = false;
                return;
            }
            rt.disabled = false;
            const checkedCount = cbs.filter(x => x.checked).length;
            rt.indeterminate = checkedCount > 0 && checkedCount < cbs.length;
            rt.checked = checkedCount === cbs.length;
        });
    }

    function refreshColStates(){
        colToggles().forEach(ct => {
            const actId = ct.dataset.activityId;
            const cbs = colCells(actId);
            if (!cbs.length){
                ct.disabled = true;
                ct.indeterminate = false;
                ct.checked = false;
                return;
            }
            ct.disabled = false;
            const checkedCount = cbs.filter(x => x.checked).length;
            ct.indeterminate = checkedCount > 0 && checkedCount < cbs.length;
            ct.checked = checkedCount === cbs.length;
        });
    }

    function refreshCounts(){
        const all = cellCheckboxes();
        const selected = all.filter(x => x.checked).length;
        if (selectedCountEl) selectedCountEl.textContent = String(selected);
        if (totalCountEl) totalCountEl.textContent = String(all.length);

        if (selectedCountAssignEl) selectedCountAssignEl.textContent = String(selected);
        if (totalCountAssignEl) totalCountAssignEl.textContent = String(all.length);
    }

    function refreshAll(){
        refreshRowStates();
        refreshColStates();
        refreshCounts();
    }

    // Changes
    document.addEventListener('change', function(e){
        if (e.target.classList.contains('row-toggle')){
            setChecks(rowCells(e.target.dataset.itemId), e.target.checked);
            refreshAll();
        }
        if (e.target.classList.contains('col-toggle')){
            setChecks(colCells(e.target.dataset.activityId), e.target.checked);
            refreshAll();
        }
        if (e.target.classList.contains('cell-toggle')){
            refreshAll();
        }
    });

    // Global buttons
    if (btnSelectAll){
        btnSelectAll.addEventListener('click', function(){
            setChecks(cellCheckboxes(), true);
            refreshAll();
        });
    }

    if (btnClearAll){
        btnClearAll.addEventListener('click', function(){
            setChecks(cellCheckboxes(), false);
            refreshAll();
        });
    }

    // Filters
    function applyFilters(){
        const q = (itemSearch?.value || '').trim().toLowerCase();
        const type = (typeFilter?.value || 'all');

        qsa('tr.matrix-row').forEach(row => {
            const hay = (row.dataset.search || '');
            const rowType = (row.dataset.itemType || '');

            let show = true;
            if (q && !hay.includes(q)) show = false;
            if (type !== 'all' && rowType !== type) show = false;

            row.style.display = show ? '' : 'none';
        });
    }

    if (itemSearch){
        itemSearch.addEventListener('input', applyFilters);
    }

    if (typeFilter){
        typeFilter.addEventListener('change', applyFilters);
    }

    // Bulk Assign submit handler: build selected_ids_json from checked cells
    if (assignForm){
        assignForm.addEventListener('submit', function(e){
            const ids = cellCheckboxes()
                .filter(cb => cb.checked)
                .map(cb => parseInt(cb.dataset.cellId, 10))
                .filter(v => Number.isFinite(v) && v > 0);

            if (!ids.length){
                e.preventDefault();
                alert('Please select at least one route cell (tick checkbox) before applying Bulk Assign.');
                return;
            }

            if (selectedIdsEl){
                selectedIdsEl.value = JSON.stringify(ids);
            }
        });
    }

    // Submit handler to build payload
    if (form){
        form.addEventListener('submit', function(){
            const payload = cellCheckboxes().map(cb => ({
                id: parseInt(cb.dataset.cellId, 10),
                is_enabled: cb.checked ? 1 : 0,
            }));
            payloadEl.value = JSON.stringify(payload);
        });
    }

    // Init
    refreshAll();
})();
</script>
@endpush

@endsection
