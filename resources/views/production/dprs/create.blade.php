@extends('layouts.erp')

@section('title', 'New DPR')

@section('content')
<div class="container-fluid">
    @php
        $hasOldPlan = session()->hasOldInput('production_plan_id');
        $defaultPlanId = old('production_plan_id');
        if (!$hasOldPlan && isset($plans) && count($plans) > 0) {
            $defaultPlanId = (string) ($plans[0]->id ?? '');
        }
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-plus-circle"></i> New DPR Entry</h2>
            <div class="small text-muted">
                @if(!empty($project) && (int)($project->id ?? 0) > 0)
                    Project: <strong>{{ $project->code }}</strong> - {{ $project->name }}
                @else
                    Select project, activity and plan to create DPR
                @endif
            </div>
        </div>
        <div class="d-flex gap-2">
            @if(\Illuminate\Support\Facades\Route::has('production.workbench.project'))
                <a class="btn btn-outline-secondary"
                   href="{{ (int)$projectId > 0 ? route('production.workbench.project', ['project' => $projectId]) : route('production.workbench') }}">
                    <i class="bi bi-grid"></i> Workbench
                </a>
            @endif
            <a class="btn btn-outline-secondary"
               href="{{ route('production.production-dprs.index', (int)$projectId > 0 ? ['project_id' => (int)$projectId] : []) }}">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Please fix the following:</div>
            <ul class="mb-0">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('production.production-dprs.store') }}">
        @csrf

        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-3">1. Select Work</h6>
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Project</label>
                        <select
                            name="project_id"
                            id="project_id_picker"
                            class="form-select"
                            data-erp-select
                            data-placeholder="Select project"
                            data-allow-clear="1"
                            data-create-url="{{ route('production.production-dprs.create') }}"
                            onchange="(function(el){var base=el.getAttribute('data-create-url')||'';if(!base){return;}var v=(el.value||'').trim();window.location.href=v?(base+'?project_id='+encodeURIComponent(v)):base;})(this)"
                            required
                        >
                            <option value="">Select project</option>
                            @foreach(($projects ?? []) as $proj)
                                <option value="{{ $proj->id }}" @selected((string) old('project_id', $projectId) === (string) $proj->id)>
                                    {{ $proj->code }} - {{ $proj->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Production Plan (Approved)</label>
                        <select name="production_plan_id" id="production_plan_id" class="form-select" data-erp-select data-placeholder="Select plan" required>
                            <option value="">Select plan</option>
                            @foreach($plans as $p)
                                <option value="{{ $p->id }}"
                                    data-bom-id="{{ (int)($p->bom_id ?? 0) }}"
                                    @selected((string)$defaultPlanId === (string)$p->id)
                                >
                                    {{ $p->plan_number }}
                                </option>
                            @endforeach
                        </select>
                        <div id="planHelpText" class="form-text {{ ((int)$projectId > 0 && count($plans) === 0) ? 'text-danger' : 'text-muted' }}">
                            @if((int)$projectId > 0 && count($plans) === 0)
                                No approved production plan found for selected project.
                            @else
                                Latest approved plan is auto-selected.
                            @endif
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Activity</label>
                        <select name="production_activity_id" class="form-select" data-erp-select data-placeholder="Select activity" required>
                            <option value="">Select activity</option>
                            @foreach($activities as $a)
                            <option value="{{ $a->id }}"
                                data-code="{{ $a->code }}"
                                data-requires-machine="{{ !empty($a->requires_machine) ? '1' : '0' }}"
                                {{ (string)old('production_activity_id') === (string)$a->id ? 'selected' : '' }}
                            >
                                {{ $a->name }} ({{ $a->code }})
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12" id="cuttingPlanWrap" style="display:none;">
                        <label class="form-label">
                            Cutting Plan <span class="text-danger">*</span>
                        </label>
                        <select name="cutting_plan_id" id="cutting_plan_id" class="form-select">
                            <option value="">Select cutting plan</option>
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
                            Required for cutting activity. Quantities are auto-loaded from cutting plan allocation.
                        </div>
                        <div id="cuttingPlanSizeInfo" class="small text-muted mt-1"></div>
                    </div>

                    <div class="col-12" id="motherPlateWrap" style="display:none;">
                        <label class="form-label">
                            Mother Plate from Store <span class="text-danger">*</span>
                        </label>
                        <select name="mother_stock_item_id" id="mother_stock_item_id" class="form-select">
                            <option value="">Select mother plate</option>
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
                            Required for cutting so plate number, heat number and MTC are linked.
                        </div>
                        <div id="motherPlateInfo" class="small text-muted mt-1"></div>
                        <div id="motherPlateWarn" class="alert alert-warning d-none mt-2 mb-0"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-3">2. Shift and Team Details</h6>
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">DPR Date</label>
                        <input type="date" name="dpr_date" class="form-control" value="{{ old('dpr_date', date('Y-m-d')) }}" required>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Shift</label>
                        <input type="text" name="shift" class="form-control" placeholder="Day / Night / A / B" value="{{ old('shift') }}">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" id="machine_id_label">Machine ID (optional)</label>
                        <input type="number" id="machine_id_input" name="machine_id" class="form-control" placeholder="Machine ID" value="{{ old('machine_id') }}">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Contractor (optional)</label>
                        <select name="contractor_party_id" class="form-select">
                            <option value="">None</option>
                            @foreach($contractors as $c)
                                <option value="{{ $c->id }}" {{ (string)old('contractor_party_id') === (string)$c->id ? 'selected' : '' }}>
                                    {{ $c->name }} ({{ $c->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Worker (optional)</label>
                        <select name="worker_user_id" class="form-select">
                            <option value="">None</option>
                            @foreach($workers as $w)
                                <option value="{{ $w->id }}" {{ (string)old('worker_user_id') === (string)$w->id ? 'selected' : '' }}>
                                    {{ $w->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Remarks (optional)</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Any supervisor note">{{ old('remarks') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky-bottom bg-body py-2 border-top">
            <div class="d-grid d-md-flex justify-content-md-end">
                <button class="btn btn-primary px-4"><i class="bi bi-check2-circle"></i> Create DPR</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const planSel = document.getElementById('production_plan_id');
    const actSel = document.querySelector('select[name="production_activity_id"]');
    const projectSel = document.getElementById('project_id_picker');
    const cpWrap = document.getElementById('cuttingPlanWrap');
    const cpSel = document.getElementById('cutting_plan_id');
    const cpSizeInfo = document.getElementById('cuttingPlanSizeInfo');
    const machineInput = document.getElementById('machine_id_input');
    const machineLabel = document.getElementById('machine_id_label');

    const plateWrap = document.getElementById('motherPlateWrap');
    const plateSel  = document.getElementById('mother_stock_item_id');
    const plateInfo = document.getElementById('motherPlateInfo');
    const plateWarn = document.getElementById('motherPlateWarn');

    if (!planSel || !actSel || !cpWrap || !cpSel || !plateWrap || !plateSel) return;

    function selectedActivityRequiresMachine() {
        const opt = actSel.options[actSel.selectedIndex];
        return !!(opt && opt.dataset && String(opt.dataset.requiresMachine) === '1');
    }

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

    function updateMachineUI() {
        if (!machineInput || !machineLabel) return;

        const required = selectedActivityRequiresMachine();
        machineInput.required = required;
        machineLabel.innerHTML = required
            ? 'Machine ID <span class="text-danger">*</span>'
            : 'Machine ID (optional)';
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
        updateMachineUI();
    });

    // Init
    filterCuttingPlansByBom();
    updateCuttingPlanSizeInfo();
    filterPlatesByCuttingPlan();
    updateCuttingPlanUI();
    updateMachineUI();
})();
</script>
@endpush
