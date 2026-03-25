<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Party;
use App\Models\Project;
use App\Models\Production\ProductionRemnant;
use App\Models\ProductionV2\ProductionCutBatch;
use App\Models\ProductionV2\ProductionCuttingPlan;
use App\Models\ProductionV2\ProductionAssemblyPartRequirement;
use App\Models\ProductionV2\ProductionWipItem;
use App\Models\StoreIssue;
use App\Models\StoreIssueLine;
use App\Models\StoreStockItem;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\ProductionV2\DailyDprManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ProductionV2CutBatchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dpr.view')->only(['index', 'show']);
        $this->middleware('permission:production.dpr.create')->only(['create', 'store']);
    }

    public function index(Project $project)
    {
        $rows = ProductionCutBatch::query()
            ->where('project_id', $project->id)
            ->with(['cuttingPlan', 'motherStock', 'storeIssue'])
            ->withCount(['wipItems', 'remnants'])
            ->orderByDesc('id')
            ->paginate(25);

        return view('production_v2.cut_batches.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }

    public function create(Request $request, Project $project)
    {
        $selectedDpr = app(DailyDprManager::class)->findForActivity($project, (int) $request->integer('dpr_id'), 'cutting');
        $selectedPlanId = (int) $request->integer('cutting_plan_id');

        $planStatusCounts = ProductionCuttingPlan::query()
            ->where('project_id', $project->id)
            ->selectRaw('status, COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        $plans = ProductionCuttingPlan::query()
            ->where('project_id', $project->id)
            ->where('status', 'released')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (ProductionCuttingPlan $plan) => $this->planNeedsCutting($plan))
            ->values();

        $selectedPlan = $selectedPlanId > 0
            ? ProductionCuttingPlan::query()
                ->where('project_id', $project->id)
                ->where('status', 'released')
                ->with(['allocations.partDefinition'])
                ->find($selectedPlanId)
            : null;

        if ($selectedPlan && ! $this->planNeedsCutting($selectedPlan)) {
            $selectedPlan = null;
        }

        $selectedPlanProfile = $selectedPlan
            ? $this->resolvePlanMaterialProfile($selectedPlan)
            : null;

        $stockItemsQuery = StoreStockItem::query()
            ->where(function ($query) use ($project) {
                $query->where('project_id', $project->id)
                    ->orWhereNull('project_id');
            })
            ->where('status', 'available')
            ->where(function ($query) {
                $query->where('qty_pcs_available', '>', 0)
                    ->orWhere('weight_kg_available', '>', 0);
            });

        if ($selectedPlanProfile) {
            if (! empty($selectedPlanProfile['material_category'])) {
                $stockItemsQuery->where('material_category', $selectedPlanProfile['material_category']);
            }

            if (! empty($selectedPlanProfile['grade'])) {
                $stockItemsQuery->where(function ($query) use ($selectedPlanProfile) {
                    $query->where('grade', $selectedPlanProfile['grade'])
                        ->orWhereNull('grade')
                        ->orWhere('grade', '');
                });
            }

            if (! empty($selectedPlanProfile['thickness_mm'])) {
                $stockItemsQuery->where('thickness_mm', $selectedPlanProfile['thickness_mm']);
            }

            if (($selectedPlanProfile['source_mode'] ?? null) === 'fresh_plate') {
                $stockItemsQuery->where(function ($query) {
                    $query->whereNull('is_remnant')
                        ->orWhere('is_remnant', false);
                });
            }

            if (($selectedPlanProfile['source_mode'] ?? null) === 'remnant') {
                $stockItemsQuery->where('is_remnant', true);
            }
        }

        return view('production_v2.cut_batches.form', [
            'project' => $project,
            'plans' => $plans,
            'planStatusCounts' => [
                'draft' => (int) ($planStatusCounts['draft'] ?? 0),
                'approved' => (int) ($planStatusCounts['approved'] ?? 0),
                'released' => (int) ($planStatusCounts['released'] ?? 0),
            ],
            'selectedPlan' => $selectedPlan,
            'selectedPlanProfile' => $selectedPlanProfile,
            'stockItems' => $stockItemsQuery
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'plate_number', 'section_profile', 'heat_number', 'mtc_number', 'thickness_mm', 'width_mm', 'length_mm', 'grade', 'material_category', 'status', 'project_id', 'is_remnant']),
            'machines' => Machine::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'contractors' => Party::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->limit(300)->get(['id', 'name']),
            'selectedDpr' => $selectedDpr,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'dpr_id' => ['nullable', 'integer', 'exists:production_dprs,id'],
            'cutting_plan_id' => [
                'required',
                'integer',
                Rule::exists('production_v2_cutting_plans', 'id')->where(fn ($query) => $query->where('project_id', $project->id)->where('status', 'released')),
            ],
            'cut_date' => ['required', 'date'],
            'mother_stock_item_id' => ['required', 'integer', 'exists:store_stock_items,id'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'operator_id' => ['nullable', 'integer', 'exists:users,id'],
            'contractor_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'shift' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['draft', 'approved'])],
            'remarks' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.allocation_id' => ['required', 'integer', 'exists:production_v2_cutting_plan_allocations,id'],
            'rows.*.produced_qty' => ['required', 'numeric', 'min:0.001'],
            'rows.*.mode' => ['nullable', Rule::in(['piece', 'lot'])],
            'rows.*.remarks' => ['nullable', 'string'],
            'batch_remnants' => ['nullable', 'array'],
            'batch_remnants.*.width_mm' => ['nullable', 'integer', 'min:0'],
            'batch_remnants.*.length_mm' => ['nullable', 'integer', 'min:0'],
            'batch_remnants.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'batch_remnants.*.is_usable' => ['nullable', 'boolean'],
            'batch_remnants.*.remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $selectedDpr = null;
        if (! empty($data['dpr_id'])) {
            $selectedDpr = app(DailyDprManager::class)->findForActivity($project, (int) $data['dpr_id'], 'cutting');
            if (! $selectedDpr) {
                return back()->withInput()->withErrors(['dpr_id' => 'Selected DPR is not valid for Production V2 cutting.']);
            }
        }

        $plan = ProductionCuttingPlan::query()
            ->where('project_id', $project->id)
            ->where('status', 'released')
            ->with(['allocations.partDefinition'])
            ->findOrFail((int) $data['cutting_plan_id']);

        if (! $this->planNeedsCutting($plan)) {
            return back()->withInput()->withErrors([
                'cutting_plan_id' => 'Selected cutting plan is already fully cut.',
            ]);
        }

        $planProfile = $this->resolvePlanMaterialProfile($plan);

        $stock = StoreStockItem::query()->findOrFail((int) $data['mother_stock_item_id']);
        if ($stock->project_id !== null && (int) $stock->project_id !== (int) $project->id) {
            return back()->withInput()->withErrors(['mother_stock_item_id' => 'Selected source stock does not belong to this project.']);
        }

        $stockMismatchMessage = $this->validateSourceStockAgainstPlan($stock, $planProfile);
        if ($stockMismatchMessage !== null) {
            return back()->withInput()->withErrors(['mother_stock_item_id' => $stockMismatchMessage]);
        }

        $allocationMap = $plan->allocations->keyBy('id');

        $cutBatch = DB::transaction(function () use ($project, $data, $stock, $allocationMap, $plan, $selectedDpr) {
            $stock = StoreStockItem::query()
                ->lockForUpdate()
                ->findOrFail($stock->id);

            $availablePieces = (int) ($stock->qty_pcs_available ?? 0);
            $availableWeight = (float) ($stock->weight_kg_available ?? 0);

            if ((string) $stock->status !== 'available' || ($availablePieces <= 0 && $availableWeight <= 0)) {
                throw new \RuntimeException('Selected source stock is not available for cutting.');
            }

            $cutBatch = ProductionCutBatch::query()->create([
                'project_id' => $project->id,
                'cutting_plan_id' => $plan->id,
                'dpr_id' => $selectedDpr?->id,
                'cut_date' => $data['cut_date'],
                'mother_stock_item_id' => $stock->id,
                'machine_id' => $data['machine_id'] ?? $selectedDpr?->machine_id,
                'operator_id' => $data['operator_id'] ?? $selectedDpr?->worker_user_id,
                'contractor_party_id' => $data['contractor_party_id'] ?? $selectedDpr?->contractor_party_id,
                'shift' => $data['shift'] ?? $selectedDpr?->shift,
                'plate_number_snapshot' => $stock->plate_number,
                'heat_number_snapshot' => $stock->heat_number,
                'mtc_number_snapshot' => $stock->mtc_number,
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if ($this->shouldCreateStoreIssueForCutBatch($stock)) {
                $issue = $this->createStoreIssueForCutBatch($project, $data, $stock, $cutBatch);
                $cutBatch->store_issue_id = $issue->id;
                $cutBatch->save();
            }

            foreach ($data['rows'] as $row) {
                $allocation = $allocationMap->get((int) $row['allocation_id']);
                if (! $allocation) {
                    continue;
                }

                $part = $allocation->partDefinition;
                if (! $part) {
                    continue;
                }

                $qty = (float) $row['produced_qty'];
                $mode = $row['mode'] ?? ($qty <= 1 ? 'piece' : 'lot');
                $reference = ProductionWipItem::generateReference($project->code, $mode === 'piece' ? 'WIP' : 'LOT', $plan->plan_number);
                $this->assertNonInterchangeablePartScope($project, $part);

                ProductionWipItem::query()->create([
                    'project_id' => $project->id,
                    'part_definition_id' => $part->id,
                    'cut_batch_id' => $cutBatch->id,
                    'piece_no' => $mode === 'piece' ? $reference : null,
                    'lot_no' => $mode === 'lot' ? $reference : null,
                    'qty' => $qty,
                    'uom_id' => $part->uom_id,
                    'thickness_mm' => $allocation->thickness_mm ?: $part->thickness_mm ?: $stock->thickness_mm,
                    'width_mm' => $allocation->cut_width_mm ?: $part->width_mm,
                    'length_mm' => $allocation->cut_length_mm ?: $part->length_mm,
                    'weight_kg' => $part->unit_weight_kg ? ((float) $part->unit_weight_kg * $qty) : null,
                    'mother_stock_item_id' => $stock->id,
                    'plate_number' => $stock->plate_number,
                    'heat_number' => $stock->heat_number,
                    'mtc_number' => $stock->mtc_number,
                    'is_interchangeable' => (bool) $part->is_interchangeable,
                    'reserved_for_assembly_id' => null,
                    'status' => 'available',
                    'remarks' => $row['remarks'] ?? $allocation->remarks,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }

            $stock->qty_pcs_available = 0;
            $stock->weight_kg_available = 0;
            $stock->status = 'consumed';
            $stock->remarks = trim((string) ($stock->remarks ?? '') . "\nConsumed in Production V2 Cut Batch #{$cutBatch->id}");
            $stock->save();

            foreach ($data['batch_remnants'] ?? [] as $remnantRow) {
                $this->createCutBatchRemnant($project, $cutBatch, $stock, $remnantRow);
            }

            return $cutBatch;
        });

        return redirect()
            ->route('projects.production-v2.cut-batches.show', ['project' => $project->id, 'cutBatch' => $cutBatch->id])
            ->with('success', 'Production V2 cut batch created, stock consumed, and WIP generated.');
    }

    public function show(Project $project, ProductionCutBatch $cutBatch)
    {
        abort_unless((int) $cutBatch->project_id === (int) $project->id, 404);
        $cutBatch->load([
            'cuttingPlan',
            'motherStock',
            'storeIssue',
            'wipItems.partDefinition',
            'wipItems.uom',
            'remnants.remnantStock',
        ]);

        return view('production_v2.cut_batches.show', [
            'project' => $project,
            'cutBatch' => $cutBatch,
        ]);
    }

    protected function createStoreIssueForCutBatch(Project $project, array $data, StoreStockItem $stock, ProductionCutBatch $cutBatch): StoreIssue
    {
        $issue = new StoreIssue();
        $issue->issue_date = $data['cut_date'];
        $issue->project_id = $project->id;
        $issue->contractor_party_id = $data['contractor_party_id'] ?? null;
        $issue->status = 'posted';
        $issue->remarks = trim('Auto-created from Production V2 Cut Batch #' . $cutBatch->id . '. ' . ($data['remarks'] ?? ''));
        $issue->created_by = auth()->id();

        if (Schema::hasColumn('store_issues', 'issue_purpose')) {
            $issue->issue_purpose = 'general';
        }

        if (Schema::hasColumn('store_issues', 'machine_id')) {
            $issue->machine_id = $data['machine_id'] ?? null;
        }

        if (Schema::hasColumn('store_issues', 'issued_to_user_id')) {
            $issue->issued_to_user_id = $data['operator_id'] ?? null;
        }

        if (Schema::hasColumn('store_issues', 'contractor_person_name')) {
            $issue->contractor_person_name = null;
        }

        if (Schema::hasColumn('store_issues', 'accounting_status')) {
            $issue->accounting_status = $stock->is_client_material ? 'not_required' : 'pending';
        }

        if (Schema::hasColumn('store_issues', 'accounting_posted_by') && $stock->is_client_material) {
            $issue->accounting_posted_by = auth()->id();
        }

        if (Schema::hasColumn('store_issues', 'accounting_posted_at') && $stock->is_client_material) {
            $issue->accounting_posted_at = now();
        }

        $issue->save();
        $issue->issue_number = app(DocumentNumberService::class)->storeIssue($issue);
        $issue->save();

        $uomId = $this->resolveItemUomId($stock->item_id);
        if (! $uomId) {
            throw new \RuntimeException('Source stock item is missing UOM. Cannot create store issue line for cut batch.');
        }

        $issueLine = new StoreIssueLine();
        $issueLine->store_issue_id = $issue->id;
        $issueLine->store_stock_item_id = $stock->id;
        $issueLine->item_id = $stock->item_id;
        $issueLine->uom_id = $uomId;
        $issueLine->issued_qty_pcs = max(1, (int) ($stock->qty_pcs_available ?? 1));
        $issueLine->issued_weight_kg = $stock->weight_kg_available ?: $stock->weight_kg_total;
        $issueLine->remarks = 'Auto issue for Production V2 Cut Batch #' . $cutBatch->id;

        if (Schema::hasColumn('store_issue_lines', 'machine_id')) {
            $issueLine->machine_id = $data['machine_id'] ?? null;
        }

        $issueLine->save();

        return $issue;
    }

    protected function assertNonInterchangeablePartScope(Project $project, $part): void
    {
        if ((bool) $part->is_interchangeable) {
            return;
        }

        $assemblyIds = ProductionAssemblyPartRequirement::query()
            ->where('part_definition_id', $part->id)
            ->whereHas('assembly', fn ($query) => $query->where('project_id', $project->id))
            ->pluck('assembly_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($assemblyIds->count() <= 1) {
            return;
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'rows' => [
                'Part ' . ($part->part_code ?: ('#' . $part->id)) . ' is marked non-interchangeable but is required by multiple assemblies. Split it into assembly-specific part definitions or mark it interchangeable.',
            ],
        ]);
    }

    protected function shouldCreateStoreIssueForCutBatch(StoreStockItem $stock): bool
    {
        return ! $this->isRawMaterialStock($stock);
    }

    protected function resolvePlanMaterialProfile(ProductionCuttingPlan $plan): array
    {
        $materialCategory = $plan->allocations
            ->pluck('partDefinition.material_category')
            ->filter()
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->first();

        $grade = $plan->grade
            ?: $plan->allocations->pluck('partDefinition.material_grade')->filter()->first();

        $thickness = $plan->thickness_mm
            ?: $plan->allocations
                ->map(fn ($allocation) => $allocation->thickness_mm ?: $allocation->partDefinition?->thickness_mm)
                ->filter()
                ->first();

        return [
            'material_category' => $materialCategory,
            'grade' => $grade,
            'thickness_mm' => $thickness ? (float) $thickness : null,
            'source_mode' => $plan->source_mode,
        ];
    }

    protected function validateSourceStockAgainstPlan(StoreStockItem $stock, array $planProfile): ?string
    {
        $planCategory = strtolower(trim((string) ($planProfile['material_category'] ?? '')));
        $stockCategory = strtolower(trim((string) ($stock->material_category ?? '')));

        if ($planCategory !== '' && $stockCategory !== '' && $planCategory !== $stockCategory) {
            return 'Selected source stock does not match the plan material category.';
        }

        $planGrade = strtoupper(trim((string) ($planProfile['grade'] ?? '')));
        $stockGrade = strtoupper(trim((string) ($stock->grade ?? '')));
        if ($planGrade !== '' && $stockGrade !== '' && $planGrade !== $stockGrade) {
            return 'Selected source stock does not match the plan material grade.';
        }

        $planThickness = isset($planProfile['thickness_mm']) ? (float) $planProfile['thickness_mm'] : null;
        $stockThickness = isset($stock->thickness_mm) ? (float) $stock->thickness_mm : null;
        if ($planThickness !== null && $stockThickness !== null && abs($planThickness - $stockThickness) > 0.0001) {
            return 'Selected source stock does not match the plan thickness.';
        }

        if (($planProfile['source_mode'] ?? null) === 'fresh_plate' && (bool) $stock->is_remnant) {
            return 'This plan is in fresh stock mode, so remnant stock cannot be selected.';
        }

        if (($planProfile['source_mode'] ?? null) === 'remnant' && ! (bool) $stock->is_remnant) {
            return 'This plan is in remnant mode, so only remnant stock can be selected.';
        }

        return null;
    }

    protected function planNeedsCutting(ProductionCuttingPlan $plan): bool
    {
        $plannedByPart = $plan->relationLoaded('allocations')
            ? $plan->allocations
            : $plan->allocations()->get();

        $plannedQtyByPart = $plannedByPart
            ->groupBy('part_definition_id')
            ->map(fn ($rows) => (float) $rows->sum('planned_qty'));

        if ($plannedQtyByPart->isEmpty()) {
            return false;
        }

        $producedQtyByPart = DB::table('production_v2_wip_items as w')
            ->join('production_v2_cut_batches as b', 'b.id', '=', 'w.cut_batch_id')
            ->where('b.cutting_plan_id', $plan->id)
            ->selectRaw('w.part_definition_id, SUM(w.qty) as total_qty')
            ->groupBy('w.part_definition_id')
            ->pluck('total_qty', 'part_definition_id');

        foreach ($plannedQtyByPart as $partId => $plannedQty) {
            $producedQty = (float) ($producedQtyByPart[$partId] ?? 0);
            if ($producedQty + 0.0001 < $plannedQty) {
                return true;
            }
        }

        return false;
    }

    protected function isRawMaterialStock(StoreStockItem $stock): bool
    {
        $materialCategory = strtolower(trim((string) ($stock->material_category ?? '')));
        if (in_array($materialCategory, ['steel_plate', 'steel_section'], true)) {
            return true;
        }

        $materialTypeCode = DB::table('items as i')
            ->leftJoin('material_types as mt', 'mt.id', '=', 'i.material_type_id')
            ->where('i.id', $stock->item_id)
            ->value('mt.code');

        return strtoupper(trim((string) $materialTypeCode)) === 'RAW';
    }

    protected function createCutBatchRemnant(Project $project, ProductionCutBatch $cutBatch, StoreStockItem $stock, array $row): void
    {
        $remW = $row['width_mm'] ?? null;
        $remL = $row['length_mm'] ?? null;
        $remWt = $row['weight_kg'] ?? null;

        if (! ($remW || $remL || $remWt)) {
            return;
        }

        $isUsable = array_key_exists('is_usable', $row) ? (bool) $row['is_usable'] : true;
        $remnantStockId = null;

        if ($isUsable) {
            $remnantPayload = [
                'material_receipt_line_id' => null,
                'item_id' => $stock->item_id,
                'project_id' => $stock->project_id,
                'is_client_material' => (bool) $stock->is_client_material,
                'is_remnant' => true,
                'mother_stock_item_id' => $stock->id,
                'material_category' => $stock->material_category,
                'thickness_mm' => $stock->thickness_mm,
                'width_mm' => $remW ?: $stock->width_mm,
                'length_mm' => $remL ?: $stock->length_mm,
                'section_profile' => $stock->section_profile,
                'grade' => $stock->grade,
                'plate_number' => $stock->plate_number,
                'heat_number' => $stock->heat_number,
                'mtc_number' => $stock->mtc_number,
                'qty_pcs_total' => 1,
                'qty_pcs_available' => 1,
                'weight_kg_total' => $remWt,
                'weight_kg_available' => $remWt,
                'source_type' => 'production_remnant',
                'source_reference' => 'CB#' . $cutBatch->id,
                'status' => 'available',
                'location' => $stock->location,
                'remarks' => 'Usable remnant generated from Production V2 Cut Batch #' . $cutBatch->id,
            ];

            if (Schema::hasColumn('store_stock_items', 'client_party_id')) {
                $remnantPayload['client_party_id'] = $stock->client_party_id;
            }

            $newRemnantStock = StoreStockItem::query()->create($remnantPayload);
            $remnantStockId = $newRemnantStock->id;
        }

        ProductionRemnant::query()->create([
            'project_id' => $project->id,
            'production_v2_cut_batch_id' => $cutBatch->id,
            'mother_stock_item_id' => $stock->id,
            'remnant_stock_item_id' => $remnantStockId,
            'thickness_mm' => $stock->thickness_mm,
            'width_mm' => $remW,
            'length_mm' => $remL,
            'weight_kg' => $remWt,
            'is_usable' => $isUsable,
            'status' => $isUsable ? 'available' : 'scrap',
            'remarks' => $row['remarks'] ?? null,
        ]);
    }

    protected function resolveItemUomId(int $itemId): ?int
    {
        return (int) DB::table('items')->where('id', $itemId)->value('uom_id') ?: null;
    }
}
