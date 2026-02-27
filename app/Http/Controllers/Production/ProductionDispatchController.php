<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Project;
use App\Models\Production\ProductionDispatch;
use App\Models\Production\ProductionDispatchLine;
use App\Models\Production\ProductionPlan;
use App\Models\Production\ProductionPlanItem;
use App\Services\Production\ProductionAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionDispatchController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.dispatch.view')->only(['index', 'show']);
        $this->middleware('permission:production.dispatch.create')->only(['create', 'store']);
        $this->middleware('permission:production.dispatch.update')->only(['finalize', 'cancel']);
    }

    public function index(Project $project)
    {
        $dispatches = ProductionDispatch::query()
            ->where('project_id', $project->id)
            ->with(['client', 'plan'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('projects.production_dispatches.index', [
            'project' => $project,
            'dispatches' => $dispatches,
        ]);
    }

    public function create(Request $request, Project $project)
    {
        $plans = ProductionPlan::query()
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->get();

        $clients = Party::query()
            ->where('is_client', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedPlan = null;
        $items = collect();
        $dispatchedQtyMap = [];
        $unitBillableWeightMap = [];

        $selectedPlanId = $request->integer('production_plan_id');
        if ($selectedPlanId) {
            $selectedPlan = ProductionPlan::query()
                ->where('id', $selectedPlanId)
                ->where('project_id', $project->id)
                ->with(['items.uom'])
                ->first();

            if ($selectedPlan) {
                // Fetch dispatched quantity so far (exclude cancelled dispatches)
                $dispatchedQtyMap = ProductionDispatchLine::query()
                    ->join('production_dispatches as pd', 'pd.id', '=', 'production_dispatch_lines.production_dispatch_id')
                    ->where('pd.project_id', $project->id)
                    ->whereIn('pd.status', ['draft', 'finalized'])
                    ->whereNotNull('production_dispatch_lines.production_plan_item_id')
                    ->whereIn('production_dispatch_lines.production_plan_item_id', $selectedPlan->items->pluck('id')->all())
                    ->groupBy('production_dispatch_lines.production_plan_item_id')
                    ->selectRaw('production_dispatch_lines.production_plan_item_id as plan_item_id, SUM(production_dispatch_lines.qty) as qty_sum')
                    ->pluck('qty_sum', 'plan_item_id')
                    ->map(fn ($v) => (float) $v)
                    ->all();

                // Billable (dispatch) unit weight map from BOM (if linked)
                $bom = $selectedPlan->bom;
                $bomItemsById = [];
                if ($bom) {
                    $bom->load(['items']);
                    $bomItemsById = $bom->items->keyBy('id')->all();
                    $assemblyWeights = $bom->assembly_weights; // this is billable-aware weight calculation
                    $unitBillableWeightMap = $assemblyWeights;
                }

                // Only show assemblies by default (components for client dispatch)
                $items = $selectedPlan->items
                    ->where('item_type', 'assembly')
                    ->where('status', 'done')
                    ->sortBy('sequence_no')
                    ->values()
                    ->filter(function (ProductionPlanItem $pi) use ($bomItemsById) {
                        // If BOM exists and item is present, apply effective billable (cascades from parents)
                        if (! empty($bomItemsById) && $pi->bom_item_id && isset($bomItemsById[$pi->bom_item_id])) {
                            return (bool) $bomItemsById[$pi->bom_item_id]->effectiveBillable($bomItemsById);
                        }

                        // Fallback: allow
                        return true;
                    });
            }
        }

        return view('projects.production_dispatches.create', [
            'project' => $project,
            'plans' => $plans,
            'clients' => $clients,
            'selectedPlan' => $selectedPlan,
            'items' => $items,
            'dispatchedQtyMap' => $dispatchedQtyMap,
            'unitBillableWeightMap' => $unitBillableWeightMap,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'production_plan_id' => ['required', 'integer'],
            'client_party_id' => ['nullable', 'integer'],
            'dispatch_date' => ['required', 'date'],
            'vehicle_number' => ['nullable', 'string', 'max:50'],
            'lr_number' => ['nullable', 'string', 'max:80'],
            'transporter_name' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string'],
            'lines' => ['array'],
        ]);

        /** @var \App\Models\Production\ProductionPlan|null $plan */
        $plan = ProductionPlan::query()
            ->where('id', (int) $data['production_plan_id'])
            ->where('project_id', $project->id)
            ->with(['items.uom', 'bom'])
            ->first();

        if (! $plan) {
            return back()->withInput()->with('error', 'Invalid production plan selected.');
        }

        $clientPartyId = $data['client_party_id'] ?? $project->client_party_id;
        if ($clientPartyId) {
            $existsClient = Party::query()->where('id', $clientPartyId)->exists();
            if (! $existsClient) {
                return back()->withInput()->with('error', 'Invalid client selected.');
            }
        }

        $linesInput = $data['lines'] ?? [];

        // Normalize selected lines
        $selected = [];
        foreach ($linesInput as $planItemId => $row) {
            if (! is_array($row)) {
                continue;
            }

            $qty = (float) ($row['qty'] ?? 0);
            $remarks = isset($row['remarks']) ? (string) $row['remarks'] : null;

            if ($qty > 0) {
                $selected[(int) $planItemId] = [
                    'qty' => $qty,
                    'remarks' => $remarks,
                ];
            }
        }

        if (empty($selected)) {
            return back()->withInput()->with('error', 'Please enter dispatch quantity for at least one component.');
        }

        // Load plan items selected
        $planItems = ProductionPlanItem::query()
            ->where('production_plan_id', $plan->id)
            ->whereIn('id', array_keys($selected))
            ->with(['uom', 'bomItem'])
            ->get()
            ->keyBy('id');

        if ($planItems->isEmpty()) {
            return back()->withInput()->with('error', 'Selected components not found in the plan.');
        }

        // Build dispatched qty map (exclude cancelled)
        $alreadyDispatchedMap = ProductionDispatchLine::query()
            ->join('production_dispatches as pd', 'pd.id', '=', 'production_dispatch_lines.production_dispatch_id')
            ->where('pd.project_id', $project->id)
            ->whereIn('pd.status', ['draft', 'finalized'])
            ->whereIn('production_dispatch_lines.production_plan_item_id', $planItems->keys()->all())
            ->groupBy('production_dispatch_lines.production_plan_item_id')
            ->selectRaw('production_dispatch_lines.production_plan_item_id as plan_item_id, SUM(production_dispatch_lines.qty) as qty_sum')
            ->pluck('qty_sum', 'plan_item_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        // Billable (dispatch) unit weight map from BOM
        $unitBillableWeightMap = [];
        $bomItemsById = [];
        if ($plan->bom) {
            $plan->bom->load(['items']);
            $bomItemsById = $plan->bom->items->keyBy('id')->all();
            $unitBillableWeightMap = $plan->bom->assembly_weights;
        }

        // Validate qty <= remaining and effective billable
        foreach ($selected as $planItemId => $sel) {
            /** @var \App\Models\Production\ProductionPlanItem|null $pi */
            $pi = $planItems->get($planItemId);
            if (! $pi) {
                return back()->withInput()->with('error', 'Some selected components were not found in the plan.');
            }

            // Effective billable check (cascades from BOM parents)
            if (! empty($bomItemsById) && $pi->bom_item_id && isset($bomItemsById[$pi->bom_item_id])) {
                if (! $bomItemsById[$pi->bom_item_id]->effectiveBillable($bomItemsById)) {
                    return back()->withInput()->with('error', "Component {$pi->assembly_mark} is not marked billable for dispatch (check BOM billable flags).");
                }
            }

            $planned = (float) ($pi->planned_qty ?? 0);
            $done = (float) ($alreadyDispatchedMap[$planItemId] ?? 0);
            $remaining = max(0, $planned - $done);
            if ($sel['qty'] - $remaining > 0.00001) {
                return back()->withInput()->with('error', "Dispatch qty for {$pi->assembly_mark} exceeds remaining qty ({$remaining}).");
            }
        }

        $dispatch = null;

        DB::transaction(function () use ($project, $plan, $clientPartyId, $data, $selected, $planItems, $unitBillableWeightMap, &$dispatch) {
            $dispatch = ProductionDispatch::create([
                'project_id' => $project->id,
                'production_plan_id' => $plan->id,
                'client_party_id' => $clientPartyId,
                'dispatch_number' => ProductionDispatch::nextDispatchNumber($project),
                'dispatch_date' => $data['dispatch_date'],
                'status' => 'draft',
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'lr_number' => $data['lr_number'] ?? null,
                'transporter_name' => $data['transporter_name'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $totalQty = 0.0;
            $totalWeight = 0.0;

            foreach ($selected as $planItemId => $sel) {
                /** @var \App\Models\Production\ProductionPlanItem $pi */
                $pi = $planItems->get($planItemId);

                $qty = (float) $sel['qty'];
                $plannedQty = (float) ($pi->planned_qty ?? 0);

                // Unit billable weight: prefer BOM assembly weight map; fallback to planned_weight/qty
                $unitW = 0.0;
                if ($pi->bom_item_id && isset($unitBillableWeightMap[$pi->bom_item_id])) {
                    $unitW = (float) $unitBillableWeightMap[$pi->bom_item_id];
                }

                if ($unitW <= 0 && $plannedQty > 0) {
                    $unitW = (float) ($pi->planned_weight_kg ?? 0) / $plannedQty;
                }

                $weight = $unitW * $qty;

                ProductionDispatchLine::create([
                    'production_dispatch_id' => $dispatch->id,
                    'production_plan_item_id' => $pi->id,
                    'qty' => $qty,
                    'uom_id' => $pi->uom_id,
                    'weight_kg' => $weight,
                    'remarks' => $sel['remarks'] ?? null,
                    'source_meta' => [
                        'item_code' => $pi->item_code,
                        'assembly_mark' => $pi->assembly_mark,
                        'assembly_type' => $pi->assembly_type,
                        'description' => $pi->description,
                        'planned_qty' => $pi->planned_qty,
                        'planned_weight_kg' => $pi->planned_weight_kg,
                        'unit_weight_kg' => $unitW,
                        'bom_item_id' => $pi->bom_item_id,
                    ],
                ]);

                $totalQty += $qty;
                $totalWeight += $weight;
            }

            $dispatch->total_qty = $totalQty;
            $dispatch->total_weight_kg = $totalWeight;
            $dispatch->save();
        });

        if ($dispatch) {
            ProductionAudit::log(
                $project->id,
                'dispatch.create',
                'ProductionDispatch',
                $dispatch->id,
                'Dispatch created',
                ['dispatch_number' => $dispatch->dispatch_number]
            );
        }

        return redirect()
            ->route('projects.production-dispatches.show', [$project, $dispatch])
            ->with('success', 'Production dispatch created successfully (Draft).');
    }

    public function show(Project $project, ProductionDispatch $production_dispatch)
    {
        if ((int) $production_dispatch->project_id !== (int) $project->id) {
            abort(404);
        }

        $production_dispatch->load([
            'client',
            'plan',
            'finalizedBy',
            'lines.planItem',
            'lines.uom',
        ]);

        return view('projects.production_dispatches.show', [
            'project' => $project,
            'dispatch' => $production_dispatch,
        ]);
    }

    public function finalize(Project $project, ProductionDispatch $production_dispatch)
    {
        if ((int) $production_dispatch->project_id !== (int) $project->id) {
            abort(404);
        }

        if (! $production_dispatch->isDraft()) {
            return back()->with('error', 'Only draft dispatches can be finalized.');
        }

        $production_dispatch->status = 'finalized';
        $production_dispatch->finalized_by = auth()->id();
        $production_dispatch->finalized_at = now();
        $production_dispatch->updated_by = auth()->id();
        $production_dispatch->save();

        ProductionAudit::log(
            $project->id,
            'dispatch.finalize',
            'ProductionDispatch',
            $production_dispatch->id,
            'Dispatch finalized',
            ['dispatch_number' => $production_dispatch->dispatch_number]
        );

        return back()->with('success', 'Dispatch finalized and locked.');
    }

    public function cancel(Project $project, ProductionDispatch $production_dispatch)
    {
        if ((int) $production_dispatch->project_id !== (int) $project->id) {
            abort(404);
        }

        if ($production_dispatch->isCancelled()) {
            return back()->with('error', 'Dispatch is already cancelled.');
        }

        if ($production_dispatch->isFinalized()) {
            return back()->with('error', 'Finalized dispatch cannot be cancelled.');
        }

        $production_dispatch->status = 'cancelled';
        $production_dispatch->updated_by = auth()->id();
        $production_dispatch->save();

        ProductionAudit::log(
            $project->id,
            'dispatch.cancel',
            'ProductionDispatch',
            $production_dispatch->id,
            'Dispatch cancelled',
            ['dispatch_number' => $production_dispatch->dispatch_number]
        );

        return back()->with('success', 'Dispatch cancelled.');
    }
}
