<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Project;
use App\Models\StoreStockItem;
use App\Models\Uom;
use App\Models\User;
use App\Services\Production\GeofenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionDprController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dpr.view')->only(['index','show']);
        $this->middleware('permission:production.dpr.create')->only(['create','store']);
        $this->middleware('permission:production.dpr.submit')->only(['submit']);
        $this->middleware('permission:production.dpr.approve')->only(['approve']);
    }

    /**
     * DPR qty in your workflow is recorded in Pieces (Nos/PCS).
     * This helper finds the correct UOM id once and reuses it.
     */
    protected function defaultPiecesUomId(): ?int
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $id = Uom::query()->where('code', 'Nos')->value('id');
        if (! $id) {
            $id = Uom::query()->where('code', 'PCS')->value('id');
        }

        $cache = $id ? (int) $id : null;
        return $cache;
    }

    protected function resolveProjectId(Request $request, $project = null): ?int
    {
        $projectId = 0;

        if (is_object($project)) {
            $projectId = (int) ($project->id ?? 0);
        } else {
            $projectId = (int) ($project ?? 0);
        }

        if ($projectId <= 0) {
            $projectId = (int) $request->integer('project_id');
        }

        return $projectId > 0 ? $projectId : null;
    }

    protected function resolveProjectIdFromDpr(int $dprId): ?int
    {
        $projectId = DB::table('production_dprs as d')
            ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
            ->where('d.id', $dprId)
            ->value('p.project_id');

        return $projectId ? (int) $projectId : null;
    }

    public function index(Request $request, $project = null)
    {
        $projectId = $this->resolveProjectId($request, $project);

        $rowsQuery = DB::table('production_dprs as d')
            ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
            ->leftJoin('projects as pr', 'pr.id', '=', 'p.project_id')
            ->leftJoin('production_activities as a', 'a.id', '=', 'd.production_activity_id')
            ->leftJoin('parties as c', 'c.id', '=', 'd.contractor_party_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.worker_user_id')
            ->orderByDesc('d.id');

        if ($projectId) {
            $rowsQuery->where('p.project_id', $projectId);
        }

        $rows = $rowsQuery
            ->select([
                'd.*',
                'p.plan_number',
                'p.project_id',
                'pr.code as project_code',
                'pr.name as project_name',
                'a.name as activity_name',
                'a.code as activity_code',
                'c.name as contractor_name',
                'u.name as worker_name',
            ])
            ->paginate(25);

        $projects = Project::query()
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('production.dprs.index', [
            'projectId' => $projectId,
            'rows' => $rows,
            'projects' => $projects,
        ]);
    }

    public function create(Request $request, $project = null)
    {
        $projectId = $this->resolveProjectId($request, $project) ?? 0;
        $projects = Project::query()
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $plans = collect();
        $cuttingPlans = collect();
        $stockPlates = collect();
        $activities = DB::table('production_activities')
            ->where('is_active', 1)
            ->orderBy('default_sequence')
            ->orderBy('name')
            ->get();

        if ($projectId > 0) {
            $plans = DB::table('production_plans')
                ->where('project_id', $projectId)
                ->where('status', 'approved')
                ->orderByDesc('id')
                ->get();

        // Cutting plan is optional for most activities, but required when activity is CUTTING.
        // We load project-level cutting plans here and filter client-side by selected plan's BOM.
            $cuttingPlans = DB::table('cutting_plans')
                ->where('project_id', $projectId)
                ->orderByDesc('id')
                ->get(['id', 'bom_id', 'name', 'grade', 'thickness_mm', 'status']);
        // Attach cutting plan plate sizes (W x L x Thk) for matching Store plates on DPR create.
        // NOTE: Cutting plan header does not store width/length; those are in cutting_plan_plates.
        if (Schema::hasTable('cutting_plan_plates') && $cuttingPlans->count() > 0) {
            $sizeRows = DB::table('cutting_plan_plates')
                ->whereIn('cutting_plan_id', $cuttingPlans->pluck('id')->all())
                ->get(['cutting_plan_id', 'thickness_mm', 'width_mm', 'length_mm']);

            $sizesByPlan = [];
            $countsByPlan = [];

            foreach ($sizeRows as $r) {
                $pid = (int) ($r->cutting_plan_id ?? 0);
                $t = (int) ($r->thickness_mm ?? 0);
                $w = (int) ($r->width_mm ?? 0);
                $l = (int) ($r->length_mm ?? 0);

                if ($pid <= 0 || $t <= 0 || $w <= 0 || $l <= 0) {
                    continue;
                }

                // Normalize WxL (so 2500x12000 and 12000x2500 are treated same)
                $a = min($w, $l);
                $b = max($w, $l);
                $key = $t . ':' . $a . 'x' . $b;

                $sizesByPlan[$pid] = $sizesByPlan[$pid] ?? [];
                $countsByPlan[$pid] = $countsByPlan[$pid] ?? [];

                // Keep one representative width/length for UI label
                if (! isset($sizesByPlan[$pid][$key])) {
                    $sizesByPlan[$pid][$key] = ['t' => $t, 'w' => $w, 'l' => $l, 'a' => $a, 'b' => $b];
                }

                $countsByPlan[$pid][$key] = (int) ($countsByPlan[$pid][$key] ?? 0) + 1;
            }

            foreach ($cuttingPlans as $cp) {
                $pid = (int) ($cp->id ?? 0);

                $list = array_values($sizesByPlan[$pid] ?? []);
                usort($list, function ($x, $y) {
                    $tx = (int) ($x['t'] ?? 0);
                    $ty = (int) ($y['t'] ?? 0);
                    if ($tx !== $ty) return $tx <=> $ty;

                    $ax = (int) ($x['a'] ?? 0);
                    $ay = (int) ($y['a'] ?? 0);
                    if ($ax !== $ay) return $ax <=> $ay;

                    return (int) ($x['b'] ?? 0) <=> (int) ($y['b'] ?? 0);
                });

                // JSON-friendly array for Blade: [{t:12,w:2500,l:12000}, ...]
                $cp->plate_sizes = array_map(function ($s) {
                    return [
                        't' => (int) ($s['t'] ?? 0),
                        'w' => (int) ($s['w'] ?? 0),
                        'l' => (int) ($s['l'] ?? 0),
                    ];
                }, $list);

                $labels = [];
                foreach ($list as $s) {
                    $t = (int) ($s['t'] ?? 0);
                    $w = (int) ($s['w'] ?? 0);
                    $l = (int) ($s['l'] ?? 0);
                    if ($t <= 0 || $w <= 0 || $l <= 0) continue;

                    $a = min($w, $l);
                    $b = max($w, $l);
                    $key = $t . ':' . $a . 'x' . $b;
                    $cnt = (int) (($countsByPlan[$pid][$key] ?? 0));

                    $labels[] = $w . 'x' . $l . 'x' . $t . 'mm' . ($cnt > 1 ? (' (x' . $cnt . ')') : '');
                }
                $cp->plate_sizes_label = implode(', ', array_values(array_unique($labels)));
            }
        } else {
            // Keep properties present to avoid undefined in Blade
            foreach ($cuttingPlans as $cp) {
                $cp->plate_sizes = [];
                $cp->plate_sizes_label = '';
            }
        }


        // Store plates/stock selection (used for Cutting traceability: plate no / heat no linking)
        // Kept lightweight: only latest 500 available plates for this project (or common store).
            if (Schema::hasTable('store_stock_items')) {
                $stockPlates = DB::table('store_stock_items as s')
                    ->join('items as it', 'it.id', '=', 's.item_id')
                    ->where('s.status', 'available')
                    ->where('s.material_category', 'steel_plate')
                    ->where(function ($q) use ($projectId) {
                        $q->whereNull('s.project_id')->orWhere('s.project_id', $projectId);
                    })
                    ->orderByDesc('s.id')
                    ->limit(500)
                    ->get([
                        's.id',
                        's.item_id',
                        'it.name as item_name',
                        's.material_category',
                        's.project_id',
                        's.grade',
                        's.thickness_mm',
                        's.width_mm',
                        's.length_mm',
                        's.plate_number',
                        's.heat_number',
                        's.mtc_number',
                        's.qty_pcs_available',
                        's.weight_kg_available',
                    ]);
            }
        }

        $contractors = Party::query()
            ->where('is_contractor', true)
            ->orderBy('name')
            ->get();

        $workers = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id','name']);

        return view('production.dprs.create', [
            'projectId' => $projectId,
            'projects' => $projects,
            'plans' => $plans,
            'activities' => $activities,
            'cuttingPlans' => $cuttingPlans,
            'stockPlates' => $stockPlates,
            'contractors' => $contractors,
            'workers' => $workers,
        ]);
    }

    public function store(Request $request, $project = null)
    {
        $projectId = $this->resolveProjectId($request, $project);

        $rules = [
            'production_plan_id' => ['required','integer'],
            'production_activity_id' => ['required','integer'],
            'cutting_plan_id' => ['nullable','integer'],
            'mother_stock_item_id' => ['nullable','integer'],
            'dpr_date' => ['required','date'],
            'shift' => ['nullable','string','max:30'],
            'contractor_party_id' => ['nullable','integer'],
            'worker_user_id' => ['nullable','integer'],
            'machine_id' => ['nullable','integer'],
            'remarks' => ['nullable','string'],
        ];

        if (Schema::hasTable('store_stock_items')) {
            $rules['mother_stock_item_id'][] = 'exists:store_stock_items,id';
        }

        $data = $request->validate($rules);

        if (! $projectId && ! empty($data['production_plan_id'])) {
            $projectId = DB::table('production_plans')
                ->where('id', (int) $data['production_plan_id'])
                ->value('project_id');
            $projectId = $projectId ? (int) $projectId : null;
        }

        if (! $projectId) {
            return back()->withErrors(['project_id' => 'Please select a project.'])->withInput();
        }

        $plan = DB::table('production_plans')
            ->where('id', (int) $data['production_plan_id'])
            ->where('project_id', $projectId)
            ->where('status', 'approved')
            ->first();

        if (! $plan) {
            return back()->withErrors(['production_plan_id' => 'Plan must be approved and belong to this project.'])->withInput();
        }

        $activity = DB::table('production_activities')
            ->where('id', (int) $data['production_activity_id'])
            ->first();

        if (! $activity) {
            return back()->withErrors(['production_activity_id' => 'Invalid activity.'])->withInput();
        }

        $activityCode = strtoupper((string) ($activity->code ?? ''));
        $isCutting = str_contains($activityCode, 'CUT');

        $cuttingPlanId = $data['cutting_plan_id'] ?? null;
        if ($cuttingPlanId === '' || $cuttingPlanId === 0 || $cuttingPlanId === '0') {
            $cuttingPlanId = null;
        }

        $motherStockItemId = $data['mother_stock_item_id'] ?? null;
        if ($motherStockItemId === '' || $motherStockItemId === 0 || $motherStockItemId === '0') {
            $motherStockItemId = null;
        }

        // For CUTTING DPR, cutting plan selection is mandatory.
        // This enables auto-selection + auto-qty for parts as per the cutting plan allocations.
        $cutAllocQtyByBomItem = [];
        $cp = null;
        if ($isCutting) {
            if (! $cuttingPlanId) {
                return back()
                    ->withErrors(['cutting_plan_id' => 'Cutting Plan is required when Cutting activity is selected.'])
                    ->withInput();
            }

            $cp = DB::table('cutting_plans')
                ->where('id', (int) $cuttingPlanId)
                ->where('project_id', $projectId)
                ->first();

            if (! $cp) {
                return back()
                    ->withErrors(['cutting_plan_id' => 'Invalid Cutting Plan for this project.'])
                    ->withInput();
            }

            // If production plan has BOM, enforce the selected cutting plan is from the same BOM.
            $planBomId = (int) ($plan->bom_id ?? 0);
            if ($planBomId > 0 && (int) ($cp->bom_id ?? 0) !== $planBomId) {
                return back()
                    ->withErrors(['cutting_plan_id' => 'Selected Cutting Plan does not belong to the selected Production Plan BOM.'])
                    ->withInput();
            }

            $allocRows = DB::table('cutting_plan_allocations as al')
                ->join('cutting_plan_plates as pl', 'pl.id', '=', 'al.cutting_plan_plate_id')
                ->where('pl.cutting_plan_id', (int) $cuttingPlanId)
                ->groupBy('al.bom_item_id')
                ->select([
                    'al.bom_item_id',
                    DB::raw('SUM(al.quantity) as qty_sum'),
                ])
                ->get();

            foreach ($allocRows as $ar) {
                $bid = (int) ($ar->bom_item_id ?? 0);
                $qty = (int) ($ar->qty_sum ?? 0);
                if ($bid > 0 && $qty > 0) {
                    $cutAllocQtyByBomItem[$bid] = $qty;
                }
            }

            if (empty($cutAllocQtyByBomItem)) {
                return back()
                    ->withErrors(['cutting_plan_id' => 'Selected Cutting Plan has no allocations. Please add allocations and try again.'])
                    ->withInput();
            }

            // New: Mother plate selection from Store (plate no / heat no traceability)
            // Only enforce if the column exists (migration applied).
            if (Schema::hasColumn('production_dprs', 'mother_stock_item_id')) {
                if (! $motherStockItemId) {
                    return back()
                        ->withErrors(['mother_stock_item_id' => 'Mother Plate (Store) is required when Cutting activity is selected.'])
                        ->withInput();
                }
            }
        }

        // Validate selected mother stock item (if provided)
        if ($motherStockItemId && Schema::hasTable('store_stock_items')) {
            /** @var \App\Models\StoreStockItem|null $stock */
            $stock = StoreStockItem::query()->where('id', (int) $motherStockItemId)->first();
            if (! $stock) {
                return back()->withErrors(['mother_stock_item_id' => 'Selected Mother Plate was not found in Store.'])->withInput();
            }

            if (($stock->status ?? '') !== 'available') {
                return back()->withErrors(['mother_stock_item_id' => 'Selected Mother Plate is not available in Store (status: ' . ($stock->status ?? '-') . ').'])->withInput();
            }

            if (($stock->material_category ?? '') !== 'steel_plate') {
                return back()->withErrors(['mother_stock_item_id' => 'Selected Store item is not a Steel Plate.'])->withInput();
            }

            if (!empty($stock->project_id) && (int)$stock->project_id !== $projectId) {
                return back()->withErrors(['mother_stock_item_id' => 'Selected Mother Plate does not belong to this project store.'])->withInput();
            }

            // Thickness sanity check against cutting plan
            if ($isCutting && $cp && !empty($cp->thickness_mm) && !empty($stock->thickness_mm)) {
                if ((int)$cp->thickness_mm !== (int)$stock->thickness_mm) {
                    return back()->withErrors([
                        'mother_stock_item_id' => 'Plate thickness mismatch. Cutting Plan is ' . (int)$cp->thickness_mm . 'mm but selected plate is ' . (int)$stock->thickness_mm . 'mm.',
                    ])->withInput();
                }
            }
            // Plate size sanity check against cutting plan plates (W x L x Thk), if sizes exist.
            // This ensures Store plate matches the plate size selected in Cutting Plan design.
            if ($isCutting && $cp && $cuttingPlanId && Schema::hasTable('cutting_plan_plates')) {
                $reqSizes = DB::table('cutting_plan_plates')
                    ->where('cutting_plan_id', (int) $cuttingPlanId)
                    ->get(['thickness_mm', 'width_mm', 'length_mm']);

                $allowed = [];
                $labels = [];

                foreach ($reqSizes as $r) {
                    $t = (int) ($r->thickness_mm ?? 0);
                    $w = (int) ($r->width_mm ?? 0);
                    $l = (int) ($r->length_mm ?? 0);

                    if ($t <= 0 || $w <= 0 || $l <= 0) {
                        continue;
                    }

                    $a = min($w, $l);
                    $b = max($w, $l);
                    $key = $t . ':' . $a . 'x' . $b;

                    $allowed[$key] = true;
                    $labels[] = $w . 'x' . $l . 'x' . $t . 'mm';
                }

                $labels = array_values(array_unique($labels));

                // Only enforce if cutting plan actually has plate sizes.
                if (! empty($allowed)) {
                    $st = (int) ($stock->thickness_mm ?? 0);
                    $sw = (int) ($stock->width_mm ?? 0);
                    $sl = (int) ($stock->length_mm ?? 0);

                    if ($sw <= 0 || $sl <= 0) {
                        return back()->withErrors([
                            'mother_stock_item_id' => 'Selected plate is missing Width/Length in Store. Please update Store stock item with correct size.',
                        ])->withInput();
                    }

                    $sa = min($sw, $sl);
                    $sb = max($sw, $sl);
                    $skey = $st . ':' . $sa . 'x' . $sb;

                    if (! isset($allowed[$skey])) {
                        $want = ! empty($labels) ? implode(', ', $labels) : 'as per Cutting Plan';
                        return back()->withErrors([
                            'mother_stock_item_id' => 'Plate size mismatch. Cutting Plan requires: ' . $want . ' but selected plate is ' . $sw . 'x' . $sl . 'x' . $st . 'mm.',
                        ])->withInput();
                    }
                }
            }

        }

        $defaultPiecesUomId = $this->defaultPiecesUomId();

        // -------------------------------------------------------
        // IMPORTANT
        // Prevent creation of an empty DPR (header with zero lines).
        // This happens when the selected activity is blocked by previous
        // activities / QC gate or routing is not enabled.
        // -------------------------------------------------------
        $piaRows = DB::table('production_plan_item_activities as pia')
            ->join('production_plan_items as i', 'i.id', '=', 'pia.production_plan_item_id')
            ->where('i.production_plan_id', (int) $data['production_plan_id'])
            ->where('pia.production_activity_id', (int) $data['production_activity_id'])
            ->where('pia.is_enabled', 1)
            ->where('pia.status', 'pending')
            ->whereIn('pia.qc_status', ['na','passed'])
            ->select([
                'pia.id as pia_id',
                'pia.production_plan_item_id as item_id',
                'pia.sequence_no as pia_seq',
                'i.uom_id as item_uom_id',
                'i.bom_item_id as bom_item_id',
            ])
            ->get();

        $eligible = [];
        foreach ($piaRows as $r) {
            // For cutting DPRs created from a cutting plan, only include items allocated in that plan.
            if ($isCutting) {
                $bomItemId = (int) ($r->bom_item_id ?? 0);
                if ($bomItemId <= 0 || ! isset($cutAllocQtyByBomItem[$bomItemId])) {
                    continue;
                }
            }

            $blocked = DB::table('production_plan_item_activities')
                ->where('production_plan_item_id', (int) $r->item_id)
                ->where('is_enabled', 1)
                ->where('sequence_no', '<', (int) $r->pia_seq)
                ->where('status', '!=', 'done')
                ->exists();

            if ($blocked) continue;

            $eligible[] = $r;
        }

        if (empty($eligible)) {
            $msg = 'No eligible items found for the selected activity. Complete previous activities / QC first, or enable routing for this activity.';
            if ($isCutting) {
                $msg = 'No eligible items found for the selected Cutting Plan. Check allocations, ensure the plan matches the Production Plan BOM, and make sure previous activities (if any) are completed.';
            }

            return back()
                ->withInput()
                ->with('error', $msg);
        }

        $dprId = DB::transaction(function () use ($data, $defaultPiecesUomId, $eligible, $isCutting, $cuttingPlanId, $cutAllocQtyByBomItem, $motherStockItemId) {
            $now = now();

            $dprInsert = [
                'production_plan_id' => (int) $data['production_plan_id'],
                'production_activity_id' => (int) $data['production_activity_id'],
                'dpr_date' => $data['dpr_date'],
                'shift' => $data['shift'] ?? null,
                'contractor_party_id' => $data['contractor_party_id'] ?: null,
                'worker_user_id' => $data['worker_user_id'] ?: null,
                'machine_id' => $data['machine_id'] ?: null,
                'geo_latitude' => null,
                'geo_longitude' => null,
                'geo_accuracy_m' => null,
                'geo_status' => null,
                'status' => 'draft',
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Backward-compatible: only set the column if the migration has been run.
            if (Schema::hasColumn('production_dprs', 'cutting_plan_id')) {
                $dprInsert['cutting_plan_id'] = $cuttingPlanId ? (int) $cuttingPlanId : null;
            }

            if (Schema::hasColumn('production_dprs', 'mother_stock_item_id')) {
                $dprInsert['mother_stock_item_id'] = $motherStockItemId ? (int) $motherStockItemId : null;
            }

            $dprId = DB::table('production_dprs')->insertGetId($dprInsert);

            $lineInserts = [];
            foreach ($eligible as $r) {
                $qty = 0;
                $isCompleted = 0;
                $qtyUomId = ($r->item_uom_id ? (int) $r->item_uom_id : ($defaultPiecesUomId ?: null));

                if ($isCutting) {
                    $bomItemId = (int) ($r->bom_item_id ?? 0);
                    $qty = (int) ($cutAllocQtyByBomItem[$bomItemId] ?? 0);
                    if ($qty < 0) { $qty = 0; }

                    // Auto-tick and auto-qty for cutting as per cutting plan.
                    $isCompleted = 1;
                    $qtyUomId = $defaultPiecesUomId ?: $qtyUomId;
                }

                $lineInserts[] = [
                    'production_dpr_id' => $dprId,
                    'production_plan_item_id' => (int) $r->item_id,
                    'production_plan_item_activity_id' => (int) $r->pia_id,
                    'production_assembly_id' => null,
                    'is_completed' => $isCompleted,
                    'remarks' => null,
                    'traceability_done' => 0,
                    'traceability_done_at' => null,
                    'qty' => $qty,
                    'qty_uom_id' => $qtyUomId,
                    'minutes_spent' => null,
                    'efficiency_metric' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('production_dpr_lines')->insert($lineInserts);

            return $dprId;
        });

        return redirect(url('/projects/'.$projectId.'/production-dprs/'.$dprId))
            ->with('success', 'DPR created (draft). Tick items and submit.');
    }

    public function show(Request $request, $project = null, $production_dpr = null)
    {
        $dprId = (int) $production_dpr;
        $projectId = $this->resolveProjectId($request, $project) ?? $this->resolveProjectIdFromDpr($dprId);
        if (! $projectId) {
            abort(404);
        }

        $dprQuery = DB::table('production_dprs as d')
            ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
            ->leftJoin('production_activities as a', 'a.id', '=', 'd.production_activity_id')
            ->where('d.id', $dprId)
            ->where('p.project_id', $projectId);

        $dprSelect = [
            'd.*',
            'p.plan_number',
            'a.name as activity_name',
            'a.code as activity_code',
            'a.requires_qc',
            'a.requires_machine',
            'a.is_fitupp',
            'a.billing_uom_id',
        ];

        // Backward-compatible: only join cutting plans if the migration has been run.
        if (Schema::hasColumn('production_dprs', 'cutting_plan_id')) {
            $dprQuery->leftJoin('cutting_plans as cp', 'cp.id', '=', 'd.cutting_plan_id');
            $dprSelect[] = 'cp.name as cutting_plan_name';
        } else {
            $dprSelect[] = DB::raw('NULL as cutting_plan_name');
        }

        // Mother plate details (Store)
        if (Schema::hasColumn('production_dprs', 'mother_stock_item_id')) {
            $dprQuery->leftJoin('store_stock_items as ms', 'ms.id', '=', 'd.mother_stock_item_id');
            $dprSelect[] = 'ms.plate_number as mother_plate_number';
            $dprSelect[] = 'ms.heat_number as mother_heat_number';
            $dprSelect[] = 'ms.mtc_number as mother_mtc_number';
            $dprSelect[] = 'ms.thickness_mm as mother_thickness_mm';
        } else {
            $dprSelect[] = DB::raw('NULL as mother_plate_number');
            $dprSelect[] = DB::raw('NULL as mother_heat_number');
            $dprSelect[] = DB::raw('NULL as mother_mtc_number');
            $dprSelect[] = DB::raw('NULL as mother_thickness_mm');
        }

        $dpr = $dprQuery->select($dprSelect)->first();

        if (! $dpr) abort(404);

        $lines = DB::table('production_dpr_lines as l')
            ->leftJoin('production_plan_items as i', 'i.id', '=', 'l.production_plan_item_id')
            ->where('l.production_dpr_id', $dprId)
            ->orderBy('l.id')
            ->select([
                'l.*',
                'i.item_type',
                'i.item_code',
                'i.description as item_description',
                'i.assembly_mark',
                'i.assembly_type',
                'i.planned_qty',
                'i.uom_id as item_uom_id',
                'i.planned_weight_kg',
            ])
            ->get();

        // -------------------------------------------------------
        // Remaining Qty helper (UI)
        // -------------------------------------------------------
        // Show "Done till date" (excluding this DPR) and "Remaining"
        // so users can avoid entering qty beyond planned qty.
        $piaIds = $lines->pluck('production_plan_item_activity_id')
            ->filter(fn ($v) => (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $doneQtyByPia = [];
        if (! empty($piaIds)) {
            $doneQtyByPia = DB::table('production_dpr_lines as dl')
                ->join('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
                ->whereIn('d.status', ['submitted', 'approved'])
                ->where('d.id', '!=', $dprId)
                ->whereIn('dl.production_plan_item_activity_id', $piaIds)
                ->where('dl.is_completed', 1)
                ->groupBy('dl.production_plan_item_activity_id')
                ->selectRaw('dl.production_plan_item_activity_id as pia_id, COALESCE(SUM(dl.qty),0) as qty_sum')
                ->pluck('qty_sum', 'pia_id')
                ->map(fn ($v) => (float) $v)
                ->all();
        }

        foreach ($lines as $ln) {
            $planned = (float) ($ln->planned_qty ?? 0);
            $piaId = (int) ($ln->production_plan_item_activity_id ?? 0);
            $done = $piaId > 0 ? (float) ($doneQtyByPia[$piaId] ?? 0) : 0.0;

            $remaining = $planned - $done;
            if ($remaining < 0) {
                $remaining = 0.0;
            }

            // Attach computed fields for Blade UI
            $ln->qty_done_before = $done;
            $ln->qty_remaining_before = $remaining;
        }

        $uoms = Uom::orderBy('code')->get()->keyBy('id');

        return view('production.dprs.show', [
            'projectId' => $projectId,
            'dpr' => $dpr,
            'lines' => $lines,
            'uoms' => $uoms,
        ]);
    }

    public function submit(Request $request, $project = null, $production_dpr = null)
    {
        $dprId = (int) $production_dpr;
        $projectId = $this->resolveProjectId($request, $project) ?? $this->resolveProjectIdFromDpr($dprId);
        if (! $projectId) {
            abort(404);
        }

        $data = $request->validate([
            'geo_latitude' => ['nullable','numeric'],
            'geo_longitude' => ['nullable','numeric'],
            'geo_accuracy_m' => ['nullable','numeric'],
            'geo_status' => ['nullable','string','max:30'],
            'geo_override_reason' => ['nullable','string','max:500'],
            'lines' => ['required','array'],
            'lines.*.id' => ['required','integer'],
            'lines.*.is_completed' => ['nullable','boolean'],
            'lines.*.qty' => ['nullable','numeric','min:0'],
            'lines.*.qty_uom_id' => ['nullable','integer'],
            'lines.*.minutes_spent' => ['nullable','numeric','min:0'],
            'lines.*.remarks' => ['nullable','string'],
        ]);

        $dpr = DB::table('production_dprs as d')
            ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
            ->where('d.id', $dprId)
            ->where('p.project_id', $projectId)
            ->select('d.*')
            ->first();

        if (! $dpr) abort(404);
        if (($dpr->status ?? '') !== 'draft') {
            return back()->with('error','Only draft DPR can be submitted.');
        }

        // Guard: prevent submitting empty DPR (no completed lines)
        $hasCompleted = false;
        foreach (($data['lines'] ?? []) as $r) {
            if (isset($r['is_completed']) && (int)$r['is_completed'] === 1) {
                $hasCompleted = true;
                break;
            }
        }
        if (! $hasCompleted) {
            return back()
                ->with('error', 'Please tick at least one item as Done before submitting the DPR.')
                ->withInput();
        }

        // -------------------------------------------------------
        // Qty validation (server-side)
        // -------------------------------------------------------
        // Entered qty must not exceed remaining qty:
        // Remaining = planned_qty - (qty already reported in OTHER submitted/approved DPRs)
        // We exclude current DPR because it is being edited right now.
        $payloadLines = $data['lines'] ?? [];

        $lineIds = [];
        foreach ($payloadLines as $r) {
            if (isset($r['id'])) {
                $lineIds[] = (int) $r['id'];
            }
        }
        $lineIds = array_values(array_unique(array_filter($lineIds, fn ($v) => $v > 0)));

        $metaById = [];
        $piaIds = [];

        if (! empty($lineIds)) {
            $metaRows = DB::table('production_dpr_lines as l')
                ->leftJoin('production_plan_items as i', 'i.id', '=', 'l.production_plan_item_id')
                ->where('l.production_dpr_id', $dprId)
                ->whereIn('l.id', $lineIds)
                ->select([
                    'l.id',
                    'l.production_plan_item_id',
                    'l.production_plan_item_activity_id',
                    'i.planned_qty',
                    'i.item_type',
                    'i.item_code',
                    'i.assembly_mark',
                ])
                ->get();

            foreach ($metaRows as $mr) {
                $metaById[(int) $mr->id] = $mr;
                if (! empty($mr->production_plan_item_activity_id)) {
                    $piaIds[] = (int) $mr->production_plan_item_activity_id;
                }
            }
        }

        $piaIds = array_values(array_unique(array_filter($piaIds, fn ($v) => $v > 0)));

        $doneQtyByPia = [];
        if (! empty($piaIds)) {
            $doneQtyByPia = DB::table('production_dpr_lines as dl')
                ->join('production_dprs as d', 'd.id', '=', 'dl.production_dpr_id')
                ->whereIn('d.status', ['submitted', 'approved'])
                ->where('d.id', '!=', $dprId)
                ->whereIn('dl.production_plan_item_activity_id', $piaIds)
                ->where('dl.is_completed', 1)
                ->groupBy('dl.production_plan_item_activity_id')
                ->selectRaw('dl.production_plan_item_activity_id as pia_id, COALESCE(SUM(dl.qty),0) as qty_sum')
                ->pluck('qty_sum', 'pia_id')
                ->map(fn ($v) => (float) $v)
                ->all();
        }

        $qtyErrors = [];
        foreach ($payloadLines as $idx => $r) {
            $lineId = (int) ($r['id'] ?? 0);
            $meta = $metaById[$lineId] ?? null;
            if (! $meta) {
                continue;
            }

            $isCompleted = isset($r['is_completed']) ? 1 : 0;
            if ($isCompleted !== 1) {
                continue;
            }

            $enteredQty = (float) ($r['qty'] ?? 0);
            $plannedQty = (float) ($meta->planned_qty ?? 0);

            $piaId = (int) ($meta->production_plan_item_activity_id ?? 0);
            $alreadyQty = $piaId > 0 ? (float) ($doneQtyByPia[$piaId] ?? 0) : 0.0;

            $remainingQty = $plannedQty - $alreadyQty;
            if ($remainingQty < 0) {
                $remainingQty = 0.0;
            }

            // Upper bound check
            if ($enteredQty > $remainingQty + 0.000001) {
                $label = ($meta->item_type === 'assembly')
                    ? ($meta->assembly_mark ?: ('#' . $meta->production_plan_item_id))
                    : ($meta->item_code ?: ('#' . $meta->production_plan_item_id));

                $qtyErrors['lines.' . $idx . '.qty'] =
                    'Qty for ' . $label . ' exceeds remaining qty. ' .
                    'Planned: ' . $plannedQty . ', Already: ' . $alreadyQty . ', Remaining: ' . $remainingQty . '.';
            }
        }

        if (! empty($qtyErrors)) {
            return back()->withErrors($qtyErrors)->withInput();
        }


        // -------------------------------------------------------
        // WP-06 Geofence enforcement (server-side)
        // -------------------------------------------------------
        $geoLat = $data['geo_latitude'] ?? null;
        $geoLng = $data['geo_longitude'] ?? null;

        /** @var \App\Services\Production\GeofenceService $geofence */
        $geofence = app(GeofenceService::class);
        $eval = $geofence->evaluate($geoLat, $geoLng);

        // If geofence is enabled and location is missing -> block submit
        if (($eval['enabled'] ?? false) === true && (($eval['status'] ?? '') === 'unknown')) {
            return back()->with('error', 'Location is required to submit DPR. Please capture GPS location and try again.');
        }

        // If outside geofence -> require override permission + reason
        if (($eval['enabled'] ?? false) === true && (($eval['status'] ?? '') === 'outside')) {
            if (! auth()->user()?->can('production.geofence.override')) {
                $dist = $eval['distance_m'] ?? null;
                $msg = 'You are outside the allowed geofence. DPR submission is blocked.';
                if ($dist !== null) {
                    $msg .= ' (Distance: ' . number_format((float)$dist, 0) . ' m)';
                }
                return back()->with('error', $msg);
            }

            $reason = trim((string)($data['geo_override_reason'] ?? ''));
            if ($reason === '') {
                return back()
                    ->with('error', 'Outside geofence: override reason is required to submit DPR.')
                    ->withInput();
            }
        }

        // Normalize stored geo_status (do not trust client-sent geo_status)
        $finalGeoStatus = $eval['enabled'] ? ($eval['status'] ?? null) : ($data['geo_status'] ?? null);
        if (($eval['enabled'] ?? false) === true && ($eval['status'] ?? '') === 'outside') {
            $finalGeoStatus = 'override';
        }

        $finalOverrideReason = null;
        if ($finalGeoStatus === 'override') {
            $finalOverrideReason = trim((string)($data['geo_override_reason'] ?? ''));
        }

        DB::transaction(function () use ($data, $dprId, $finalGeoStatus, $finalOverrideReason) {
            $now = now();

            DB::table('production_dprs')->where('id', $dprId)->update([
                'geo_latitude' => $data['geo_latitude'] ?? null,
                'geo_longitude' => $data['geo_longitude'] ?? null,
                'geo_accuracy_m' => $data['geo_accuracy_m'] ?? null,
                'geo_status' => $finalGeoStatus,
                'geo_override_reason' => $finalOverrideReason,
                'status' => 'submitted',
                'submitted_by' => auth()->id(),
                'submitted_at' => $now,
                'updated_by' => auth()->id(),
                'updated_at' => $now,
            ]);

            foreach (($data['lines'] ?? []) as $idx => $r) {
                $lineId = (int) $r['id'];

                $line = DB::table('production_dpr_lines')
                    ->where('id', $lineId)
                    ->where('production_dpr_id', $dprId)
                    ->first();

                if (! $line) continue;

                // Checkbox handling: if not present, it's unchecked
                $isCompleted = isset($r['is_completed']) ? 1 : 0;
                $qty = $isCompleted ? (float) ($r['qty'] ?? 0) : 0.0;

                DB::table('production_dpr_lines')
                    ->where('id', $lineId)
                    ->update([
                        'is_completed' => $isCompleted,
                        'qty' => $qty,
                        'qty_uom_id' => $r['qty_uom_id'] ?? $line->qty_uom_id,
                        'minutes_spent' => $r['minutes_spent'] ?? null,
                        'remarks' => $r['remarks'] ?? null,
                        'updated_at' => $now,
                    ]);
            }
        });

        return redirect(url('/projects/'.$projectId.'/production-dprs/'.$dprId))
            ->with('success','DPR submitted. Awaiting approval.');
    }

    public function approve(Request $request, $project = null, $production_dpr = null)
    {
        $dprId = (int) $production_dpr;
        $projectId = $this->resolveProjectId($request, $project) ?? $this->resolveProjectIdFromDpr($dprId);
        if (! $projectId) {
            abort(404);
        }

        $dpr = DB::table('production_dprs as d')
            ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
            ->join('production_activities as a', 'a.id', '=', 'd.production_activity_id')
            ->where('d.id', $dprId)
            ->where('p.project_id', $projectId)
            ->select([
                'd.*',
                'a.requires_qc',
            ])
            ->first();

        if (! $dpr) abort(404);
        if (($dpr->status ?? '') !== 'submitted') {
            return back()->with('error','Only submitted DPR can be approved.');
        }

        $completedCount = (int) DB::table('production_dpr_lines')
            ->where('production_dpr_id', $dprId)
            ->where('is_completed', 1)
            ->count();

        if ($completedCount <= 0) {
            return back()->with('error', 'Cannot approve DPR because no items are marked as Done.');
        }

        DB::transaction(function () use ($dpr, $dprId, $projectId) {
            $now = now();

            DB::table('production_dprs')->where('id', $dprId)->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => $now,
                'updated_by' => auth()->id(),
                'updated_at' => $now,
            ]);

            $lines = DB::table('production_dpr_lines')
                ->where('production_dpr_id', $dprId)
                ->where('is_completed', 1)
                ->get();

            foreach ($lines as $l) {
                if (! $l->production_plan_item_activity_id) continue;

                if ((int) ($dpr->requires_qc ?? 0) === 1) {
                    DB::table('production_plan_item_activities')
                        ->where('id', (int) $l->production_plan_item_activity_id)
                        ->update([
                            'status' => 'in_progress',
                            'qc_status' => 'pending',
                            'updated_at' => $now,
                        ]);

                    // Create QC check (schema-aligned)
                    DB::table('production_qc_checks')->insert([
                        'project_id' => (int) $projectId,
                        'production_plan_id' => (int) $dpr->production_plan_id,
                        'production_activity_id' => (int) $dpr->production_activity_id,
                        'production_plan_item_id' => $l->production_plan_item_id ? (int) $l->production_plan_item_id : null,
                        'production_plan_item_activity_id' => (int) $l->production_plan_item_activity_id,
                        'production_dpr_id' => (int) $dprId,
                        'production_dpr_line_id' => (int) $l->id,
                        'result' => 'pending',
                        'remarks' => null,
                        'checked_by' => null,
                        'checked_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('production_plan_item_activities')
                        ->where('id', (int) $l->production_plan_item_activity_id)
                        ->update([
                            'status' => 'done',
                            'qc_status' => 'na',
                            'updated_at' => $now,
                        ]);
                }

                if ($l->production_plan_item_id) {
                    $pending = DB::table('production_plan_item_activities')
                        ->where('production_plan_item_id', (int) $l->production_plan_item_id)
                        ->where('is_enabled', 1)
                        ->where('status', '!=', 'done')
                        ->exists();

                    DB::table('production_plan_items')
                        ->where('id', (int) $l->production_plan_item_id)
                        ->update([
                            'status' => $pending ? 'in_progress' : 'done',
                            'updated_at' => $now,
                        ]);
                }
            }
        });

        return redirect(url('/projects/'.$projectId.'/production-dprs/'.$dprId))
            ->with('success','DPR approved.');
    }
}


