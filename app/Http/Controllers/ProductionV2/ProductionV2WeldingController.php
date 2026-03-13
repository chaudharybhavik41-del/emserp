<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Machine;
use App\Models\Party;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionFitup;
use App\Models\ProductionV2\ProductionWeldingEvent;
use App\Support\ProductionV2\DailyDprManager;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionV2WeldingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dpr.view')->only(['index', 'show']);
        $this->middleware('permission:production.dpr.create')->only(['create', 'store']);
    }

    public function index(Project $project)
    {
        $rows = ProductionWeldingEvent::query()
            ->where('project_id', $project->id)
            ->with(['assembly', 'welder', 'supervisor'])
            ->withCount('inspections')
            ->orderByDesc('id')
            ->paginate(25);

        return view('production_v2.welding_events.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }

    public function create(Request $request, Project $project)
    {
        $selectedDpr = app(DailyDprManager::class)->findForActivity($project, (int) $request->integer('dpr_id'), 'welding');
        $selectedAssemblyId = (int) $request->integer('assembly_id');
        $assemblies = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->where('status', 'released')
            ->withCount('fitups')
            ->orderBy('assembly_code')
            ->get();

        $selectedAssembly = $selectedAssemblyId > 0
            ? ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->where('status', 'released')
                ->with([
                    'fitups' => fn ($query) => $query->with(['supervisor', 'inspector', 'contractor', 'consumptions.partDefinition', 'consumptions.uom', 'consumptions.wipItem.motherStock'])->latest('fitup_date')->latest('id')->limit(5),
                    'requirements.partDefinition',
                ])
                ->withCount(['fitups', 'weldingEvents', 'inspectionEvents'])
                ->find($selectedAssemblyId)
            : null;

        $latestFitup = $selectedAssembly?->fitups?->first();
        $fitupConsumptionSummary = $latestFitup
            ? $latestFitup->consumptions
                ->sortBy(fn ($row) => (string) ($row->partDefinition?->part_code ?? ''))
                ->values()
            : collect();

        return view('production_v2.welding_events.form', [
            'project' => $project,
            'assemblies' => $assemblies,
            'selectedAssembly' => $selectedAssembly,
            'latestFitup' => $latestFitup,
            'fitupConsumptionSummary' => $fitupConsumptionSummary,
            'machines' => Machine::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'contractors' => Party::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->limit(300)->get(['id', 'name']),
            'consumableItems' => Item::query()
                ->whereHas('type', fn ($query) => $query->where('code', 'CONSUMABLE'))
                ->orderBy('code')
                ->limit(300)
                ->get(['id', 'code', 'name']),
            'weldingProcesses' => ['SMAW', 'GMAW', 'FCAW', 'SAW', 'GTAW'],
            'statusOptions' => ['draft', 'in_progress', 'completed', 'approved'],
            'selectedDpr' => $selectedDpr,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'dpr_id' => ['nullable', 'integer', 'exists:production_dprs,id'],
            'assembly_id' => [
                'required',
                'integer',
                Rule::exists('production_v2_assemblies', 'id')->where(fn ($query) => $query->where('project_id', $project->id)->where('status', 'released')),
            ],
            'welding_process' => ['required', 'string', 'max:40'],
            'weld_date' => ['required', 'date'],
            'welder_id' => ['nullable', 'integer', 'exists:users,id'],
            'contractor_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'joint_description' => ['nullable', 'string', 'max:200'],
            'line_no' => ['nullable', 'string', 'max:120'],
            'weld_size_mm' => ['nullable', 'numeric', 'min:0'],
            'wpss_ref' => ['nullable', 'string', 'max:150'],
            'consumable_item_id' => ['nullable', 'integer', 'exists:items,id'],
            'consumable_batch' => ['nullable', 'string', 'max:120'],
            'shielding_gas' => ['nullable', 'string', 'max:120'],
            'current_amp' => ['nullable', 'numeric', 'min:0'],
            'voltage' => ['nullable', 'numeric', 'min:0'],
            'travel_speed' => ['nullable', 'numeric', 'min:0'],
            'heat_input' => ['nullable', 'numeric', 'min:0'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'inspector_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(['draft', 'in_progress', 'completed', 'approved'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $selectedDpr = null;
        if (! empty($data['dpr_id'])) {
            $selectedDpr = app(DailyDprManager::class)->findForActivity($project, (int) $data['dpr_id'], 'welding');
            if (! $selectedDpr) {
                return back()->withInput()->withErrors(['dpr_id' => 'Selected DPR is not valid for Production V2 welding.']);
            }
        }

        $assembly = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->where('status', 'released')
            ->findOrFail((int) $data['assembly_id']);

        $fitupExists = ProductionFitup::query()
            ->where('project_id', $project->id)
            ->where('assembly_id', $assembly->id)
            ->exists();

        if (! $fitupExists) {
            return back()
                ->withInput()
                ->withErrors(['assembly_id' => 'Selected assembly has no V2 fit-up yet. Fit-up is required before welding.']);
        }

        $latestFitup = ProductionFitup::query()
            ->where('project_id', $project->id)
            ->where('assembly_id', $assembly->id)
            ->latest('fitup_date')
            ->latest('id')
            ->first();

        $welding = ProductionWeldingEvent::query()->create([
            'project_id' => $project->id,
            'assembly_id' => $assembly->id,
            'dpr_id' => $selectedDpr?->id,
            'welding_process' => strtoupper(trim((string) $data['welding_process'])),
            'weld_date' => $data['weld_date'],
            'welder_id' => $data['welder_id'] ?? $selectedDpr?->worker_user_id,
            'contractor_party_id' => $data['contractor_party_id'] ?? $selectedDpr?->contractor_party_id ?? $latestFitup?->contractor_party_id,
            'joint_description' => $data['joint_description'] ?? null,
            'line_no' => $data['line_no'] ?? null,
            'weld_size_mm' => $data['weld_size_mm'] ?? null,
            'wpss_ref' => $data['wpss_ref'] ?? null,
            'consumable_item_id' => $data['consumable_item_id'] ?? null,
            'consumable_batch' => $data['consumable_batch'] ?? null,
            'shielding_gas' => $data['shielding_gas'] ?? null,
            'current_amp' => $data['current_amp'] ?? null,
            'voltage' => $data['voltage'] ?? null,
            'travel_speed' => $data['travel_speed'] ?? null,
            'heat_input' => $data['heat_input'] ?? null,
            'machine_id' => $data['machine_id'] ?? $selectedDpr?->machine_id,
            'supervisor_id' => $data['supervisor_id'] ?? $latestFitup?->supervisor_id,
            'inspector_id' => $data['inspector_id'] ?? $latestFitup?->inspector_id,
            'status' => $data['status'],
            'remarks' => $data['remarks'] ?? ($latestFitup ? 'Prepared from FU-' . $latestFitup->id : null),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('projects.production-v2.welding-events.show', ['project' => $project->id, 'weldingEvent' => $welding->id])
            ->with('success', 'Production V2 welding event created.');
    }

    public function show(Project $project, ProductionWeldingEvent $weldingEvent)
    {
        abort_unless((int) $weldingEvent->project_id === (int) $project->id, 404);
        return view('production_v2.welding_events.show', $this->showData($project, $weldingEvent));
    }

    public function print(Project $project, ProductionWeldingEvent $weldingEvent)
    {
        abort_unless((int) $weldingEvent->project_id === (int) $project->id, 404);

        return view('production_v2.welding_events.print', $this->showData($project, $weldingEvent));
    }

    protected function showData(Project $project, ProductionWeldingEvent $weldingEvent): array
    {
        $weldingEvent->load([
            'assembly',
            'welder',
            'contractor',
            'consumableItem',
            'machine',
            'supervisor',
            'inspector',
            'inspections.checkedBy',
        ]);

        $latestFitup = ProductionFitup::query()
            ->where('project_id', $project->id)
            ->where('assembly_id', $weldingEvent->assembly_id)
            ->with(['consumptions.partDefinition', 'consumptions.uom', 'consumptions.wipItem.motherStock'])
            ->latest('fitup_date')
            ->latest('id')
            ->first();

        return [
            'project' => $project,
            'weldingEvent' => $weldingEvent,
            'latestFitup' => $latestFitup,
        ];
    }
}
