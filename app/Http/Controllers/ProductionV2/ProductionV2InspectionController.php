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
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductionV2InspectionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dpr.view|production.qc.perform')->only(['index', 'show']);
        $this->middleware('permission:production.qc.perform|production.dpr.create')->only(['create', 'store']);
    }

    public function index(Project $project)
    {
        $rows = ProductionInspectionEvent::query()
            ->where('project_id', $project->id)
            ->with(['assembly', 'weldingEvent', 'checkedBy'])
            ->orderByDesc('id')
            ->paginate(25);

        return view('production_v2.inspection_events.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }

    public function create(Request $request, Project $project)
    {
        $selectedDpr = app(DailyDprManager::class)->findForActivity($project, (int) $request->integer('dpr_id'), 'inspection');
        $selectedAssemblyId = (int) $request->integer('assembly_id');
        $selectedWeldingId = (int) $request->integer('welding_event_id');
        $selectedReworkId = (int) $request->integer('source_rework_event_id');

        $assemblies = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->where('status', 'released')
            ->withCount(['weldingEvents', 'inspectionEvents'])
            ->orderBy('assembly_code')
            ->get();

        $weldingEvents = ProductionWeldingEvent::query()
            ->where('project_id', $project->id)
            ->when($selectedAssemblyId > 0, fn ($query) => $query->where('assembly_id', $selectedAssemblyId))
            ->with('assembly')
            ->orderByDesc('weld_date')
            ->orderByDesc('id')
            ->get();

        $selectedWeldingEvent = $selectedWeldingId > 0
            ? ProductionWeldingEvent::query()
                ->where('project_id', $project->id)
                ->with(['assembly', 'welder', 'supervisor', 'inspector', 'consumableItem', 'inspections.checkedBy'])
                ->find($selectedWeldingId)
            : null;

        $selectedReworkEvent = $selectedReworkId > 0
            ? ProductionReworkEvent::query()
                ->where('project_id', $project->id)
                ->with(['assembly', 'sourceInspection.weldingEvent', 'sourceInspection.checkedBy'])
                ->find($selectedReworkId)
            : null;

        if ($selectedReworkEvent) {
            if ($selectedAssemblyId <= 0) {
                $selectedAssemblyId = (int) $selectedReworkEvent->assembly_id;
            }

            if ($selectedWeldingId <= 0) {
                $selectedWeldingId = (int) ($selectedReworkEvent->sourceInspection?->related_welding_event_id ?? 0);
                if ($selectedWeldingId > 0 && ! $selectedWeldingEvent) {
                    $selectedWeldingEvent = ProductionWeldingEvent::query()
                        ->where('project_id', $project->id)
                        ->with(['assembly', 'welder', 'supervisor', 'inspector', 'consumableItem', 'inspections.checkedBy'])
                        ->find($selectedWeldingId);
                }
            }
        }

        if ($selectedWeldingEvent && $selectedAssemblyId <= 0) {
            $selectedAssemblyId = (int) $selectedWeldingEvent->assembly_id;
        }

        $selectedAssembly = $selectedAssemblyId > 0
            ? ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->where('status', 'released')
                ->withCount(['fitups', 'weldingEvents', 'inspectionEvents', 'reworkEvents'])
                ->find($selectedAssemblyId)
            : null;

        if (! $selectedWeldingEvent && $selectedAssembly) {
            $selectedWeldingEvent = ProductionWeldingEvent::query()
                ->where('project_id', $project->id)
                ->where('assembly_id', $selectedAssembly->id)
                ->with(['assembly', 'welder', 'supervisor', 'inspector', 'consumableItem', 'inspections.checkedBy'])
                ->latest('weld_date')
                ->latest('id')
                ->first();
        }

        $latestFitup = $selectedAssembly
            ? ProductionFitup::query()
                ->where('project_id', $project->id)
                ->where('assembly_id', $selectedAssembly->id)
                ->with(['consumptions.partDefinition', 'consumptions.uom', 'consumptions.wipItem.motherStock'])
                ->latest('fitup_date')
                ->latest('id')
                ->first()
            : null;

        $fitupConsumptionSummary = $latestFitup
            ? $latestFitup->consumptions
                ->sortBy(fn ($row) => (string) ($row->partDefinition?->part_code ?? ''))
                ->values()
            : collect();

        return view('production_v2.inspection_events.form', [
            'project' => $project,
            'assemblies' => $assemblies,
            'weldingEvents' => $weldingEvents,
            'selectedAssembly' => $selectedAssembly,
            'selectedWeldingEvent' => $selectedWeldingEvent,
            'selectedReworkEvent' => $selectedReworkEvent,
            'latestFitup' => $latestFitup,
            'fitupConsumptionSummary' => $fitupConsumptionSummary,
            'users' => User::query()->orderBy('name')->limit(300)->get(['id', 'name']),
            'inspectionTypes' => ['visual', 'dimensional', 'dpt', 'mpt', 'ut', 'rt', 'final'],
            'resultOptions' => ['passed', 'failed', 'reoffer', 'hold'],
            'selectedDpr' => $selectedDpr,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'related_dpr_id' => ['nullable', 'integer', 'exists:production_dprs,id'],
            'assembly_id' => [
                'required',
                'integer',
                Rule::exists('production_v2_assemblies', 'id')->where('project_id', $project->id),
            ],
            'inspection_type' => ['required', 'string', 'max:60'],
            'inspection_date' => ['required', 'date'],
            'result' => ['required', Rule::in(['passed', 'failed', 'reoffer', 'hold'])],
            'related_welding_event_id' => ['nullable', 'integer', 'exists:production_v2_welding_events,id'],
            'source_rework_event_id' => ['nullable', 'integer', 'exists:production_v2_rework_events,id'],
            'line_no' => ['nullable', 'string', 'max:120'],
            'defect_type' => ['nullable', 'string', 'max:120'],
            'defect_description' => ['nullable', 'string'],
            'repair_action' => ['nullable', 'string'],
            'reoffer_no' => ['nullable', 'string', 'max:120'],
            'retest_result' => ['nullable', 'string', 'max:60'],
            'checked_by' => ['nullable', 'integer', 'exists:users,id'],
            'inspector_agency' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string'],
        ]);

        $selectedDpr = null;
        if (! empty($data['related_dpr_id'])) {
            $selectedDpr = app(DailyDprManager::class)->findForActivity($project, (int) $data['related_dpr_id'], 'inspection');
            if (! $selectedDpr) {
                return back()->withInput()->withErrors(['related_dpr_id' => 'Selected DPR is not valid for Production V2 inspection.']);
            }
        }

        if (($data['result'] ?? null) === 'reoffer' && blank($data['reoffer_no'] ?? null)) {
            return back()
                ->withInput()
                ->withErrors(['reoffer_no' => 'Re-offer number is required when inspection result is reoffer.']);
        }

        $assembly = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->findOrFail((int) $data['assembly_id']);

        $weldingEvent = null;
        if (! empty($data['related_welding_event_id'])) {
            $weldingEvent = ProductionWeldingEvent::query()
                ->where('project_id', $project->id)
                ->findOrFail((int) $data['related_welding_event_id']);

            if ((int) $weldingEvent->assembly_id !== (int) $assembly->id) {
                return back()
                    ->withInput()
                    ->withErrors(['related_welding_event_id' => 'Selected welding event does not belong to the selected assembly.']);
            }
        }

        $sourceRework = null;
        if (! empty($data['source_rework_event_id'])) {
            $sourceRework = ProductionReworkEvent::query()
                ->where('project_id', $project->id)
                ->with('sourceInspection')
                ->findOrFail((int) $data['source_rework_event_id']);

            if ((int) $sourceRework->assembly_id !== (int) $assembly->id) {
                return back()
                    ->withInput()
                    ->withErrors(['source_rework_event_id' => 'Selected rework event does not belong to the selected assembly.']);
            }

            if (! in_array((string) $sourceRework->final_result, ['pending', 'failed', 'reoffer', 'hold'], true)) {
                return back()
                    ->withInput()
                    ->withErrors(['source_rework_event_id' => 'Selected rework event is already closed.']);
            }
        }

        $inspection = DB::transaction(function () use ($project, $data, $assembly, $weldingEvent, $sourceRework) {
            $inspection = ProductionInspectionEvent::query()->create([
                'project_id' => $project->id,
                'assembly_id' => $assembly->id,
                'inspection_type' => strtolower(trim((string) $data['inspection_type'])),
                'inspection_date' => $data['inspection_date'],
                'result' => strtolower(trim((string) $data['result'])),
                'related_dpr_id' => $selectedDpr?->id,
                'related_welding_event_id' => $weldingEvent?->id,
                'line_no' => $data['line_no'] ?? $weldingEvent?->line_no,
                'defect_type' => $data['defect_type'] ?? null,
                'defect_description' => $data['defect_description'] ?? null,
                'repair_action' => $data['repair_action'] ?? null,
                'reoffer_no' => $data['reoffer_no'] ?? null,
                'retest_result' => $data['retest_result'] ?? null,
                'checked_by' => $data['checked_by'] ?? $selectedDpr?->worker_user_id ?? auth()->id(),
                'inspector_agency' => $data['inspector_agency'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if ($sourceRework) {
                $sourceRework->final_result = strtolower(trim((string) $data['result']));
                $sourceRework->reoffer_date = $data['inspection_date'];
                $sourceRework->updated_by = auth()->id();
                $sourceRework->save();

                if ($sourceRework->sourceInspection) {
                    $sourceRework->sourceInspection->retest_result = strtolower(trim((string) $data['result']));
                    if (! empty($data['reoffer_no'])) {
                        $sourceRework->sourceInspection->reoffer_no = $data['reoffer_no'];
                    }
                    $sourceRework->sourceInspection->save();
                }
            }

            return $inspection;
        });

        return redirect()
            ->route('projects.production-v2.inspection-events.show', ['project' => $project->id, 'inspectionEvent' => $inspection->id])
            ->with('success', 'Production V2 inspection event created.');
    }

    public function show(Project $project, ProductionInspectionEvent $inspectionEvent)
    {
        abort_unless((int) $inspectionEvent->project_id === (int) $project->id, 404);
        return view('production_v2.inspection_events.show', $this->showData($project, $inspectionEvent));
    }

    public function print(Project $project, ProductionInspectionEvent $inspectionEvent)
    {
        abort_unless((int) $inspectionEvent->project_id === (int) $project->id, 404);

        return view('production_v2.inspection_events.print', $this->showData($project, $inspectionEvent));
    }

    protected function showData(Project $project, ProductionInspectionEvent $inspectionEvent): array
    {
        $inspectionEvent->load([
            'assembly',
            'weldingEvent.welder',
            'checkedBy',
            'reworkEvents',
        ]);

        $latestFitup = ProductionFitup::query()
            ->where('project_id', $project->id)
            ->where('assembly_id', $inspectionEvent->assembly_id)
            ->with(['consumptions.partDefinition', 'consumptions.uom', 'consumptions.wipItem.motherStock'])
            ->latest('fitup_date')
            ->latest('id')
            ->first();

        return [
            'project' => $project,
            'inspectionEvent' => $inspectionEvent,
            'latestFitup' => $latestFitup,
        ];
    }
}
