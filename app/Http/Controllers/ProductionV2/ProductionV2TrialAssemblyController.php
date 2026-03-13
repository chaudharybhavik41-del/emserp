<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionTrialAssembly;
use App\Models\ProductionV2\ProductionTrialAssemblyMeasurement;
use App\Support\ProductionV2\DailyDprManager;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductionV2TrialAssemblyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dpr.view|production.qc.perform')->only(['index', 'show']);
        $this->middleware('permission:production.qc.perform|production.dpr.create')->only(['create', 'store']);
    }

    public function index(Project $project)
    {
        $rows = ProductionTrialAssembly::query()
            ->where('project_id', $project->id)
            ->with(['checkedBy', 'inspector'])
            ->withCount('measurements')
            ->orderByDesc('id')
            ->paginate(25);

        return view('production_v2.trial_assemblies.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }

    public function create(Request $request, Project $project)
    {
        $selectedDpr = app(DailyDprManager::class)->findForActivity($project, (int) $request->integer('dpr_id'), 'trial_assembly');
        $selectedAssemblyIds = collect((array) $request->input('assembly_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $assemblies = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->where('status', 'released')
            ->withCount(['inspectionEvents', 'reworkEvents'])
            ->orderBy('assembly_code')
            ->get();

        $selectedAssemblies = empty($selectedAssemblyIds)
            ? collect()
            : ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->where('status', 'released')
                ->whereIn('id', $selectedAssemblyIds)
                ->orderBy('assembly_code')
                ->get();

        $defaultGroupRef = $selectedAssemblies->isEmpty()
            ? ''
            : $selectedAssemblies->pluck('assembly_code')->implode(' + ');

        return view('production_v2.trial_assemblies.form', [
            'project' => $project,
            'assemblies' => $assemblies,
            'selectedAssemblies' => $selectedAssemblies,
            'defaultGroupRef' => $defaultGroupRef,
            'users' => User::query()->orderBy('name')->limit(300)->get(['id', 'name']),
            'statusOptions' => ['draft', 'in_progress', 'passed', 'failed', 'reoffer', 'hold'],
            'selectedDpr' => $selectedDpr,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'dpr_id' => ['nullable', 'integer', 'exists:production_dprs,id'],
            'assembly_ids' => ['nullable', 'array'],
            'assembly_ids.*' => [
                'integer',
                Rule::exists('production_v2_assemblies', 'id')->where('project_id', $project->id),
            ],
            'assembly_group_ref' => ['required', 'string', 'max:150'],
            'trial_date' => ['required', 'date'],
            'checked_by' => ['nullable', 'integer', 'exists:users,id'],
            'inspector_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(['draft', 'in_progress', 'passed', 'failed', 'reoffer', 'hold'])],
            'remarks' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.parameter_name' => ['required', 'string', 'max:150'],
            'rows.*.required_dimension' => ['nullable', 'string', 'max:150'],
            'rows.*.tolerance' => ['nullable', 'string', 'max:120'],
            'rows.*.actual_dimension' => ['nullable', 'string', 'max:150'],
            'rows.*.assembly_id' => ['nullable', 'integer', 'exists:production_v2_assemblies,id'],
            'rows.*.assembly_ref' => ['nullable', 'string', 'max:150'],
            'rows.*.ok_status' => ['nullable', 'boolean'],
            'rows.*.remarks' => ['nullable', 'string'],
        ]);

        $selectedDpr = null;
        if (! empty($data['dpr_id'])) {
            $selectedDpr = app(DailyDprManager::class)->findForActivity($project, (int) $data['dpr_id'], 'trial_assembly');
            if (! $selectedDpr) {
                return back()->withInput()->withErrors(['dpr_id' => 'Selected DPR is not valid for Production V2 trial assembly.']);
            }
        }

        $selectedAssemblyIds = collect($data['assembly_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $selectedAssemblies = $selectedAssemblyIds->isEmpty()
            ? collect()
            : ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->whereIn('id', $selectedAssemblyIds)
                ->orderBy('assembly_code')
                ->get()
                ->keyBy('id');

        $defaultAssemblyId = $selectedAssemblyIds->count() === 1
            ? (int) $selectedAssemblyIds->first()
            : null;

        $trialAssembly = DB::transaction(function () use ($project, $data, $selectedAssemblyIds, $selectedAssemblies, $defaultAssemblyId, $selectedDpr) {
            $trialAssembly = ProductionTrialAssembly::query()->create([
                'project_id' => $project->id,
                'assembly_group_ref' => $data['assembly_group_ref'],
                'trial_date' => $data['trial_date'],
                'dpr_id' => $selectedDpr?->id,
                'checked_by' => $data['checked_by'] ?? $selectedDpr?->worker_user_id ?? auth()->id(),
                'inspector_id' => $data['inspector_id'] ?? null,
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if ($selectedAssemblyIds->isNotEmpty()) {
                $syncPayload = [];
                foreach ($selectedAssemblyIds->values() as $index => $assemblyId) {
                    $syncPayload[(int) $assemblyId] = ['sequence_no' => $index + 1];
                }
                $trialAssembly->assemblies()->sync($syncPayload);
            }

            foreach ($data['rows'] as $row) {
                $assemblyId = ! empty($row['assembly_id']) ? (int) $row['assembly_id'] : $defaultAssemblyId;
                if ($assemblyId && ! $selectedAssemblies->has($assemblyId)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'rows' => ['Measurement rows can only reference assemblies selected for this trial assembly.'],
                    ]);
                }

                $assemblyRef = $row['assembly_ref'] ?? null;
                if ($assemblyId && $selectedAssemblies->has($assemblyId)) {
                    $assemblyRef = $selectedAssemblies->get($assemblyId)?->assembly_code;
                }

                ProductionTrialAssemblyMeasurement::query()->create([
                    'trial_assembly_id' => $trialAssembly->id,
                    'parameter_name' => $row['parameter_name'],
                    'required_dimension' => $row['required_dimension'] ?? null,
                    'tolerance' => $row['tolerance'] ?? null,
                    'actual_dimension' => $row['actual_dimension'] ?? null,
                    'assembly_id' => $assemblyId,
                    'assembly_ref' => $assemblyRef,
                    'ok_status' => array_key_exists('ok_status', $row) ? (bool) $row['ok_status'] : null,
                    'remarks' => $row['remarks'] ?? null,
                ]);
            }

            return $trialAssembly;
        });

        return redirect()
            ->route('projects.production-v2.trial-assemblies.show', ['project' => $project->id, 'trialAssembly' => $trialAssembly->id])
            ->with('success', 'Production V2 trial assembly created.');
    }

    public function show(Project $project, ProductionTrialAssembly $trialAssembly)
    {
        abort_unless((int) $trialAssembly->project_id === (int) $project->id, 404);

        $trialAssembly->load(['checkedBy', 'inspector', 'assemblies', 'measurements.assembly']);

        return view('production_v2.trial_assemblies.show', [
            'project' => $project,
            'trialAssembly' => $trialAssembly,
        ]);
    }
}
