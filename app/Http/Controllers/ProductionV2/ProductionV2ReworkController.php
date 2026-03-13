<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionFitup;
use App\Models\ProductionV2\ProductionInspectionEvent;
use App\Models\ProductionV2\ProductionReworkEvent;
use App\Models\ProductionV2\ProductionWeldingEvent;
use App\Support\ProductionV2\DailyDprManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionV2ReworkController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dpr.view|production.qc.perform')->only(['index', 'show']);
        $this->middleware('permission:production.qc.perform|production.dpr.create')->only(['create', 'store']);
    }

    public function index(Project $project)
    {
        $rows = ProductionReworkEvent::query()
            ->where('project_id', $project->id)
            ->with(['assembly', 'sourceInspection.weldingEvent'])
            ->orderByDesc('id')
            ->paginate(25);

        return view('production_v2.rework_events.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }

    public function create(Request $request, Project $project)
    {
        $selectedDpr = app(DailyDprManager::class)->findForActivity($project, (int) $request->integer('dpr_id'), 'rework');
        $selectedInspectionId = (int) $request->integer('inspection_event_id');
        $selectedAssemblyId = (int) $request->integer('assembly_id');

        $candidateInspections = ProductionInspectionEvent::query()
            ->where('project_id', $project->id)
            ->whereIn('result', ['failed', 'reoffer', 'hold'])
            ->with(['assembly', 'weldingEvent'])
            ->orderByDesc('inspection_date')
            ->orderByDesc('id')
            ->get();

        $selectedInspection = $selectedInspectionId > 0
            ? ProductionInspectionEvent::query()
                ->where('project_id', $project->id)
                ->with(['assembly', 'weldingEvent.welder', 'checkedBy', 'reworkEvents'])
                ->find($selectedInspectionId)
            : null;

        if ($selectedInspection && $selectedAssemblyId <= 0) {
            $selectedAssemblyId = (int) $selectedInspection->assembly_id;
        }

        $selectedAssembly = $selectedAssemblyId > 0
            ? ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->withCount(['inspectionEvents', 'reworkEvents'])
                ->find($selectedAssemblyId)
            : null;

        return view('production_v2.rework_events.form', [
            'project' => $project,
            'candidateInspections' => $candidateInspections,
            'selectedInspection' => $selectedInspection,
            'selectedAssembly' => $selectedAssembly,
            'canCreateRework' => $selectedInspection ? $selectedInspection->reworkEvents->isEmpty() : false,
            'reasonCodes' => ['weld_defect', 'dimension', 'surface', 'alignment', 'fitup', 'material', 'other'],
            'resultOptions' => ['pending', 'passed', 'failed', 'reoffer', 'hold'],
            'selectedDpr' => $selectedDpr,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'rework_dpr_id' => ['nullable', 'integer', 'exists:production_dprs,id'],
            'assembly_id' => [
                'required',
                'integer',
                Rule::exists('production_v2_assemblies', 'id')->where('project_id', $project->id),
            ],
            'source_inspection_event_id' => ['nullable', 'integer', 'exists:production_v2_inspection_events,id'],
            'rework_date' => ['required', 'date'],
            'reason_code' => ['nullable', 'string', 'max:120'],
            'reason_description' => ['nullable', 'string'],
            'action_taken' => ['required', 'string'],
            'reoffer_date' => ['nullable', 'date', 'after_or_equal:rework_date'],
            'final_result' => ['required', Rule::in(['pending', 'passed', 'failed', 'reoffer', 'hold'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $selectedDpr = null;
        if (! empty($data['rework_dpr_id'])) {
            $selectedDpr = app(DailyDprManager::class)->findForActivity($project, (int) $data['rework_dpr_id'], 'rework');
            if (! $selectedDpr) {
                return back()->withInput()->withErrors(['rework_dpr_id' => 'Selected DPR is not valid for Production V2 rework.']);
            }
        }

        $assembly = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->findOrFail((int) $data['assembly_id']);

        $sourceInspection = null;
        if (! empty($data['source_inspection_event_id'])) {
            $sourceInspection = ProductionInspectionEvent::query()
                ->where('project_id', $project->id)
                ->findOrFail((int) $data['source_inspection_event_id']);

            if ((int) $sourceInspection->assembly_id !== (int) $assembly->id) {
                return back()
                    ->withInput()
                    ->withErrors(['source_inspection_event_id' => 'Selected inspection does not belong to the selected assembly.']);
            }

            if (! in_array((string) $sourceInspection->result, ['failed', 'reoffer', 'hold'], true)) {
                return back()
                    ->withInput()
                    ->withErrors(['source_inspection_event_id' => 'Rework can only be initiated from failed, re-offer, or hold inspections.']);
            }

            $existingRework = ProductionReworkEvent::query()
                ->where('project_id', $project->id)
                ->where('source_inspection_event_id', $sourceInspection->id)
                ->exists();

            if ($existingRework) {
                return back()
                    ->withInput()
                    ->withErrors(['source_inspection_event_id' => 'A rework event already exists for this inspection. Create the next rework from a fresh failed re-inspection instead.']);
            }
        }

        $rework = ProductionReworkEvent::query()->create([
            'project_id' => $project->id,
            'assembly_id' => $assembly->id,
            'source_inspection_event_id' => $sourceInspection?->id,
            'rework_date' => $data['rework_date'],
            'reason_code' => $data['reason_code'] ?? null,
            'reason_description' => $data['reason_description'] ?? null,
            'action_taken' => $data['action_taken'],
            'rework_dpr_id' => $selectedDpr?->id,
            'reoffer_date' => $data['reoffer_date'] ?? null,
            'final_result' => $data['final_result'],
            'remarks' => $data['remarks'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('projects.production-v2.rework-events.show', ['project' => $project->id, 'reworkEvent' => $rework->id])
            ->with('success', 'Production V2 rework event created.');
    }

    public function show(Project $project, ProductionReworkEvent $reworkEvent)
    {
        abort_unless((int) $reworkEvent->project_id === (int) $project->id, 404);
        return view('production_v2.rework_events.show', $this->showData($project, $reworkEvent));
    }

    public function print(Project $project, ProductionReworkEvent $reworkEvent)
    {
        abort_unless((int) $reworkEvent->project_id === (int) $project->id, 404);

        return view('production_v2.rework_events.print', $this->showData($project, $reworkEvent));
    }

    protected function showData(Project $project, ProductionReworkEvent $reworkEvent): array
    {
        $reworkEvent->load([
            'assembly',
            'sourceInspection.weldingEvent.welder',
            'sourceInspection.checkedBy',
        ]);

        $latestFitup = ProductionFitup::query()
            ->where('project_id', $project->id)
            ->where('assembly_id', $reworkEvent->assembly_id)
            ->with(['consumptions.partDefinition', 'consumptions.uom', 'consumptions.wipItem.motherStock'])
            ->latest('fitup_date')
            ->latest('id')
            ->first();

        $latestWeldingEvent = ProductionWeldingEvent::query()
            ->where('project_id', $project->id)
            ->where('assembly_id', $reworkEvent->assembly_id)
            ->latest('weld_date')
            ->latest('id')
            ->first();

        return [
            'project' => $project,
            'reworkEvent' => $reworkEvent,
            'latestFitup' => $latestFitup,
            'latestWeldingEvent' => $latestWeldingEvent,
        ];
    }
}
