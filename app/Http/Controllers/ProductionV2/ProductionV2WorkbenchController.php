<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionAssemblyPartRequirement;
use App\Models\ProductionV2\ProductionDispatch;
use App\Models\ProductionV2\ProductionInspectionEvent;
use App\Models\ProductionV2\ProductionOperationEvent;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Models\ProductionV2\ProductionReworkEvent;
use App\Models\ProductionV2\ProductionTrialAssembly;
use App\Models\ProductionV2\ProductionWeldingEvent;
use App\Models\ProductionV2\ProductionFitup;
use App\Models\ProductionV2\ProductionCutBatch;
use App\Models\ProductionV2\ProductionWipItem;
use App\Support\ProductionV2\DailyDprManager;
use App\Support\ProductionV2\RouteProgressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionV2WorkbenchController extends Controller
{
    public function __construct(
        private DailyDprManager $dprManager,
        private RouteProgressService $routeProgressService
    )
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.update|production.dpr.view|production.dpr.create|production.qc.perform')->only(['show']);
        $this->middleware('permission:production.plan.update')->only(['updateMode']);
    }

    public function show(Project $project)
    {
        $assembliesWithCounts = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->where('status', 'released')
            ->with(['routeTemplate', 'routeSteps.operationMaster'])
            ->withCount(['fitups', 'weldingEvents', 'inspectionEvents', 'reworkEvents'])
            ->orderBy('assembly_code')
            ->get();

        $summary = [
            'part_definitions' => ProductionPartDefinition::query()->where('project_id', $project->id)->where('status', 'released')->count(),
            'assemblies' => $assembliesWithCounts->count(),
            'requirements' => ProductionAssemblyPartRequirement::query()
                ->whereHas('assembly', fn ($query) => $query->where('project_id', $project->id)->where('status', 'released'))
                ->count(),
            'cut_batches' => ProductionCutBatch::query()->where('project_id', $project->id)->count(),
            'fitups' => ProductionFitup::query()->where('project_id', $project->id)->count(),
            'welding_events' => ProductionWeldingEvent::query()->where('project_id', $project->id)->count(),
            'inspection_events' => ProductionInspectionEvent::query()->where('project_id', $project->id)->count(),
            'rework_events' => ProductionReworkEvent::query()->where('project_id', $project->id)->count(),
            'trial_assemblies' => ProductionTrialAssembly::query()->where('project_id', $project->id)->count(),
            'operation_events' => ProductionOperationEvent::query()->where('project_id', $project->id)->count(),
            'dispatches' => ProductionDispatch::query()->where('project_id', $project->id)->count(),
            'dprs' => $this->dprManager->dprQuery($project)->count(),
        ];

        $availableWipByPart = ProductionWipItem::query()
            ->where('project_id', $project->id)
            ->where('status', 'available')
            ->select('part_definition_id', DB::raw('COALESCE(SUM(qty),0) as qty_total'))
            ->groupBy('part_definition_id')
            ->pluck('qty_total', 'part_definition_id');

        $shortageRows = ProductionAssemblyPartRequirement::query()
            ->whereHas('assembly', fn ($query) => $query->where('project_id', $project->id)->where('status', 'released'))
            ->with(['assembly', 'partDefinition', 'uom'])
            ->get()
            ->groupBy('part_definition_id')
            ->map(function ($requirements, $partDefinitionId) use ($availableWipByPart) {
                $requirements = $requirements->values();
                /** @var \App\Models\ProductionV2\ProductionAssemblyPartRequirement|null $sample */
                $sample = $requirements->first();
                $availableQty = (float) ($availableWipByPart[$partDefinitionId] ?? 0);
                $requiredQty = (float) $requirements->sum(fn ($row) => (float) $row->required_qty);
                $shortageQty = max(0, $requiredQty - $availableQty);

                return [
                    'part_definition' => $sample?->partDefinition,
                    'uom' => $sample?->uom,
                    'available_qty' => $availableQty,
                    'required_qty' => $requiredQty,
                    'shortage_qty' => $shortageQty,
                    'assemblies' => $requirements
                        ->map(fn ($row) => $row->assembly)
                        ->filter()
                        ->unique('id')
                        ->sortBy('assembly_code')
                        ->values(),
                ];
            })
            ->filter(fn (array $row) => $row['shortage_qty'] > 0.0001)
            ->sortByDesc('shortage_qty')
            ->values();

        $assemblyNextSteps = $assembliesWithCounts
            ->map(function (ProductionAssembly $assembly) {
                $next = $this->routeProgressService->nextAssemblyStep($assembly);
                if (! $next) {
                    return null;
                }

                return [
                    'assembly' => $assembly,
                    'step' => $next['step'],
                    'required_qty' => $next['required_qty'],
                    'completed_qty' => $next['completed_qty'],
                    'status' => $next['status'] ?? 'operation_pending',
                    'gate_event' => $next['gate_event'] ?? null,
                ];
            })
            ->filter()
            ->values();

        $partRouteRows = ProductionPartDefinition::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', ['superseded', 'obsolete'])
            ->whereHas('routeSteps')
            ->with(['routeTemplate', 'routeSteps'])
            ->orderBy('part_code')
            ->get()
            ->map(function (ProductionPartDefinition $part) {
                $next = $this->routeProgressService->nextPartStep($part);
                if (! $next) {
                    return null;
                }

                return [
                    'part' => $part,
                    'step' => $next['step'],
                    'required_qty' => $next['required_qty'],
                    'completed_qty' => $next['completed_qty'],
                    'status' => $next['status'] ?? 'operation_pending',
                    'gate_event' => $next['gate_event'] ?? null,
                ];
            })
            ->filter()
            ->values();

        $alreadyDispatchedMap = ProductionDispatch::query()
            ->where('project_id', $project->id)
            ->whereIn('status', ['draft', 'finalized'])
            ->join('production_v2_dispatch_lines', 'production_v2_dispatch_lines.dispatch_id', '=', 'production_v2_dispatches.id')
            ->groupBy('production_v2_dispatch_lines.assembly_id')
            ->selectRaw('production_v2_dispatch_lines.assembly_id as assembly_id, SUM(production_v2_dispatch_lines.qty) as qty_sum')
            ->pluck('qty_sum', 'assembly_id');

        $openReworkCounts = ProductionReworkEvent::query()
            ->where('project_id', $project->id)
            ->whereIn('final_result', ['pending', 'failed', 'reoffer', 'hold'])
            ->whereNotNull('assembly_id')
            ->groupBy('assembly_id')
            ->selectRaw('assembly_id, COUNT(*) as total_rows')
            ->pluck('total_rows', 'assembly_id');

        $dispatchReadyRows = $assembliesWithCounts
            ->map(function (ProductionAssembly $assembly) use ($alreadyDispatchedMap, $openReworkCounts) {
                if ($assembly->routeSteps->isEmpty()) {
                    return null;
                }

                $next = $this->routeProgressService->nextAssemblyStep($assembly);
                if ($next !== null) {
                    return null;
                }

                if ((int) ($openReworkCounts[$assembly->id] ?? 0) > 0) {
                    return null;
                }

                $alreadyDispatchedQty = (float) ($alreadyDispatchedMap[$assembly->id] ?? 0);
                $remainingQty = max(0.0, (float) $assembly->planned_qty - $alreadyDispatchedQty);
                if ($remainingQty <= 0.0001) {
                    return null;
                }

                return [
                    'assembly' => $assembly,
                    'remaining_qty' => $remainingQty,
                    'already_dispatched_qty' => $alreadyDispatchedQty,
                ];
            })
            ->filter()
            ->values();

        $exceptionSummary = [
            'shortages' => $shortageRows->count(),
            'missing_fitup' => $assemblyNextSteps->where('step.operation_code', 'fitup')->count(),
            'missing_welding' => $assemblyNextSteps->where('step.operation_code', 'welding')->count(),
            'pending_inspection' => $assemblyNextSteps->where('step.operation_code', 'inspection')->count(),
            'pending_qc_gates' => $assemblyNextSteps->where('status', 'qc_gate_pending')->count()
                + $partRouteRows->where('status', 'qc_gate_pending')->count(),
            'open_rework' => ProductionReworkEvent::query()
                ->where('project_id', $project->id)
                ->whereIn('final_result', ['pending', 'failed', 'reoffer', 'hold'])
                ->count(),
            'part_route_pending' => $partRouteRows->count(),
            'dispatch_ready' => $dispatchReadyRows->count(),
        ];

        $missingStageRows = $assemblyNextSteps->take(12);

        $openReworks = ProductionReworkEvent::query()
            ->where('project_id', $project->id)
            ->whereIn('final_result', ['pending', 'failed', 'reoffer', 'hold'])
            ->with(['assembly', 'sourceInspection'])
            ->latest('rework_date')
            ->latest('id')
            ->limit(8)
            ->get();

        $inspectionAttention = ProductionInspectionEvent::query()
            ->where('project_id', $project->id)
            ->whereIn('result', ['failed', 'reoffer', 'hold'])
            ->with(['assembly', 'weldingEvent', 'checkedBy'])
            ->latest('inspection_date')
            ->latest('id')
            ->limit(8)
            ->get();

        $latestParts = ProductionPartDefinition::query()
            ->where('project_id', $project->id)
            ->where('status', 'released')
            ->latest('id')
            ->limit(5)
            ->get();

        $latestAssemblies = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->where('status', 'released')
            ->withCount('requirements')
            ->latest('id')
            ->limit(5)
            ->get();

        $latestDprs = $this->dprManager->dprQuery($project)
            ->with(['activity', 'worker', 'machine'])
            ->latest('dpr_date')
            ->latest('id')
            ->limit(5)
            ->get();

        return view('production_v2.workbench', [
            'project' => $project,
            'summary' => $summary,
            'exceptionSummary' => $exceptionSummary,
            'shortageRows' => $shortageRows->take(8),
            'missingStageRows' => $missingStageRows,
            'partRouteRows' => $partRouteRows->take(8),
            'openReworks' => $openReworks,
            'inspectionAttention' => $inspectionAttention,
            'latestParts' => $latestParts,
            'latestAssemblies' => $latestAssemblies,
            'latestDprs' => $latestDprs,
            'dispatchReadyRows' => $dispatchReadyRows->take(8),
        ]);
    }

    public function updateMode(Request $request, Project $project)
    {
        $data = $request->validate([
            'production_mode' => ['required', Rule::in(['legacy_only', 'v2_enabled', 'legacy_to_v2_transition'])],
        ]);

        $project->production_mode = $data['production_mode'];
        $project->save();

        return redirect()
            ->route('production-v2.project', ['project' => $project->id])
            ->with('success', 'Project production mode updated.');
    }
}
