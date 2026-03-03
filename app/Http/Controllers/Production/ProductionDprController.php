<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Project;
use App\Models\StoreStockItem;
use App\Models\Uom;
use App\Models\User;
use App\Services\ApprovalNotificationService;
use App\Services\Production\GeofenceService;
use App\Services\Production\ProductionAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;

class ProductionDprController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dpr.view')->only(['index','show']);
        $this->middleware('permission:production.dpr.create')->only(['create','store']);
        $this->middleware('permission:production.dpr.submit')->only(['submit']);
        $this->middleware('permission:production.dpr.approve')->only(['approve']);
        $this->middleware('permission:production.dpr.update')->only(['cancel', 'reopen']);
        $this->middleware('permission:production.dpr.view|production.dpr.create')->only(['lookupPlans']);
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

    protected function resolveProjectId(Request $request, $project = null): int
    {
        $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) ($project ?? 0);
        if ($projectId <= 0) {
            $projectId = (int) $request->integer('project_id');
        }

        return $projectId;
    }

    protected function resolveProjectAndDprIds($project = null, $productionDpr = null): array
    {
        // Project-scoped route: /projects/{project}/production-dprs/{production_dpr}
        if ($productionDpr !== null) {
            $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) ($project ?? 0);
            $dprId = is_object($productionDpr) ? (int) ($productionDpr->id ?? 0) : (int) ($productionDpr ?? 0);
            return [$projectId, $dprId];
        }

        // Global route: /production/production-dprs/{production_dpr}
        $dprId = is_object($project) ? (int) ($project->id ?? 0) : (int) ($project ?? 0);
        if ($dprId <= 0) {
            return [0, 0];
        }

        $projectId = (int) DB::table('production_dprs as d')
            ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
            ->where('d.id', $dprId)
            ->value('p.project_id');

        return [$projectId, $dprId];
    }

    protected function activityNeedsTraceability($activity): bool
    {
        $code = strtoupper((string) ($activity->code ?? ''));
        $name = strtoupper((string) ($activity->name ?? ''));
        $isFitup = (bool) ($activity->is_fitupp ?? false);

        return $isFitup || str_contains($code, 'CUT') || str_contains($name, 'CUT');
    }

    protected function calculateQtyByMethod(
        string $method,
        object $lineMeta,
        string $activityCode = '',
        ?string $billingUomCode = null
    ): array {
        $itemLabel = trim((string) ($lineMeta->item_code ?? ''));
        if ($itemLabel === '') {
            $itemLabel = trim((string) ($lineMeta->assembly_mark ?? ''));
        }
        if ($itemLabel === '') {
            $itemLabel = 'Line#' . (int) ($lineMeta->line_id ?? 0);
        }

        $plannedQty = (float) ($lineMeta->planned_qty ?? 0);

        if ($method === 'nos') {
            if ($plannedQty <= 0) {
                return [null, "Planned qty is missing for {$itemLabel}."];
            }

            return [$plannedQty, null];
        }

        if ($method === 'kg_from_weight') {
            $weightKg = (float) ($lineMeta->planned_weight_kg ?? 0);
            if ($weightKg <= 0) {
                return [null, "Planned weight is missing for {$itemLabel}."];
            }

            if ($billingUomCode === 'MT') {
                $weightKg = $weightKg / 1000;
            }

            return [$weightKg, null];
        }

        if ($method === 'sqm_from_area') {
            $unitArea = (float) ($lineMeta->unit_area_m2 ?? 0);
            if ($plannedQty <= 0 || $unitArea <= 0) {
                return [null, "Unit area or planned qty is missing for {$itemLabel}."];
            }

            return [$unitArea * $plannedQty, null];
        }

        if ($method === 'meter_from_len') {
            $cutLen = (float) ($lineMeta->unit_cut_length_m ?? 0);
            $weldLen = (float) ($lineMeta->unit_weld_length_m ?? 0);
            if ($plannedQty <= 0) {
                return [null, "Planned qty is missing for {$itemLabel}."];
            }

            $code = strtoupper($activityCode);
            $unitLen = 0.0;

            if (str_contains($code, 'WELD') && $weldLen > 0) {
                $unitLen = $weldLen;
            } elseif (str_contains($code, 'CUT') && $cutLen > 0) {
                $unitLen = $cutLen;
            } else {
                $unitLen = $weldLen > 0 ? $weldLen : $cutLen;
            }

            if ($unitLen <= 0) {
                return [null, "Unit length metric is missing for {$itemLabel}."];
            }

            $meters = $unitLen * $plannedQty;
            if ($billingUomCode === 'KM') {
                $meters = $meters / 1000;
            }

            return [$meters, null];
        }

        return [null, 'Unsupported calculation method: ' . $method];
    }


    public function index(Request $request, $project = null)
    {
        $projectId = $this->resolveProjectId($request, $project);

        $query = DB::table('production_dprs as d')
            ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
            ->join('projects as pr', 'pr.id', '=', 'p.project_id')
            ->leftJoin('production_activities as a', 'a.id', '=', 'd.production_activity_id')
            ->leftJoin('parties as c', 'c.id', '=', 'd.contractor_party_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.worker_user_id')
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
            ]);

        if ($projectId > 0) {
            $query->where('p.project_id', $projectId);
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('p.plan_number', 'like', '%' . $q . '%')
                    ->orWhere('a.name', 'like', '%' . $q . '%')
                    ->orWhere('a.code', 'like', '%' . $q . '%')
                    ->orWhere('d.shift', 'like', '%' . $q . '%')
                    ->orWhere('d.status', 'like', '%' . $q . '%')
                    ->orWhere('c.name', 'like', '%' . $q . '%')
                    ->orWhere('u.name', 'like', '%' . $q . '%');
            });
        }

        if ($planId = (int) $request->get('production_plan_id', 0)) {
            $query->where('d.production_plan_id', $planId);
        }

        if ($activityId = (int) $request->get('production_activity_id', 0)) {
            $query->where('d.production_activity_id', $activityId);
        }

        if ($status = trim((string) $request->get('status', ''))) {
            $query->where('d.status', $status);
        }

        $dateFrom = trim((string) $request->get('date_from', ''));
        if ($dateFrom !== '') {
            $query->whereDate('d.dpr_date', '>=', $dateFrom);
        }

        $dateTo = trim((string) $request->get('date_to', ''));
        if ($dateTo !== '') {
            $query->whereDate('d.dpr_date', '<=', $dateTo);
        }

        $sort = (string) $request->get('sort', '');
        $dir = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = [
            'dpr_date' => 'd.dpr_date',
            'plan_number' => 'p.plan_number',
            'activity_name' => 'a.name',
            'status' => 'd.status',
            'created_at' => 'd.created_at',
            'id' => 'd.id',
        ];
        if ($sort !== '' && isset($sortable[$sort])) {
            $query->orderBy($sortable[$sort], $dir);
        } else {
            $query->orderByDesc('d.id');
        }

        $perPage = (int) $request->get('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $rows = $query->paginate($perPage)->withQueryString();

        $projects = DB::table('projects')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status']);

        $plans = DB::table('production_plans')
            ->when($projectId > 0, fn ($builder) => $builder->where('project_id', $projectId))
            ->orderByDesc('id')
            ->get(['id', 'plan_number']);

        $activities = DB::table('production_activities')
            ->where('is_active', 1)
            ->orderBy('default_sequence')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('production.dprs.index', [
            'projectId' => $projectId,
            'rows' => $rows,
            'plans' => $plans,
            'activities' => $activities,
            'projects' => $projects,
        ]);
    }

    public function create(Request $request, $project = null)
    {
        $projectId = $this->resolveProjectId($request, $project);

        $projects = Project::query()
            ->select(['id', 'code', 'name', 'status'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->orderBy('name')
            ->get();

        $projectRow = Project::query()
            ->select(['id', 'code', 'name'])
            ->find($projectId > 0 ? $projectId : null);

        $plans = collect();
        if ($projectId > 0) {
            $plans = DB::table('production_plans')
                ->where('project_id', $projectId)
                ->where('status', 'approved')
                ->orderByDesc('id')
                ->get();
        }

        $activities = DB::table('production_activities')
            ->where('is_active', 1)
            ->orderBy('default_sequence')
            ->orderBy('name')
            ->get();

        // Cutting plan is optional for most activities, but required when activity is CUTTING.
        // We load project-level cutting plans here and filter client-side by selected plan's BOM.
        $cuttingPlans = collect();
        if ($projectId > 0) {
            $cuttingPlans = DB::table('cutting_plans')
                ->where('project_id', $projectId)
                ->orderByDesc('id')
                ->get(['id', 'bom_id', 'name', 'grade', 'thickness_mm', 'status']);
        }
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
        $stockPlates = collect();
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
            'project' => $projectRow,
            'projects' => $projects,
            'plans' => $plans,
            'activities' => $activities,
            'cuttingPlans' => $cuttingPlans,
            'stockPlates' => $stockPlates,
            'contractors' => $contractors,
            'workers' => $workers,
        ]);
    }

    public function lookupPlans(Request $request, $project): JsonResponse
    {
        $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) $project;
        if ($projectId <= 0) {
            return response()->json([
                'plans' => [],
                'default_plan_id' => null,
            ]);
        }

        $plans = DB::table('production_plans')
            ->where('project_id', $projectId)
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->get(['id', 'plan_number', 'bom_id']);

        return response()->json([
            'plans' => $plans->map(fn ($plan) => [
                'id' => (int) ($plan->id ?? 0),
                'plan_number' => (string) ($plan->plan_number ?? ''),
                'bom_id' => (int) ($plan->bom_id ?? 0),
            ])->values(),
            'default_plan_id' => (int) ($plans->first()->id ?? 0) ?: null,
        ]);
    }

    public function store(Request $request, $project = null)
    {
        $projectId = $this->resolveProjectId($request, $project);
        if ($projectId <= 0) {
            return back()->withInput()->with('error', 'Please select a project first.');
        }

        $rules = [
            'project_id' => ['nullable', 'integer'],
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

        $requiresMachine = (int) ($activity->requires_machine ?? 0) === 1;

        $machineId = $data['machine_id'] ?? null;
        if ($machineId === '' || $machineId === 0 || $machineId === '0') {
            $machineId = null;
        } else {
            $machineId = (int) $machineId;
        }

        if ($requiresMachine && ! $machineId) {
            return back()
                ->withErrors(['machine_id' => 'Machine is required for the selected activity.'])
                ->withInput();
        }

        if ($machineId && Schema::hasTable('machines')) {
            $machineQuery = DB::table('machines')
                ->where('id', (int) $machineId)
                ->where('is_active', 1)
                ->where('status', 'active');

            if (Schema::hasColumn('machines', 'deleted_at')) {
                $machineQuery->whereNull('deleted_at');
            }

            $validMachine = $machineQuery->exists();
            if (! $validMachine) {
                return back()
                    ->withErrors(['machine_id' => 'Selected machine is not active/available.'])
                    ->withInput();
            }
        }

        $data['machine_id'] = $machineId;

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
            ->whereIn('pia.qc_status', ['na', 'passed', 'failed'])
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
                'contractor_party_id' => ($data['contractor_party_id'] ?? null) ?: null,
                'worker_user_id' => ($data['worker_user_id'] ?? null) ?: null,
                'machine_id' => ($data['machine_id'] ?? null) ?: null,
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
        [$projectId, $dprId] = $this->resolveProjectAndDprIds($project, $production_dpr);
        if ($projectId <= 0 || $dprId <= 0) {
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

        $uoms = Uom::orderBy('code')->get()->keyBy('id');

        $projectRow = Project::query()
            ->select(['id', 'code', 'name'])
            ->find($projectId);

        return view('production.dprs.show', [
            'projectId' => $projectId,
            'project' => $projectRow,
            'dpr' => $dpr,
            'lines' => $lines,
            'uoms' => $uoms,
        ]);
    }

    public function submit(Request $request, $project = null, $production_dpr = null)
    {
        [$projectId, $dprId] = $this->resolveProjectAndDprIds($project, $production_dpr);
        if ($projectId <= 0 || $dprId <= 0) {
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
            ->join('production_activities as a', 'a.id', '=', 'd.production_activity_id')
            ->where('d.id', $dprId)
            ->where('p.project_id', $projectId)
            ->select([
                'd.*',
                'a.code as activity_code',
                'a.calculation_method',
                'a.billing_uom_id',
            ])
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

        $calcMethod = (string) ($dpr->calculation_method ?? 'manual');
        $calculatedByLineId = [];

        if (in_array($calcMethod, ['kg_from_weight', 'meter_from_len', 'sqm_from_area', 'nos'], true)) {
            $lineIds = collect($data['lines'] ?? [])
                ->pluck('id')
                ->map(fn ($value) => (int) $value)
                ->filter(fn ($value) => $value > 0)
                ->unique()
                ->values()
                ->all();

            $lineMetaById = DB::table('production_dpr_lines as l')
                ->leftJoin('production_plan_items as i', 'i.id', '=', 'l.production_plan_item_id')
                ->where('l.production_dpr_id', $dprId)
                ->whereIn('l.id', $lineIds)
                ->select([
                    'l.id as line_id',
                    'l.qty_uom_id',
                    'i.item_code',
                    'i.assembly_mark',
                    'i.planned_qty',
                    'i.planned_weight_kg',
                    'i.unit_area_m2',
                    'i.unit_cut_length_m',
                    'i.unit_weld_length_m',
                ])
                ->get()
                ->keyBy('line_id');

            $billingUomId = ! empty($dpr->billing_uom_id) ? (int) $dpr->billing_uom_id : null;
            $billingUomCode = null;
            if ($billingUomId) {
                $billingUomCode = strtoupper((string) DB::table('uoms')->where('id', $billingUomId)->value('code'));
                if ($billingUomCode === '') {
                    $billingUomCode = null;
                }
            }

            $calcErrors = [];
            foreach (($data['lines'] ?? []) as $lineInput) {
                $lineId = (int) ($lineInput['id'] ?? 0);
                if ($lineId <= 0 || ! isset($lineMetaById[$lineId])) {
                    continue;
                }

                $isCompleted = isset($lineInput['is_completed']) ? 1 : 0;
                if ($isCompleted !== 1) {
                    continue;
                }

                [$qty, $error] = $this->calculateQtyByMethod(
                    $calcMethod,
                    $lineMetaById[$lineId],
                    (string) ($dpr->activity_code ?? ''),
                    $billingUomCode
                );

                if ($error !== null) {
                    $calcErrors[] = $error;
                    continue;
                }

                $calculatedByLineId[$lineId] = [
                    'qty' => round((float) $qty, 3),
                    'qty_uom_id' => $billingUomId ?: ((int) ($lineMetaById[$lineId]->qty_uom_id ?? 0) ?: null),
                ];
            }

            if (! empty($calcErrors)) {
                return back()
                    ->with('error', 'Cannot auto-calculate DPR qty. ' . implode(' ', array_values(array_unique($calcErrors))))
                    ->withInput();
            }
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

        DB::transaction(function () use ($data, $dprId, $finalGeoStatus, $finalOverrideReason, $calculatedByLineId) {
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
                $qty = (float) ($r['qty'] ?? 0);
                $qtyUomId = $r['qty_uom_id'] ?? $line->qty_uom_id;

                if ($isCompleted === 1 && isset($calculatedByLineId[$lineId])) {
                    $qty = (float) $calculatedByLineId[$lineId]['qty'];
                    $qtyUomId = $calculatedByLineId[$lineId]['qty_uom_id'];
                }

                DB::table('production_dpr_lines')
                    ->where('id', $lineId)
                    ->update([
                        'is_completed' => $isCompleted,
                        'qty' => $qty,
                        'qty_uom_id' => $qtyUomId,
                        'minutes_spent' => $r['minutes_spent'] ?? null,
                        'remarks' => $r['remarks'] ?? null,
                        'updated_at' => $now,
                    ]);
            }
        });

        $notifier = app(ApprovalNotificationService::class);
        $notifier->notifyApproversByPermission(
            'production.dpr.approve',
            'DPR Approval Required',
            "DPR #{$dprId} is pending approval.",
            [
                'module' => 'production_dpr',
                'production_dpr_id' => $dprId,
                'project_id' => $projectId,
            ],
            $notifier->safeRoute('projects.production-dprs.show', [
                'project' => $projectId,
                'production_dpr' => $dprId,
            ]),
            'warning',
            auth()->id()
        );

        return redirect(url('/projects/'.$projectId.'/production-dprs/'.$dprId))
            ->with('success','DPR submitted. Awaiting approval.');
    }

    public function approve(Request $request, $project = null, $production_dpr = null)
    {
        [$projectId, $dprId] = $this->resolveProjectAndDprIds($project, $production_dpr);
        if ($projectId <= 0 || $dprId <= 0) {
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
                'a.code',
                'a.name',
                'a.is_fitupp',
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

        $traceabilityRequired = $this->activityNeedsTraceability($dpr);

        DB::transaction(function () use ($dpr, $dprId, $projectId, $traceabilityRequired) {
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
                    $markDone = (! $traceabilityRequired) || ((int) ($l->traceability_done ?? 0) === 1);

                    DB::table('production_plan_item_activities')
                        ->where('id', (int) $l->production_plan_item_activity_id)
                        ->update([
                            'status' => $markDone ? 'done' : 'in_progress',
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

                    // Keep assembly lifecycle in sync:
                    // When the plan item is fully DONE, any assemblies created for this plan item
                    // should also be marked as COMPLETED.
                    if (! $pending && Schema::hasTable('production_assemblies')) {
                        DB::table('production_assemblies')
                            ->where('production_plan_item_id', (int) $l->production_plan_item_id)
                            ->where('status', '!=', 'completed')
                            ->update([
                                'status' => 'completed',
                                'updated_at' => $now,
                            ]);
                    }
                }
            }
        });

        $notifier = app(ApprovalNotificationService::class);
        $notifier->notifyUsers(
            [
                $dpr->submitted_by ?? null,
                $dpr->created_by ?? null,
                $dpr->worker_user_id ?? null,
            ],
            'DPR Approved',
            "DPR #{$dprId} has been approved.",
            [
                'module' => 'production_dpr',
                'production_dpr_id' => $dprId,
                'project_id' => $projectId,
            ],
            $notifier->safeRoute('projects.production-dprs.show', [
                'project' => $projectId,
                'production_dpr' => $dprId,
            ]),
            'success'
        );

        return redirect(url('/projects/'.$projectId.'/production-dprs/'.$dprId))
            ->with('success','DPR approved.');
    }

    public function cancel(Request $request, $project = null, $production_dpr = null)
    {
        [$projectId, $dprId] = $this->resolveProjectAndDprIds($project, $production_dpr);
        if ($projectId <= 0 || $dprId <= 0) {
            abort(404);
        }

        $dpr = DB::table('production_dprs as d')
            ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
            ->where('d.id', $dprId)
            ->where('p.project_id', $projectId)
            ->select('d.*')
            ->first();

        if (! $dpr) {
            abort(404);
        }

        $status = (string) ($dpr->status ?? '');
        if ($status === 'cancelled') {
            return back()->with('error', 'DPR is already cancelled.');
        }

        if (! in_array($status, ['draft', 'submitted'], true)) {
            return back()->with('error', 'Only draft/submitted DPR can be cancelled.');
        }

        DB::table('production_dprs')->where('id', $dprId)->update([
            'status' => 'cancelled',
            'updated_by' => auth()->id(),
            'updated_at' => now(),
        ]);

        ProductionAudit::log(
            $projectId,
            'dpr.cancel',
            'production_dpr',
            $dprId,
            'DPR cancelled',
            [
                'old_status' => $status,
                'new_status' => 'cancelled',
            ]
        );

        return redirect(url('/projects/' . $projectId . '/production-dprs/' . $dprId))
            ->with('success', 'DPR cancelled.');
    }

    public function reopen(Request $request, $project = null, $production_dpr = null)
    {
        [$projectId, $dprId] = $this->resolveProjectAndDprIds($project, $production_dpr);
        if ($projectId <= 0 || $dprId <= 0) {
            abort(404);
        }

        $dpr = DB::table('production_dprs as d')
            ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
            ->where('d.id', $dprId)
            ->where('p.project_id', $projectId)
            ->select('d.*')
            ->first();

        if (! $dpr) {
            abort(404);
        }

        $status = (string) ($dpr->status ?? '');
        if (! in_array($status, ['submitted', 'cancelled'], true)) {
            return back()->with('error', 'Only submitted/cancelled DPR can be reopened to draft.');
        }

        DB::table('production_dprs')->where('id', $dprId)->update([
            'status' => 'draft',
            'submitted_by' => null,
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => auth()->id(),
            'updated_at' => now(),
        ]);

        ProductionAudit::log(
            $projectId,
            'dpr.reopen',
            'production_dpr',
            $dprId,
            'DPR reopened to draft',
            [
                'old_status' => $status,
                'new_status' => 'draft',
            ]
        );

        return redirect(url('/projects/' . $projectId . '/production-dprs/' . $dprId))
            ->with('success', 'DPR reopened (draft).');
    }
}
