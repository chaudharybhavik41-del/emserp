<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Party;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionAssemblyRouteStep;
use App\Models\ProductionV2\ProductionOperationEvent;
use App\Models\ProductionV2\ProductionOperationMaster;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Models\ProductionV2\ProductionPartRouteStep;
use App\Models\ProductionV2\ProductionWipItem;
use App\Models\User;
use App\Support\ProductionV2\DailyDprManager;
use App\Support\ProductionV2\OperationCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionV2OperationEventController extends Controller
{
    public function __construct(
        private DailyDprManager $dprManager,
        private OperationCatalog $operationCatalog
    )
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dpr.view|production.qc.perform')->only(['index', 'show']);
        $this->middleware('permission:production.dpr.create|production.qc.perform')->only(['create', 'store']);
    }

    public function index(Project $project)
    {
        $rows = ProductionOperationEvent::query()
            ->where('project_id', $project->id)
            ->with(['operationMaster', 'partDefinition', 'assembly', 'worker', 'machine'])
            ->latest('operation_date')
            ->latest('id')
            ->paginate(25);

        $summary = ProductionOperationEvent::query()
            ->where('project_id', $project->id)
            ->selectRaw('operation_master_id, COUNT(*) as total_rows, COALESCE(SUM(qty), 0) as total_qty')
            ->groupBy('operation_master_id')
            ->with('operationMaster:id,name')
            ->get()
            ->sortByDesc('total_rows')
            ->values();

        return view('production_v2.operation_events.index', [
            'project' => $project,
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    public function create(Request $request, Project $project)
    {
        $operation = $this->resolveOperation($project, $request);
        abort_unless($operation !== null, 404);

        $activityKey = 'op:' . $operation->code;
        $selectedDpr = $this->dprManager->findForActivity($project, (int) $request->integer('dpr_id'), $activityKey);

        $parts = collect();
        $assemblies = collect();
        $wipItems = collect();

        if ($operation->applies_to === 'part') {
            $parts = ProductionPartDefinition::query()
                ->where('project_id', $project->id)
                ->whereHas('routeSteps', fn ($query) => $query->where('operation_master_id', $operation->id))
                ->whereNotIn('status', ['superseded', 'obsolete'])
                ->orderBy('part_code')
                ->get();

            $selectedPartId = (int) $request->integer('part_definition_id');
            if ($selectedPartId > 0) {
                $wipItems = ProductionWipItem::query()
                    ->where('project_id', $project->id)
                    ->where('part_definition_id', $selectedPartId)
                    ->where('status', 'available')
                    ->orderBy('piece_no')
                    ->limit(200)
                    ->get();
            }
        } else {
            $assemblies = ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->whereHas('routeSteps', fn ($query) => $query->where('operation_master_id', $operation->id))
                ->where('status', 'released')
                ->orderBy('assembly_code')
                ->get();
        }

        $recentRows = ProductionOperationEvent::query()
            ->where('project_id', $project->id)
            ->where('operation_master_id', $operation->id)
            ->with(['partDefinition', 'assembly', 'worker'])
            ->latest('operation_date')
            ->latest('id')
            ->limit(8)
            ->get();

        return view('production_v2.operation_events.form', [
            'project' => $project,
            'operation' => $operation,
            'selectedDpr' => $selectedDpr,
            'parts' => $parts,
            'assemblies' => $assemblies,
            'wipItems' => $wipItems,
            'recentRows' => $recentRows,
            'contractors' => Party::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->limit(300)->get(['id', 'name']),
            'machines' => Machine::query()->orderBy('name')->limit(200)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'operation_master_id' => ['required', 'integer', 'exists:production_v2_operation_masters,id'],
            'dpr_id' => ['nullable', 'integer', 'exists:production_dprs,id'],
            'operation_date' => ['required', 'date'],
            'shift' => ['nullable', 'string', 'max:30'],
            'part_definition_id' => ['nullable', 'integer', 'exists:production_v2_part_definitions,id'],
            'assembly_id' => ['nullable', 'integer', 'exists:production_v2_assemblies,id'],
            'wip_item_id' => ['nullable', 'integer', 'exists:production_v2_wip_items,id'],
            'qty' => ['required', 'numeric', 'min:0.001'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'worker_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'contractor_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'result' => ['nullable', 'string', 'max:60'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'approved', 'hold'])],
        ]);

        $operation = ProductionOperationMaster::query()->findOrFail((int) $data['operation_master_id']);
        abort_unless($operation->is_active, 422);

        $partRouteStepId = null;
        $assemblyRouteStepId = null;
        $partId = null;
        $assemblyId = null;
        $uomId = null;

        if ($operation->applies_to === 'part') {
            $partId = (int) ($data['part_definition_id'] ?? 0);
            abort_if($partId <= 0, 422, 'Part is required for this operation.');

            $part = ProductionPartDefinition::query()
                ->where('project_id', $project->id)
                ->where('id', $partId)
                ->with('uom')
                ->firstOrFail();

            $routeStep = ProductionPartRouteStep::query()
                ->where('part_definition_id', $partId)
                ->where('operation_master_id', $operation->id)
                ->firstOrFail();

            $partRouteStepId = $routeStep->id;
            $uomId = $part->uom_id;
        } else {
            $assemblyId = (int) ($data['assembly_id'] ?? 0);
            abort_if($assemblyId <= 0, 422, 'Assembly is required for this operation.');

            $assembly = ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->where('id', $assemblyId)
                ->firstOrFail();

            $routeStep = ProductionAssemblyRouteStep::query()
                ->where('assembly_id', $assemblyId)
                ->where('operation_master_id', $operation->id)
                ->firstOrFail();

            $assemblyRouteStepId = $routeStep->id;
        }

        $selectedDpr = null;
        if (! empty($data['dpr_id'])) {
            $selectedDpr = $this->dprManager->findForActivity($project, (int) $data['dpr_id'], 'op:' . $operation->code);
            if (! $selectedDpr) {
                return back()->withErrors(['dpr_id' => 'Selected DPR does not match this operation.'])->withInput();
            }
        }

        $event = ProductionOperationEvent::query()->create([
            'project_id' => $project->id,
            'operation_master_id' => $operation->id,
            'part_route_step_id' => $partRouteStepId,
            'assembly_route_step_id' => $assemblyRouteStepId,
            'part_definition_id' => $partId ?: null,
            'assembly_id' => $assemblyId ?: null,
            'wip_item_id' => ! empty($data['wip_item_id']) ? (int) $data['wip_item_id'] : null,
            'dpr_id' => $selectedDpr?->id,
            'operation_date' => $data['operation_date'],
            'shift' => $data['shift'] ?? null,
            'qty' => $data['qty'],
            'uom_id' => $uomId,
            'machine_id' => $data['machine_id'] ?? null,
            'worker_user_id' => $data['worker_user_id'] ?? null,
            'contractor_party_id' => $data['contractor_party_id'] ?? null,
            'result' => $data['result'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'status' => $data['status'],
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('projects.production-v2.operation-events.show', ['project' => $project->id, 'operationEvent' => $event->id])
            ->with('success', 'Production V2 operation event recorded.');
    }

    public function show(Project $project, ProductionOperationEvent $operationEvent)
    {
        abort_unless((int) $operationEvent->project_id === (int) $project->id, 404);
        $operationEvent->load(['operationMaster', 'partDefinition', 'assembly', 'wipItem', 'worker', 'machine', 'contractor', 'dpr']);

        return view('production_v2.operation_events.show', [
            'project' => $project,
            'operationEvent' => $operationEvent,
        ]);
    }

    private function resolveOperation(Project $project, Request $request): ?ProductionOperationMaster
    {
        $operationId = (int) $request->integer('operation_master_id');
        if ($operationId > 0) {
            return ProductionOperationMaster::query()->where('id', $operationId)->where('is_active', true)->first();
        }

        $operationCode = trim((string) $request->query('operation_code', ''));
        if ($operationCode !== '') {
            return $this->operationCatalog->findByCode($operationCode);
        }

        $activityKey = trim((string) $request->query('activity_key', ''));
        if (str_starts_with($activityKey, 'op:')) {
            return $this->operationCatalog->findByCode(substr($activityKey, 3));
        }

        return null;
    }
}
