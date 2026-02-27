@extends('layouts.erp')

@section('title', 'New DPR')

@section('content')
@php
    $isScoped = !empty($projectId);
    $listUrl = $isScoped
        ? url('/projects/'.$projectId.'/production-dprs')
        : url('/production/production-dprs');
    $createUrl = $isScoped
        ? url('/projects/'.$projectId.'/production-dprs/create')
        : url('/production/production-dprs/create');
    $storeUrl = $isScoped
        ? url('/projects/'.$projectId.'/production-dprs')
        : url('/production/production-dprs');
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-plus-circle"></i> New DPR</h2>
            <div class="text-muted small">{{ $isScoped ? 'Project-specific DPR creation' : 'Global mode: select a project first' }}</div>
        </div>
        <a class="btn btn-outline-secondary" href="{{ $listUrl }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    @if(! $isScoped)
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ $createUrl }}" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Select Project</label>
                        <select name="project_id" class="form-select" onchange="this.form.submit()">
                            <option value="">— Select —</option>
                            @foreach(($projects ?? collect()) as $p)
                                <option value="{{ $p->id }}" {{ (string) request('project_id') === (string) $p->id ? 'selected' : '' }}>
                                    {{ $p->code }} — {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ $createUrl }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if(! $isScoped)
        <div class="alert alert-info">
            Select a project to load approved plans and cutting plans for DPR creation.
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ $storeUrl }}">
                @csrf
                @if(! $isScoped)
                    <input type="hidden" name="project_id" value="{{ request('project_id') }}">
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Production Plan (Approved)</label>
                        <select name="production_plan_id" class="form-select" required>
                            <option value="">— Select —</option>
                            @foreach($plans as $p)
                                <option value="{{ $p->id }}"
                                    data-bom-id="{{ (int)($p->bom_id ?? 0) }}"
                                    {{ (string)old('production_plan_id') === (string)$p->id ? 'selected' : '' }}
                                >
                                    {{ $p->plan_number }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Activity</label>
                        <select name="production_activity_id" class="form-select" required>
                            <option value="">— Select —</option>
                            @foreach($activities as $a)
                                <option value="{{ $a->id }}"
                                    data-code="{{ $a->code }}"
                                    {{ (string)old('production_activity_id') === (string)$a->id ? 'selected' : '' }}
                                >
                                    {{ $a->name }} ({{ $a->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-12" id="cuttingPlanWrap" style="display:none;">
                        <label class="form-label">
                            Cutting Plan <span class="text-danger">*</span>
                        </label>
                        <select name="cutting_plan_id" id="cutting_plan_id" class="form-select">
                            <option value="">— Select —</option>
                            @foreach(($cuttingPlans ?? []) as $cp)
                                @php
                                    $cpLabel = (string)($cp->name ?? ('#'.$cp->id));
                                    if (!empty($cp->grade)) { $cpLabel .= ' | '.$cp->grade; }
                                    if (!empty($cp->thickness_mm)) { $cpLabel .= ' | '.$cp->thickness_mm.'mm'; }
                                    if (!empty($cp->status)) { $cpLabel .= ' | '.strtoupper($cp->status); }
                                @endphp
                                <option value="{{ $cp->id }}"
                                    data-bom-id="{{ (int)($cp->bom_id ?? 0) }}"
                                    data-thickness-mm="{{ (int)($cp->thickness_mm ?? 0) }}"
                                    data-grade="{{ (string)($cp->grade ?? '') }}"
                                    data-plate-sizes='@json($cp->plate_sizes ?? [])'
                                    data-plate-sizes-label="{{ (string)($cp->plate_sizes_label ?? '') }}"
                                    {{ (string)old('cutting_plan_id') === (string)$cp->id ? 'selected' : '' }}
                                >
                                    {{ $cpLabel }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Required when <b>Cutting</b> activity is selected. Parts will be auto-selected and quantity will be set as per allocations.
                        </div>
                        <div id="cuttingPlanSizeInfo" class="small text-muted mt-1"></div>
                    </div>

                    <div class="col-md-12" id="motherPlateWrap" style="display:none;">
                        <label class="form-label">
                            Mother Plate (Store) <span class="text-danger">*</span>
                        </label>
                        <select name="mother_stock_item_id" id="mother_stock_item_id" class="form-select">
                            <option value="">— Select —</option>
                            @foreach(($stockPlates ?? []) as $s)
                                @php
                                    $label = '#'.$s->id;
                                    $itemName = $s->item_name ?? ('Item#'.$s->item_id);
                                    $thk = $s->thickness_mm ?? null;
                                    $wmm = $s->width_mm ?? null;
                                    $lmm = $s->length_mm ?? null;
                                    $grade = $s->grade ?? null;
                                    $pno = $s->plate_number ?? null;
                                    $hno = $s->heat_number ?? null;
                                    $mtc = $s->mtc_number ?? null;
                                    $wt  = $s->weight_kg_available ?? null;

                                    $label .= ' | '.$itemName;

                                    if (!empty($wmm) && !empty($lmm) && !empty($thk)) {
                                        $label .= ' | '.$wmm.'x'.$lmm.'x'.$thk.'mm';
                                    } elseif (!empty($thk)) {
                                        $label .= ' | '.$thk.'mm';
                                    }

                                    if (!empty($grade)) { $label .= ' | '.$grade; }

                                    $label .= ' | Plate: '.(!empty($pno) ? $pno : '-');
                                    $label .= ' | Heat: '.(!empty($hno) ? $hno : '-');
                                    if (!empty($mtc)) { $label .= ' | MTC: '.$mtc; }
                                    if (!empty($wt)) { $label .= ' | Avl Wt: '.$wt; }
                                @endphp
                                <option value="{{ $s->id }}"
                                    data-thickness-mm="{{ (int)($s->thickness_mm ?? 0) }}"
                                    data-width-mm="{{ (int)($s->width_mm ?? 0) }}"
                                    data-length-mm="{{ (int)($s->length_mm ?? 0) }}"
                                    data-grade="{{ (string)($s->grade ?? '') }}"
                                    data-plate-number="{{ (string)($s->plate_number ?? '') }}"
                                    data-heat-number="{{ (string)($s->heat_number ?? '') }}"
                                    data-mtc-number="{{ (string)($s->mtc_number ?? '') }}"
                                    {{ (string)old('mother_stock_item_id') === (string)$s->id ? 'selected' : '' }}
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Required for <b>Cutting</b> DPR to link <b>Plate No / Heat No / MTC</b> from Store.
                        </div>
                        <div id="motherPlateInfo" class="small text-muted mt-1"></div>
                        <div id="motherPlateWarn" class="alert alert-warning d-none mt-2 mb-0"></div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">DPR Date</label>
                        <input type="date" name="dpr_date" class="form-control" value="{{ old('dpr_date', date('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Shift</label>
                        <input type="text" name="shift" class="form-control" placeholder="Day/Night/A/B..." value="{{ old('shift') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Machine ID (optional)</label>
                        <input type="number" name="machine_id" class="form-control" placeholder="Machine id" value="{{ old('machine_id') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Contractor (optional)</label>
                        <select name="contractor_party_id" class="form-select">
                            <option value="">— None —</option>
                            @foreach($contractors as $c)
                                <option value="{{ $c->id }}" {{ (string)old('contractor_party_id') === (string)$c->id ? 'selected' : '' }}>
                                    {{ $c->name }} ({{ $c->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Worker (optional)</label>
                        <select name="worker_user_id" class="form-select">
                            <option value="">— None —</option>
                            @foreach($workers as $w)
                                <option value="{{ $w->id }}" {{ (string)old('worker_user_id') === (string)$w->id ? 'selected' : '' }}>
                                    {{ $w->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                <div class="mt-3">
                    <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Create DPR</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const planSel = document.querySelector('select[name="production_plan_id"]');
    const actSel = document.querySelector('select[name="production_activity_id"]');
    const cpWrap = document.getElementById('cuttingPlanWrap');
    const cpSel = document.getElementById('cutting_plan_id');
    const cpSizeInfo = document.getElementById('cuttingPlanSizeInfo');

    const plateWrap = document.getElementById('motherPlateWrap');
    const plateSel  = document.getElementById('mother_stock_item_id');
    const plateInfo = document.getElementById('motherPlateInfo');
    const plateWarn = document.getElementById('motherPlateWarn');

    if (!planSel || !actSel || !cpWrap || !cpSel || !plateWrap || !plateSel) return;

    function selectedActivityCode() {
        const opt = actSel.options[actSel.selectedIndex];
        const code = (opt && opt.dataset && opt.dataset.code) ? String(opt.dataset.code) : '';
        return code.toUpperCase();
    }

    function isCutting() {
        const code = selectedActivityCode();
        return code.includes('CUT');
    }

    function selectedPlanBomId() {
        const opt = planSel.options[planSel.selectedIndex];
        const bomId = (opt && opt.dataset && opt.dataset.bomId) ? parseInt(opt.dataset.bomId, 10) : 0;
        return isNaN(bomId) ? 0 : bomId;
    }

    function normalizeWL(w, l) {
        const ww = parseInt(w || 0, 10) || 0;
        const ll = parseInt(l || 0, 10) || 0;
        if (ww <= 0 || ll <= 0) return { a: 0, b: 0 };
        return { a: Math.min(ww, ll), b: Math.max(ww, ll) };
    }

    function filterCuttingPlansByBom() {
        const bomId = selectedPlanBomId();

        Array.from(cpSel.options).forEach((o, idx) => {
            if (idx === 0) {
                o.hidden = false;
                return;
            }

            const cpBomId = o.dataset && o.dataset.bomId ? parseInt(o.dataset.bomId, 10) : 0;
            const hide = (bomId > 0 && cpBomId > 0 && cpBomId !== bomId);
            o.hidden = hide;
        });

        // If currently selected option is hidden due to filter, reset selection
        const sel = cpSel.options[cpSel.selectedIndex];
        if (sel && sel.hidden) {
            cpSel.value = '';
        }

        updateCuttingPlanSizeInfo();
    }

    function selectedCuttingPlanMeta() {
        const opt = cpSel.options[cpSel.selectedIndex];
        const thk = (opt && opt.dataset && opt.dataset.thicknessMm) ? parseInt(opt.dataset.thicknessMm, 10) : 0;
        const grade = (opt && opt.dataset && opt.dataset.grade) ? String(opt.dataset.grade) : '';
        return {
            thickness: isNaN(thk) ? 0 : thk,
            grade: grade.trim().toUpperCase(),
        };
    }

    function selectedCuttingPlanSizes() {
        const opt = cpSel.options[cpSel.selectedIndex];
        const raw = (opt && opt.dataset && opt.dataset.plateSizes) ? String(opt.dataset.plateSizes) : '[]';
        try {
            const arr = JSON.parse(raw);
            return Array.isArray(arr) ? arr : [];
        } catch (e) {
            return [];
        }
    }

    function buildRequiredSizeSet() {
        const sizes = selectedCuttingPlanSizes();
        if (!sizes.length) return null;

        const set = new Set();
        sizes.forEach(s => {
            const thk = parseInt(s.t ?? s.thickness_mm ?? s.thk ?? 0, 10) || 0;
            const w = parseInt(s.w ?? s.width_mm ?? s.width ?? 0, 10) || 0;
            const l = parseInt(s.l ?? s.length_mm ?? s.length ?? 0, 10) || 0;

            const norm = normalizeWL(w, l);
            if (thk > 0 && norm.a > 0 && norm.b > 0) {
                set.add(thk + ':' + norm.a + 'x' + norm.b);
            }
        });

        return set.size ? set : null;
    }

    function updateCuttingPlanSizeInfo() {
        if (!cpSizeInfo) return;

        const opt = cpSel.options[cpSel.selectedIndex];
        if (!opt || !cpSel.value) {
            cpSizeInfo.textContent = '';
            return;
        }

        const label = (opt.dataset && opt.dataset.plateSizesLabel) ? String(opt.dataset.plateSizesLabel) : '';
        if (label.trim()) {
            cpSizeInfo.textContent = 'Plate size(s) in Cutting Plan: ' + label;
            return;
        }

        const sizes = selectedCuttingPlanSizes();
        if (!sizes.length) {
            cpSizeInfo.textContent = 'Plate size(s) not found in Cutting Plan (filtering by thickness only).';
            return;
        }

        const pretty = sizes.map(s => {
            const thk = parseInt(s.t ?? s.thickness_mm ?? 0, 10) || 0;
            const w = parseInt(s.w ?? s.width_mm ?? 0, 10) || 0;
            const l = parseInt(s.l ?? s.length_mm ?? 0, 10) || 0;
            if (w && l && thk) return `${w}x${l}x${thk}mm`;
            if (thk) return `${thk}mm`;
            return '';
        }).filter(Boolean);

        cpSizeInfo.textContent = pretty.length
            ? ('Plate size(s) in Cutting Plan: ' + pretty.join(', '))
            : '';
    }

    function filterPlatesByCuttingPlan() {
        const meta = selectedCuttingPlanMeta();
        const thk = meta.thickness;
        const requiredSet = buildRequiredSizeSet();

        let visibleCount = 0;

        Array.from(plateSel.options).forEach((o, idx) => {
            if (idx === 0) {
                o.hidden = false;
                return;
            }

            const pThk = o.dataset && o.dataset.thicknessMm ? parseInt(o.dataset.thicknessMm, 10) : 0;
            const pW = o.dataset && o.dataset.widthMm ? parseInt(o.dataset.widthMm, 10) : 0;
            const pL = o.dataset && o.dataset.lengthMm ? parseInt(o.dataset.lengthMm, 10) : 0;

            let hide = false;

            // Always filter by thickness if available (safe baseline)
            if (thk > 0 && pThk > 0 && pThk !== thk) {
                hide = true;
            }

            // If cutting plan has explicit plate sizes, match by (thk + normalized WxL)
            if (!hide && requiredSet) {
                const norm = normalizeWL(pW, pL);
                const key = pThk + ':' + norm.a + 'x' + norm.b;

                if (norm.a <= 0 || norm.b <= 0) {
                    hide = true; // can't confirm size
                } else if (!requiredSet.has(key)) {
                    hide = true;
                }
            }

            o.hidden = hide;
            if (!hide) visibleCount++;
        });

        // Reset selection if hidden due to filter
        const sel = plateSel.options[plateSel.selectedIndex];
        if (sel && sel.hidden) {
            plateSel.value = '';
        }

        // Warn if nothing matches
        if (plateWarn) {
            if (isCutting() && cpSel.value && visibleCount === 0) {
                plateWarn.textContent = 'No matching plates found in Store for the selected Cutting Plan size. Please add the required plate to Store or choose another Cutting Plan.';
                plateWarn.classList.remove('d-none');
            } else {
                plateWarn.classList.add('d-none');
                plateWarn.textContent = '';
            }
        }

        updatePlateInfo();
    }

    function updatePlateInfo() {
        if (!plateInfo) return;

        const opt = plateSel.options[plateSel.selectedIndex];
        if (!opt || !plateSel.value) {
            plateInfo.textContent = '';
            return;
        }

        const pno = opt.dataset && opt.dataset.plateNumber ? opt.dataset.plateNumber : '';
        const hno = opt.dataset && opt.dataset.heatNumber ? opt.dataset.heatNumber : '';
        const mtc = opt.dataset && opt.dataset.mtcNumber ? opt.dataset.mtcNumber : '';
        const thk = opt.dataset && opt.dataset.thicknessMm ? opt.dataset.thicknessMm : '';
        const wmm = opt.dataset && opt.dataset.widthMm ? opt.dataset.widthMm : '';
        const lmm = opt.dataset && opt.dataset.lengthMm ? opt.dataset.lengthMm : '';

        let msg = 'Selected: ';
        if (pno) msg += 'Plate ' + pno + ' | ';
        if (hno) msg += 'Heat ' + hno + ' | ';
        if (mtc) msg += 'MTC ' + mtc + ' | ';
        if (wmm && lmm) msg += wmm + 'x' + lmm + 'mm | ';
        if (thk) msg += thk + 'mm | ';

        msg = msg.replace(/\|\s*$/, '');
        plateInfo.textContent = msg;
    }

    function updateCuttingPlanUI() {
        const cutting = isCutting();
        if (cutting) {
            cpWrap.style.display = '';
            cpSel.required = true;
            filterCuttingPlansByBom();
            updateCuttingPlanSizeInfo();

            plateWrap.style.display = '';
            plateSel.required = true;
            filterPlatesByCuttingPlan();
        } else {
            cpWrap.style.display = 'none';
            cpSel.required = false;

            plateWrap.style.display = 'none';
            plateSel.required = false;

            if (cpSizeInfo) cpSizeInfo.textContent = '';
            if (plateWarn) plateWarn.classList.add('d-none');
        }
    }

    planSel.addEventListener('change', function () {
        filterCuttingPlansByBom();
        filterPlatesByCuttingPlan();
    });

    cpSel.addEventListener('change', function () {
        updateCuttingPlanSizeInfo();
        filterPlatesByCuttingPlan();
    });

    plateSel.addEventListener('change', function () {
        updatePlateInfo();
    });

    actSel.addEventListener('change', function () {
        updateCuttingPlanUI();
    });

    // Init
    filterCuttingPlansByBom();
    updateCuttingPlanSizeInfo();
    filterPlatesByCuttingPlan();
    updateCuttingPlanUI();
})();
</script>
@endpush
