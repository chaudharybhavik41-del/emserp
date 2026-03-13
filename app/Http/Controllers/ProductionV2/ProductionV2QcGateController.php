<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssemblyRouteStep;
use App\Models\ProductionV2\ProductionPartRouteStep;
use App\Models\ProductionV2\ProductionQcGateEvent;
use App\Models\User;
use App\Support\ProductionV2\QcGateCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionV2QcGateController extends Controller
{
    public function __construct(private QcGateCatalog $qcGateCatalog)
    {
        $this->middleware('auth');
        $this->middleware('permission:production.qc.perform|production.dpr.view')->only(['index', 'show']);
        $this->middleware('permission:production.qc.perform')->only(['create', 'store']);
    }

    public function index(Project $project)
    {
        $rows = ProductionQcGateEvent::query()
            ->where('project_id', $project->id)
            ->with(['operationMaster', 'assembly', 'partDefinition', 'checkedBy'])
            ->latest('gate_date')
            ->latest('id')
            ->paginate(25);

        return view('production_v2.qc_gates.index', [
            'project' => $project,
            'rows' => $rows,
            'summary' => [
                'total' => ProductionQcGateEvent::query()->where('project_id', $project->id)->count(),
                'passed' => ProductionQcGateEvent::query()->where('project_id', $project->id)->where('result', 'passed')->count(),
                'hold' => ProductionQcGateEvent::query()->where('project_id', $project->id)->whereIn('result', ['hold', 'reoffer'])->count(),
                'failed' => ProductionQcGateEvent::query()->where('project_id', $project->id)->where('result', 'failed')->count(),
            ],
        ]);
    }

    public function create(Request $request, Project $project)
    {
        [$partRouteStep, $assemblyRouteStep] = $this->resolveRouteStepContext($project, $request);
        $targetLabel = $assemblyRouteStep?->assembly?->assembly_code ?: $partRouteStep?->partDefinition?->part_code;

        $recentRows = ProductionQcGateEvent::query()
            ->where('project_id', $project->id)
            ->when($assemblyRouteStep, fn ($query) => $query->where('assembly_route_step_id', $assemblyRouteStep->id))
            ->when($partRouteStep, fn ($query) => $query->where('part_route_step_id', $partRouteStep->id))
            ->with(['checkedBy'])
            ->latest('gate_date')
            ->latest('id')
            ->limit(8)
            ->get();

        return view('production_v2.qc_gates.form', [
            'project' => $project,
            'partRouteStep' => $partRouteStep,
            'assemblyRouteStep' => $assemblyRouteStep,
            'targetLabel' => $targetLabel,
            'recentRows' => $recentRows,
            'users' => User::query()->orderBy('name')->limit(300)->get(['id', 'name']),
            'gateModes' => $this->qcGateCatalog->modeOptions(),
            'gateTypes' => $this->qcGateCatalog->typeOptions(),
            'gateResults' => $this->qcGateCatalog->resultOptions(),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'part_route_step_id' => ['nullable', 'integer', 'exists:production_v2_part_route_steps,id'],
            'assembly_route_step_id' => ['nullable', 'integer', 'exists:production_v2_assembly_route_steps,id'],
            'gate_date' => ['required', 'date'],
            'gate_mode' => ['required', Rule::in(array_keys($this->qcGateCatalog->modeOptions()))],
            'gate_type' => ['required', Rule::in(array_keys($this->qcGateCatalog->typeOptions()))],
            'result' => ['required', Rule::in(array_keys($this->qcGateCatalog->resultOptions()))],
            'checked_by' => ['nullable', 'integer', 'exists:users,id'],
            'inspector_agency' => ['nullable', 'string', 'max:160'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string'],
        ]);

        $hasPartStep = ! empty($data['part_route_step_id']);
        $hasAssemblyStep = ! empty($data['assembly_route_step_id']);
        abort_unless($hasPartStep xor $hasAssemblyStep, 422, 'Select exactly one route step.');

        $partRouteStep = null;
        $assemblyRouteStep = null;

        if ($hasPartStep) {
            $partRouteStep = ProductionPartRouteStep::query()
                ->with('partDefinition')
                ->where('id', (int) $data['part_route_step_id'])
                ->firstOrFail();
            abort_unless((int) $partRouteStep->partDefinition->project_id === (int) $project->id, 404);
            abort_unless((bool) $partRouteStep->qc_gate_required, 422, 'Selected route step does not require a QC gate.');
        } else {
            $assemblyRouteStep = ProductionAssemblyRouteStep::query()
                ->with('assembly')
                ->where('id', (int) $data['assembly_route_step_id'])
                ->firstOrFail();
            abort_unless((int) $assemblyRouteStep->assembly->project_id === (int) $project->id, 404);
            abort_unless((bool) $assemblyRouteStep->qc_gate_required, 422, 'Selected route step does not require a QC gate.');
        }

        $qcGate = ProductionQcGateEvent::query()->create([
            'project_id' => $project->id,
            'operation_master_id' => $assemblyRouteStep?->operation_master_id ?: $partRouteStep->operation_master_id,
            'part_route_step_id' => $partRouteStep?->id,
            'assembly_route_step_id' => $assemblyRouteStep?->id,
            'part_definition_id' => $partRouteStep?->part_definition_id,
            'assembly_id' => $assemblyRouteStep?->assembly_id,
            'gate_date' => $data['gate_date'],
            'gate_mode' => $data['gate_mode'],
            'gate_type' => $data['gate_type'],
            'result' => $data['result'],
            'checked_by' => $data['checked_by'] ?? null,
            'inspector_agency' => $data['inspector_agency'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('projects.production-v2.qc-gates.show', ['project' => $project->id, 'qcGate' => $qcGate->id])
            ->with('success', 'Production V2 QC gate recorded.');
    }

    public function show(Project $project, ProductionQcGateEvent $qcGate)
    {
        abort_unless((int) $qcGate->project_id === (int) $project->id, 404);
        $qcGate->load(['operationMaster', 'partDefinition', 'assembly', 'partRouteStep', 'assemblyRouteStep', 'checkedBy']);

        return view('production_v2.qc_gates.show', [
            'project' => $project,
            'qcGate' => $qcGate,
        ]);
    }

    private function resolveRouteStepContext(Project $project, Request $request): array
    {
        $partRouteStep = null;
        $assemblyRouteStep = null;

        $partRouteStepId = (int) $request->integer('part_route_step_id');
        if ($partRouteStepId > 0) {
            $partRouteStep = ProductionPartRouteStep::query()
                ->with(['partDefinition', 'operationMaster'])
                ->findOrFail($partRouteStepId);
            abort_unless((int) $partRouteStep->partDefinition->project_id === (int) $project->id, 404);
        }

        $assemblyRouteStepId = (int) $request->integer('assembly_route_step_id');
        if ($assemblyRouteStepId > 0) {
            $assemblyRouteStep = ProductionAssemblyRouteStep::query()
                ->with(['assembly', 'operationMaster'])
                ->findOrFail($assemblyRouteStepId);
            abort_unless((int) $assemblyRouteStep->assembly->project_id === (int) $project->id, 404);
        }

        return [$partRouteStep, $assemblyRouteStep];
    }
}
