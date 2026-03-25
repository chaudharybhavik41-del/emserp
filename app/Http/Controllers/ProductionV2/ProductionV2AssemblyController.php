<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionAssemblyPartRequirement;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Support\ProductionV2\RevisionImpactAnalyzer;
use App\Support\ProductionV2\RevisionDraftBuilder;
use App\Support\ProductionV2\RouteSnapshotManager;
use App\Models\Uom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductionV2AssemblyController extends Controller
{
    public function __construct(
        protected RevisionImpactAnalyzer $revisionImpactAnalyzer,
        protected RevisionDraftBuilder $revisionDraftBuilder,
        protected RouteSnapshotManager $routeSnapshotManager
    )
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.view')->only(['index', 'show']);
        $this->middleware('permission:production.plan.create')->only(['create', 'store']);
        $this->middleware('permission:production.plan.update')->only(['edit', 'update', 'revise']);
    }

    public function index(Request $request, Project $project)
    {
        $q = trim((string) $request->get('q', ''));

        $baseQuery = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($sub) use ($q) {
                    $sub->where('assembly_code', 'like', '%' . $q . '%')
                        ->orWhere('assembly_name', 'like', '%' . $q . '%')
                        ->orWhere('span_no', 'like', '%' . $q . '%')
                        ->orWhere('girder_no', 'like', '%' . $q . '%');
                });
            });

        $assemblies = (clone $baseQuery)
            ->withCount('requirements')
            ->orderBy('sequence_no')
            ->orderBy('assembly_code')
            ->paginate(25)
            ->withQueryString();

        return view('production_v2.assemblies.index', [
            'project' => $project,
            'assemblies' => $assemblies,
            'q' => $q,
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'released' => (clone $baseQuery)->where('status', 'released')->count(),
                'with_parts' => ProductionAssembly::query()
                    ->where('project_id', $project->id)
                    ->whereHas('requirements')
                    ->count(),
            ],
        ]);
    }

    public function create(Project $project)
    {
        $assembly = new ProductionAssembly([
            'status' => 'draft',
            'sequence_no' => 0,
        ]);

        return view('production_v2.assemblies.form', $this->formData($project, $assembly));
    }

    public function store(Request $request, Project $project)
    {
        $data = $this->validatedAssemblyData($request, $project);
        $requirements = $this->validatedRequirements($request, $project);

        $assembly = DB::transaction(function () use ($project, $data, $requirements) {
            $assembly = ProductionAssembly::query()->create($data + [
                'project_id' => $project->id,
                'revision_no' => (int) ($data['revision_no'] ?? 1),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $assembly->forceFill(['revision_root_id' => $assembly->id])->save();

            $this->syncRequirements($assembly, $requirements);
            $this->routeSnapshotManager->syncAssembly($assembly);

            return $assembly;
        });

        return redirect()
            ->route('projects.production-v2.assemblies.show', ['project' => $project->id, 'assembly' => $assembly->id])
            ->with('success', 'Production V2 assembly created.');
    }

    public function show(Project $project, ProductionAssembly $assembly)
    {
        abort_unless((int) $assembly->project_id === (int) $project->id, 404);
        $assembly->load(['requirements.partDefinition.uom', 'requirements.uom', 'designRelease', 'releasedBy', 'previousRevision', 'supersededByRevision', 'routeTemplate', 'routeSteps']);

        $revisionHistory = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->where('revision_root_id', $assembly->revision_root_id ?: $assembly->id)
            ->orderBy('revision_no')
            ->orderBy('id')
            ->get();

        return view('production_v2.assemblies.show', [
            'project' => $project,
            'assembly' => $assembly,
            'revisionHistory' => $revisionHistory,
            'dependencyImpact' => $this->revisionImpactAnalyzer->assemblyDependencyImpact($assembly),
        ]);
    }

    public function edit(Project $project, ProductionAssembly $assembly)
    {
        abort_unless((int) $assembly->project_id === (int) $project->id, 404);
        abort_if($assembly->status === 'released', 403, 'Released assemblies cannot be edited directly. Create a revision instead.');
        $assembly->load('requirements');

        return view('production_v2.assemblies.form', $this->formData($project, $assembly));
    }

    public function update(Request $request, Project $project, ProductionAssembly $assembly)
    {
        abort_unless((int) $assembly->project_id === (int) $project->id, 404);
        abort_if($assembly->status === 'released', 403, 'Released assemblies cannot be edited directly. Create a revision instead.');

        $data = $this->validatedAssemblyData($request, $project, $assembly->id);
        $requirements = $this->validatedRequirements($request, $project);

        DB::transaction(function () use ($assembly, $data, $requirements) {
            $assembly->update($data + ['updated_by' => auth()->id()]);
            $this->syncRequirements($assembly, $requirements);
            $this->routeSnapshotManager->syncAssembly($assembly);
        });

        return redirect()
            ->route('projects.production-v2.assemblies.show', ['project' => $project->id, 'assembly' => $assembly->id])
            ->with('success', 'Production V2 assembly updated.');
    }

    public function revise(Project $project, ProductionAssembly $assembly)
    {
        abort_unless((int) $assembly->project_id === (int) $project->id, 404);
        abort_unless(in_array($assembly->status, ['released', 'superseded'], true), 403);

        $result = $this->revisionDraftBuilder->createAssemblyRevisionWithLatestParts($assembly, (int) auth()->id());
        /** @var \App\Models\ProductionV2\ProductionAssembly $revision */
        $revision = $result['revision'];
        $autoReplaced = $result['auto_replaced']->all();

        return redirect()
            ->route('projects.production-v2.assemblies.edit', ['project' => $project->id, 'assembly' => $revision->id])
            ->with('success', $this->revisionDraftMessage('assembly', $autoReplaced));
    }

    protected function formData(Project $project, ProductionAssembly $assembly): array
    {
        return [
            'project' => $project,
            'assembly' => $assembly,
            'partDefinitions' => ProductionPartDefinition::query()
                ->where('project_id', $project->id)
                ->whereNotIn('status', ['superseded', 'obsolete'])
                ->with('uom')
                ->orderBy('part_code')
                ->get(),
            'uoms' => Uom::query()->orderBy('code')->get(),
        ];
    }

    protected function validatedAssemblyData(Request $request, Project $project, ?int $ignoreId = null): array
    {
        return $request->validate([
            'assembly_code' => ['required', 'string', 'max:120'],
            'assembly_name' => ['required', 'string', 'max:200'],
            'assembly_type' => ['nullable', 'string', 'max:120'],
            'span_no' => ['nullable', 'string', 'max:80'],
            'leaf_no' => ['nullable', 'string', 'max:80'],
            'segment_no' => ['nullable', 'string', 'max:80'],
            'girder_no' => ['nullable', 'string', 'max:80'],
            'drawing_ref' => ['nullable', 'string', 'max:150'],
            'route_template_id' => [
                'nullable',
                'integer',
                Rule::exists('production_v2_route_templates', 'id')
                    ->where('project_id', $project->id)
                    ->where('applies_to', 'assembly'),
            ],
            'sequence_no' => ['nullable', 'integer', 'min:0'],
            'planned_qty' => ['nullable', 'numeric', 'min:0.001'],
            'planned_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'reviewed', 'approved', 'released', 'superseded', 'active', 'closed'])],
            'revision_no' => ['nullable', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],
        ]);

        $revisionNo = (int) $request->input('revision_no', 1);
        $data['revision_no'] = $revisionNo;

        $assemblyCodeRule = Rule::unique('production_v2_assemblies', 'assembly_code')
            ->where('project_id', $project->id)
            ->where('revision_no', $revisionNo);

        if ($ignoreId) {
            $assemblyCodeRule = $assemblyCodeRule->ignore($ignoreId);
        }

        validator(
            ['assembly_code' => $data['assembly_code']],
            ['assembly_code' => [$assemblyCodeRule]]
        )->validate();

        return $data;
    }

    protected function validatedRequirements(Request $request, Project $project): array
    {
        $data = $request->validate([
            'requirements' => ['nullable', 'array'],
            'requirements.*.part_definition_id' => [
                'required',
                'integer',
                Rule::exists('production_v2_part_definitions', 'id')->where('project_id', $project->id),
            ],
            'requirements.*.required_qty' => ['required', 'numeric', 'min:0.001'],
            'requirements.*.uom_id' => ['nullable', 'integer', 'exists:uoms,id'],
            'requirements.*.consumption_sequence' => ['nullable', 'integer', 'min:0'],
            'requirements.*.is_mandatory' => ['nullable', 'boolean'],
            'requirements.*.is_client_dispatchable' => ['nullable', 'boolean'],
            'requirements.*.remarks' => ['nullable', 'string'],
        ]);

        return collect($data['requirements'] ?? [])
            ->filter(fn ($row) => ! empty($row['part_definition_id']))
            ->values()
            ->all();
    }

    protected function syncRequirements(ProductionAssembly $assembly, array $requirements): void
    {
        ProductionAssemblyPartRequirement::query()
            ->where('assembly_id', $assembly->id)
            ->delete();

        foreach ($requirements as $index => $row) {
            ProductionAssemblyPartRequirement::query()->create([
                'assembly_id' => $assembly->id,
                'part_definition_id' => (int) $row['part_definition_id'],
                'required_qty' => $row['required_qty'],
                'uom_id' => $row['uom_id'] ?: null,
                'consumption_sequence' => (int) ($row['consumption_sequence'] ?? $index + 1),
                'is_mandatory' => array_key_exists('is_mandatory', $row) ? (bool) $row['is_mandatory'] : false,
                'is_client_dispatchable' => array_key_exists('is_client_dispatchable', $row) ? (bool) $row['is_client_dispatchable'] : false,
                'remarks' => $row['remarks'] ?? null,
            ]);
        }
    }

    protected function revisionDraftMessage(string $label, array $autoReplaced): string
    {
        $autoReplaced = collect($autoReplaced)->unique()->values();
        if ($autoReplaced->isEmpty()) {
            return 'Revision draft created from released ' . $label . '.';
        }

        return 'Revision draft created from released ' . $label . '. Auto-updated part references: ' . $autoReplaced->implode(', ') . '.';
    }
}
