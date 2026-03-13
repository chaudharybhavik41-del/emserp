<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Party;
use App\Models\Project;
use App\Models\Production\ProductionDpr;
use App\Models\ProductionV2\ProductionCutBatch;
use App\Models\ProductionV2\ProductionFitup;
use App\Models\ProductionV2\ProductionInspectionEvent;
use App\Models\ProductionV2\ProductionOperationEvent;
use App\Models\ProductionV2\ProductionReworkEvent;
use App\Models\ProductionV2\ProductionTrialAssembly;
use App\Models\ProductionV2\ProductionWeldingEvent;
use App\Models\User;
use App\Support\ProductionV2\DailyDprManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionV2DprController extends Controller
{
    public function __construct(private DailyDprManager $dprManager)
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dpr.view')->only(['index', 'show']);
        $this->middleware('permission:production.dpr.create')->only(['create', 'store']);
    }

    public function index(Request $request, Project $project)
    {
        $activityOptions = $this->dprManager->activityOptions($project);
        $selectedActivity = (string) $request->query('activity', '');

        $rows = $this->dprManager->dprQuery($project)
            ->with(['activity', 'contractor', 'worker', 'machine'])
            ->when($selectedActivity !== '' && isset($activityOptions[$selectedActivity]), function ($query) use ($selectedActivity) {
                $query->where('production_activity_id', $this->dprManager->ensureActivity($selectedActivity)->id);
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('dpr_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('production_v2.dprs.index', [
            'project' => $project,
            'rows' => $rows,
            'activityOptions' => $activityOptions,
            'statusOptions' => ['draft', 'submitted', 'approved', 'cancelled'],
        ]);
    }

    public function create(Request $request, Project $project)
    {
        $activityOptions = $this->dprManager->activityOptions($project);
        $selectedActivity = (string) $request->query('activity', 'cutting');
        if (! isset($activityOptions[$selectedActivity])) {
            $selectedActivity = array_key_first($activityOptions) ?: 'cutting';
        }

        return view('production_v2.dprs.form', [
            'project' => $project,
            'activityOptions' => $activityOptions,
            'selectedActivity' => $selectedActivity,
            'statusOptions' => ['draft', 'submitted', 'approved'],
            'contractors' => Party::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->limit(300)->get(['id', 'name']),
            'machines' => Machine::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'recentRows' => $this->dprManager->recentForActivity($project, $selectedActivity, 8),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $activityOptions = $this->dprManager->activityOptions($project);

        $data = $request->validate([
            'activity_key' => ['required', Rule::in(array_keys($activityOptions))],
            'dpr_date' => ['required', 'date'],
            'shift' => ['nullable', 'string', 'max:30'],
            'contractor_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'worker_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'approved'])],
            'remarks' => ['nullable', 'string'],
            'open_after_create' => ['nullable', 'string'],
        ]);

        $dpr = $this->dprManager->create($project, $data['activity_key'], $data);

        if (($data['open_after_create'] ?? null) === '1') {
            return redirect()
                ->route(
                    $this->dprManager->routeForActivity($data['activity_key']),
                    ['project' => $project->id, 'dpr_id' => $dpr->id] + $this->dprManager->routeParametersForActivity($data['activity_key'])
                )
                ->with('success', 'Production V2 DPR created. Continue with the linked execution stage.');
        }

        return redirect()
            ->route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $dpr->id])
            ->with('success', 'Production V2 DPR created.');
    }

    public function show(Project $project, ProductionDpr $productionDpr)
    {
        abort_unless($this->belongsToProject($project, $productionDpr), 404);

        $productionDpr->load(['activity', 'contractor', 'worker', 'machine']);
        $activityKey = $this->dprManager->activityKeyForDpr($productionDpr);
        abort_unless($activityKey !== null, 404);

        $links = [
            'cut_batches' => ProductionCutBatch::query()->where('project_id', $project->id)->where('dpr_id', $productionDpr->id)->latest('id')->get(),
            'fitups' => ProductionFitup::query()->where('project_id', $project->id)->where('dpr_id', $productionDpr->id)->with('assembly')->latest('id')->get(),
            'welding_events' => ProductionWeldingEvent::query()->where('project_id', $project->id)->where('dpr_id', $productionDpr->id)->with('assembly')->latest('id')->get(),
            'inspection_events' => ProductionInspectionEvent::query()->where('project_id', $project->id)->where('related_dpr_id', $productionDpr->id)->with('assembly')->latest('id')->get(),
            'rework_events' => ProductionReworkEvent::query()->where('project_id', $project->id)->where('rework_dpr_id', $productionDpr->id)->with('assembly')->latest('id')->get(),
            'trial_assemblies' => ProductionTrialAssembly::query()->where('project_id', $project->id)->where('dpr_id', $productionDpr->id)->latest('id')->get(),
            'operation_events' => ProductionOperationEvent::query()->where('project_id', $project->id)->where('dpr_id', $productionDpr->id)->with(['partDefinition', 'assembly'])->latest('id')->get(),
        ];

        return view('production_v2.dprs.show', [
            'project' => $project,
            'productionDpr' => $productionDpr,
            'activityKey' => $activityKey,
            'nextRoute' => $this->dprManager->routeForActivity($activityKey),
            'nextRouteParameters' => ['project' => $project->id, 'dpr_id' => $productionDpr->id] + $this->dprManager->routeParametersForActivity($activityKey),
            'links' => $links,
        ]);
    }

    private function belongsToProject(Project $project, ProductionDpr $productionDpr): bool
    {
        $plan = $this->dprManager->ensurePlan($project);

        return (int) $productionDpr->production_plan_id === (int) $plan->id;
    }
}
