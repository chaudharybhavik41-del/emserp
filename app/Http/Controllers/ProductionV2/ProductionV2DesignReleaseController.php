<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionCuttingPlan;
use App\Models\ProductionV2\ProductionDesignRelease;
use App\Models\ProductionV2\ProductionMaterialRequirement;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Support\ProductionV2\RevisionImpactAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductionV2DesignReleaseController extends Controller
{
    public function __construct(protected RevisionImpactAnalyzer $revisionImpactAnalyzer)
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.view')->only(['index', 'show']);
        $this->middleware('permission:production.plan.create|production.plan.update')->only(['create', 'store']);
    }

    public function index(Project $project)
    {
        $rows = ProductionDesignRelease::query()
            ->where('project_id', $project->id)
            ->withCount(['parts', 'assemblies', 'cuttingPlans', 'materialRequirements'])
            ->with('releasedBy')
            ->latest('release_date')
            ->latest('id')
            ->paginate(25);

        return view('production_v2.design_releases.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }

    public function create(Project $project)
    {
        $parts = ProductionPartDefinition::query()
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->whereNull('design_release_id')
            ->orderBy('part_code')
            ->get();

        $assemblies = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->whereNull('design_release_id')
            ->withCount('requirements')
            ->with(['requirements.partDefinition'])
            ->orderBy('sequence_no')
            ->orderBy('assembly_code')
            ->get();

        $cuttingPlans = ProductionCuttingPlan::query()
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->whereNull('design_release_id')
            ->withCount('allocations')
            ->with(['allocations.partDefinition'])
            ->latest('plan_date')
            ->latest('id')
            ->get();

        $materialRequirements = ProductionMaterialRequirement::query()
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->whereNull('design_release_id')
            ->withCount('items')
            ->with('items')
            ->latest('requirement_date')
            ->latest('id')
            ->get();

        $effectiveLatest = $this->revisionImpactAnalyzer->effectiveLatestPartsByRoot($project->id);

        return view('production_v2.design_releases.form', [
            'project' => $project,
            'defaultReleaseNumber' => $this->nextReleaseNumber($project),
            'parts' => $parts,
            'assemblies' => $assemblies,
            'cuttingPlans' => $cuttingPlans,
            'materialRequirements' => $materialRequirements,
            'assemblyWarnings' => $assemblies
                ->mapWithKeys(function (ProductionAssembly $assembly) use ($effectiveLatest) {
                    $stale = $this->revisionImpactAnalyzer->staleAssemblyRequirementsAgainst($assembly, $effectiveLatest);

                    return [$assembly->id => $stale->pluck('partDefinition.part_code')->filter()->unique()->implode(', ')];
                })
                ->filter()
                ->all(),
            'cuttingPlanWarnings' => $cuttingPlans
                ->mapWithKeys(function (ProductionCuttingPlan $cuttingPlan) use ($effectiveLatest) {
                    $stale = $this->revisionImpactAnalyzer->staleCuttingPlanAllocationsAgainst($cuttingPlan, $effectiveLatest);

                    return [$cuttingPlan->id => $stale->pluck('partDefinition.part_code')->filter()->unique()->implode(', ')];
                })
                ->filter()
                ->all(),
            'materialRequirementWarnings' => $materialRequirements
                ->mapWithKeys(function (ProductionMaterialRequirement $materialRequirement) use ($project, $effectiveLatest) {
                    $stale = $this->revisionImpactAnalyzer->staleMaterialRequirementRootsAgainst($project, $materialRequirement, $effectiveLatest);

                    return [$materialRequirement->id => $stale->pluck('part_code')->filter()->unique()->implode(', ')];
                })
                ->filter()
                ->all(),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'release_number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('production_v2_design_releases', 'release_number')->where('project_id', $project->id),
            ],
            'release_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'part_ids' => ['nullable', 'array'],
            'part_ids.*' => ['integer'],
            'assembly_ids' => ['nullable', 'array'],
            'assembly_ids.*' => ['integer'],
            'cutting_plan_ids' => ['nullable', 'array'],
            'cutting_plan_ids.*' => ['integer'],
            'material_requirement_ids' => ['nullable', 'array'],
            'material_requirement_ids.*' => ['integer'],
        ]);

        $partIds = collect($data['part_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $assemblyIds = collect($data['assembly_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $cuttingPlanIds = collect($data['cutting_plan_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $materialRequirementIds = collect($data['material_requirement_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($partIds->isEmpty() && $assemblyIds->isEmpty() && $cuttingPlanIds->isEmpty() && $materialRequirementIds->isEmpty()) {
            throw ValidationException::withMessages([
                'part_ids' => 'Select at least one approved design record to release.',
            ]);
        }

        $this->validateReleaseSelection($project, $partIds, $assemblyIds, $cuttingPlanIds, $materialRequirementIds);

        $release = DB::transaction(function () use ($project, $data, $partIds, $assemblyIds, $cuttingPlanIds, $materialRequirementIds) {
            $release = ProductionDesignRelease::query()->create([
                'project_id' => $project->id,
                'release_number' => $data['release_number'],
                'release_date' => $data['release_date'],
                'remarks' => $data['remarks'] ?? null,
                'released_by' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $timestamp = now();

            if ($partIds->isNotEmpty()) {
                $parts = ProductionPartDefinition::query()
                    ->where('project_id', $project->id)
                    ->whereIn('id', $partIds)
                    ->where('status', 'approved')
                    ->whereNull('design_release_id')
                    ->get();

                foreach ($parts as $part) {
                    $part->update([
                        'status' => 'released',
                        'design_release_id' => $release->id,
                        'released_by' => auth()->id(),
                        'released_at' => $timestamp,
                        'updated_by' => auth()->id(),
                        'updated_at' => $timestamp,
                    ]);

                    $this->supersedeReleasedRevisions(ProductionPartDefinition::class, $part);
                }
            }

            if ($assemblyIds->isNotEmpty()) {
                $assemblies = ProductionAssembly::query()
                    ->where('project_id', $project->id)
                    ->whereIn('id', $assemblyIds)
                    ->where('status', 'approved')
                    ->whereNull('design_release_id')
                    ->get();

                foreach ($assemblies as $assembly) {
                    $assembly->update([
                        'status' => 'released',
                        'design_release_id' => $release->id,
                        'released_by' => auth()->id(),
                        'released_at' => $timestamp,
                        'updated_by' => auth()->id(),
                        'updated_at' => $timestamp,
                    ]);

                    $this->supersedeReleasedRevisions(ProductionAssembly::class, $assembly);
                }
            }

            if ($cuttingPlanIds->isNotEmpty()) {
                $cuttingPlans = ProductionCuttingPlan::query()
                    ->where('project_id', $project->id)
                    ->whereIn('id', $cuttingPlanIds)
                    ->where('status', 'approved')
                    ->whereNull('design_release_id')
                    ->get();

                foreach ($cuttingPlans as $cuttingPlan) {
                    $cuttingPlan->update([
                        'status' => 'released',
                        'design_release_id' => $release->id,
                        'released_by' => auth()->id(),
                        'released_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);

                    $this->supersedeReleasedRevisions(ProductionCuttingPlan::class, $cuttingPlan);
                }
            }

            if ($materialRequirementIds->isNotEmpty()) {
                $materialRequirements = ProductionMaterialRequirement::query()
                    ->where('project_id', $project->id)
                    ->whereIn('id', $materialRequirementIds)
                    ->where('status', 'approved')
                    ->whereNull('design_release_id')
                    ->get();

                foreach ($materialRequirements as $materialRequirement) {
                    $materialRequirement->update([
                        'status' => 'released',
                        'design_release_id' => $release->id,
                        'released_by' => auth()->id(),
                        'released_at' => $timestamp,
                        'updated_by' => auth()->id(),
                        'updated_at' => $timestamp,
                    ]);

                    $this->supersedeReleasedRevisions(ProductionMaterialRequirement::class, $materialRequirement);
                }
            }

            return $release;
        });

        return redirect()
            ->route('projects.production-v2.design-releases.show', ['project' => $project->id, 'designRelease' => $release->id])
            ->with('success', 'Design release created and selected planning records marked as released.');
    }

    public function show(Project $project, ProductionDesignRelease $designRelease)
    {
        abort_unless((int) $designRelease->project_id === (int) $project->id, 404);
        $designRelease->load([
            'releasedBy',
            'parts',
            'assemblies',
            'cuttingPlans',
            'materialRequirements',
        ]);

        return view('production_v2.design_releases.show', [
            'project' => $project,
            'designRelease' => $designRelease,
        ]);
    }

    protected function nextReleaseNumber(Project $project): string
    {
        $year = now()->year;
        $prefix = 'DR-' . strtoupper($project->code) . '-' . $year . '-';

        $last = ProductionDesignRelease::query()
            ->where('project_id', $project->id)
            ->where('release_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('release_number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    protected function supersedeReleasedRevisions(string $modelClass, mixed $releasedRecord): void
    {
        $rootId = $releasedRecord->revision_root_id ?: $releasedRecord->id;

        $modelClass::query()
            ->where('project_id', $releasedRecord->project_id)
            ->where('id', '!=', $releasedRecord->id)
            ->where('status', 'released')
            ->where(function ($query) use ($rootId) {
                $query->where('revision_root_id', $rootId)
                    ->orWhere(function ($sub) use ($rootId) {
                        $sub->whereNull('revision_root_id')
                            ->where('id', $rootId);
                    });
            })
            ->update([
                'status' => 'superseded',
                'superseded_by_revision_id' => $releasedRecord->id,
                'superseded_at' => now(),
                'updated_at' => now(),
            ]);
    }

    protected function validateReleaseSelection(Project $project, $partIds, $assemblyIds, $cuttingPlanIds, $materialRequirementIds): void
    {
        $effectiveLatest = $this->revisionImpactAnalyzer->effectiveLatestPartsByRoot($project->id, $partIds);
        $errors = [];

        if ($assemblyIds->isNotEmpty()) {
            $assemblies = ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->whereIn('id', $assemblyIds)
                ->where('status', 'approved')
                ->whereNull('design_release_id')
                ->with(['requirements.partDefinition'])
                ->get();

            foreach ($assemblies as $assembly) {
                $stale = $this->revisionImpactAnalyzer->staleAssemblyRequirementsAgainst($assembly, $effectiveLatest);
                if ($stale->isNotEmpty()) {
                    $errors['assembly_ids'][] = sprintf(
                        'Assembly %s still references older part revisions: %s.',
                        $assembly->assembly_code,
                        $stale->pluck('partDefinition.part_code')->filter()->unique()->implode(', ')
                    );
                }
            }
        }

        if ($cuttingPlanIds->isNotEmpty()) {
            $cuttingPlans = ProductionCuttingPlan::query()
                ->where('project_id', $project->id)
                ->whereIn('id', $cuttingPlanIds)
                ->where('status', 'approved')
                ->whereNull('design_release_id')
                ->with(['allocations.partDefinition'])
                ->get();

            foreach ($cuttingPlans as $cuttingPlan) {
                $stale = $this->revisionImpactAnalyzer->staleCuttingPlanAllocationsAgainst($cuttingPlan, $effectiveLatest);
                if ($stale->isNotEmpty()) {
                    $errors['cutting_plan_ids'][] = sprintf(
                        'Cutting plan %s still references older part revisions: %s.',
                        $cuttingPlan->plan_number,
                        $stale->pluck('partDefinition.part_code')->filter()->unique()->implode(', ')
                    );
                }
            }
        }

        if ($materialRequirementIds->isNotEmpty()) {
            $materialRequirements = ProductionMaterialRequirement::query()
                ->where('project_id', $project->id)
                ->whereIn('id', $materialRequirementIds)
                ->where('status', 'approved')
                ->whereNull('design_release_id')
                ->with('items')
                ->get();

            foreach ($materialRequirements as $materialRequirement) {
                $stale = $this->revisionImpactAnalyzer->staleMaterialRequirementRootsAgainst($project, $materialRequirement, $effectiveLatest);
                if ($stale->isNotEmpty()) {
                    $errors['material_requirement_ids'][] = sprintf(
                        'Material requirement %s is stale against newer part revisions: %s.',
                        $materialRequirement->requirement_number,
                        $stale->pluck('part_code')->filter()->unique()->implode(', ')
                    );
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
