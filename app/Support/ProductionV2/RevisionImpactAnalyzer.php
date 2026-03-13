<?php

namespace App\Support\ProductionV2;

use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionCuttingPlan;
use App\Models\ProductionV2\ProductionMaterialRequirement;
use App\Models\ProductionV2\ProductionMaterialRequirementItem;
use App\Models\ProductionV2\ProductionPartDefinition;
use Illuminate\Support\Collection;

class RevisionImpactAnalyzer
{
    public function projectSummary(Project $project): array
    {
        $latestReleased = $this->effectiveLatestPartsByRoot($project->id);

        $assemblyRows = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->whereIn('status', ['approved', 'released'])
            ->whereNotIn('status', ['superseded', 'closed'])
            ->with(['requirements.partDefinition'])
            ->orderBy('assembly_code')
            ->get()
            ->map(function (ProductionAssembly $assembly) use ($latestReleased) {
                $staleRequirements = $this->staleAssemblyRequirementsAgainst($assembly, $latestReleased);

                return [
                    'assembly' => $assembly,
                    'stale_requirements' => $staleRequirements,
                ];
            })
            ->filter(fn (array $row) => $row['stale_requirements']->isNotEmpty())
            ->values();

        $cuttingPlanRows = ProductionCuttingPlan::query()
            ->where('project_id', $project->id)
            ->whereIn('status', ['approved', 'released'])
            ->whereNotIn('status', ['superseded', 'cancelled'])
            ->with(['allocations.partDefinition'])
            ->latest('plan_date')
            ->latest('id')
            ->get()
            ->map(function (ProductionCuttingPlan $plan) use ($latestReleased) {
                $staleAllocations = $this->staleCuttingPlanAllocationsAgainst($plan, $latestReleased);

                return [
                    'cutting_plan' => $plan,
                    'stale_allocations' => $staleAllocations,
                ];
            })
            ->filter(fn (array $row) => $row['stale_allocations']->isNotEmpty())
            ->values();

        $materialRequirementRows = ProductionMaterialRequirement::query()
            ->where('project_id', $project->id)
            ->whereIn('status', ['approved', 'released'])
            ->where('basis', 'released_design')
            ->whereNotIn('status', ['superseded'])
            ->with('items')
            ->latest('requirement_date')
            ->latest('id')
            ->get()
            ->map(function (ProductionMaterialRequirement $requirement) use ($project, $latestReleased) {
                $staleRoots = $this->staleMaterialRequirementRootsAgainst($project, $requirement, $latestReleased);

                return [
                    'material_requirement' => $requirement,
                    'stale_roots' => $staleRoots,
                ];
            })
            ->filter(fn (array $row) => $row['stale_roots']->isNotEmpty())
            ->values();

        return [
            'assemblies' => $assemblyRows,
            'cutting_plans' => $cuttingPlanRows,
            'material_requirements' => $materialRequirementRows,
        ];
    }

    public function partUsageImpact(ProductionPartDefinition $part): array
    {
        $projectId = (int) $part->project_id;
        $latestReleased = $this->effectiveLatestPartsByRoot($projectId);
        $rootId = $this->rootId($part);
        $latest = $latestReleased->get($rootId);

        if (! $latest || (int) $latest->id === (int) $part->id) {
            return [
                'active_latest_revision' => $latest,
                'impacted_assemblies' => collect(),
                'impacted_cutting_plans' => collect(),
                'impacted_material_requirements' => collect(),
            ];
        }

        $impactedAssemblies = ProductionAssembly::query()
            ->where('project_id', $projectId)
            ->whereIn('status', ['approved', 'released'])
            ->whereHas('requirements', fn ($query) => $query->where('part_definition_id', $part->id))
            ->with(['requirements' => fn ($query) => $query->where('part_definition_id', $part->id)->with('partDefinition')])
            ->orderBy('assembly_code')
            ->get();

        $impactedCuttingPlans = ProductionCuttingPlan::query()
            ->where('project_id', $projectId)
            ->whereIn('status', ['approved', 'released'])
            ->whereHas('allocations', fn ($query) => $query->where('part_definition_id', $part->id))
            ->with(['allocations' => fn ($query) => $query->where('part_definition_id', $part->id)->with('partDefinition')])
            ->latest('plan_date')
            ->latest('id')
            ->get();

        $impactedMaterialRequirements = ProductionMaterialRequirement::query()
            ->where('project_id', $projectId)
            ->whereIn('status', ['approved', 'released'])
            ->where('basis', 'released_design')
            ->with('items')
            ->get()
            ->filter(function (ProductionMaterialRequirement $requirement) use ($projectId, $rootId, $latest) {
                if (($requirement->released_at ?? $requirement->updated_at ?? $requirement->created_at) > ($latest->released_at ?? $latest->updated_at ?? $latest->created_at)) {
                    return false;
                }

                return $requirement->items->contains(function (ProductionMaterialRequirementItem $item) use ($projectId, $rootId) {
                    return collect($this->materialRequirementRowRootIds($projectId, $item))->contains($rootId);
                });
            })
            ->values();

        return [
            'active_latest_revision' => $latest,
            'impacted_assemblies' => $impactedAssemblies,
            'impacted_cutting_plans' => $impactedCuttingPlans,
            'impacted_material_requirements' => $impactedMaterialRequirements,
        ];
    }

    public function assemblyDependencyImpact(ProductionAssembly $assembly): Collection
    {
        $latestReleased = $this->effectiveLatestPartsByRoot((int) $assembly->project_id);
        $assembly->loadMissing('requirements.partDefinition');

        return $this->staleAssemblyRequirementsAgainst($assembly, $latestReleased);
    }

    public function cuttingPlanDependencyImpact(ProductionCuttingPlan $cuttingPlan): Collection
    {
        $latestReleased = $this->effectiveLatestPartsByRoot((int) $cuttingPlan->project_id);
        $cuttingPlan->loadMissing('allocations.partDefinition');

        return $this->staleCuttingPlanAllocationsAgainst($cuttingPlan, $latestReleased);
    }

    public function materialRequirementDependencyImpact(Project $project, ProductionMaterialRequirement $materialRequirement): Collection
    {
        $latestReleased = $this->effectiveLatestPartsByRoot((int) $project->id);
        $materialRequirement->loadMissing('items');

        return $this->staleMaterialRequirementRootsAgainst($project, $materialRequirement, $latestReleased);
    }

    public function effectiveLatestPartsByRoot(int $projectId, iterable $pendingPartIds = []): Collection
    {
        $latest = ProductionPartDefinition::query()
            ->where('project_id', $projectId)
            ->where('status', 'released')
            ->orderByDesc('revision_no')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (ProductionPartDefinition $part) => $this->rootId($part))
            ->map(fn (Collection $rows) => $rows->first());

        $pendingIds = collect($pendingPartIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($pendingIds->isEmpty()) {
            return $latest;
        }

        $pendingParts = ProductionPartDefinition::query()
            ->where('project_id', $projectId)
            ->whereIn('id', $pendingIds)
            ->where('status', 'approved')
            ->get();

        foreach ($pendingParts as $part) {
            $rootId = $this->rootId($part);
            $current = $latest->get($rootId);

            if (! $current || (int) $part->revision_no >= (int) $current->revision_no) {
                $latest->put($rootId, $part);
            }
        }

        return $latest;
    }

    public function staleAssemblyRequirementsAgainst(ProductionAssembly $assembly, Collection $latestReleased): Collection
    {
        return $assembly->requirements
            ->filter(function ($requirement) use ($latestReleased) {
                $part = $requirement->partDefinition;
                if (! $part) {
                    return false;
                }

                $latest = $latestReleased->get($this->rootId($part));

                return $latest && (int) $latest->id !== (int) $part->id;
            })
            ->values();
    }

    public function staleCuttingPlanAllocationsAgainst(ProductionCuttingPlan $cuttingPlan, Collection $latestReleased): Collection
    {
        return $cuttingPlan->allocations
            ->filter(function ($allocation) use ($latestReleased) {
                $part = $allocation->partDefinition;
                if (! $part) {
                    return false;
                }

                $latest = $latestReleased->get($this->rootId($part));

                return $latest && (int) $latest->id !== (int) $part->id;
            })
            ->values();
    }

    public function staleMaterialRequirementRootsAgainst(Project $project, ProductionMaterialRequirement $requirement, Collection $latestReleased): Collection
    {
        $snapshotAt = $requirement->released_at ?? $requirement->updated_at ?? $requirement->created_at;

        return $requirement->items
            ->flatMap(function (ProductionMaterialRequirementItem $item) use ($project) {
                return $this->materialRequirementRowRootIds((int) $project->id, $item);
            })
            ->unique()
            ->map(function (int $rootId) use ($latestReleased, $snapshotAt) {
                /** @var ProductionPartDefinition|null $latest */
                $latest = $latestReleased->get($rootId);
                if (! $latest) {
                    return null;
                }

                $latestChangedAt = $latest->released_at ?? $latest->updated_at ?? $latest->created_at;
                if (! $latestChangedAt || ! $snapshotAt || $latestChangedAt < $snapshotAt) {
                    return null;
                }

                return $latest;
            })
            ->filter()
            ->unique('id')
            ->values();
    }

    protected function materialRequirementRowRootIds(int $projectId, ProductionMaterialRequirementItem $item): array
    {
        $stored = collect($item->part_revision_root_ids_json ?? [])
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        if ($stored->isNotEmpty()) {
            return $stored->all();
        }

        return ProductionPartDefinition::query()
            ->where('project_id', $projectId)
            ->where(function ($query) use ($item) {
                $query->where('material_item_id', $item->material_item_id)
                    ->orWhere(function ($sub) use ($item) {
                        $sub->where('material_category', $item->material_category)
                            ->where('material_grade', $item->material_grade)
                            ->where('thickness_mm', $item->thickness_mm)
                            ->where('width_mm', $item->width_mm)
                            ->where('length_mm', $item->length_mm);
                    });
            })
            ->pluck('revision_root_id', 'id')
            ->map(fn ($rootId, $id) => (int) ($rootId ?: $id))
            ->unique()
            ->values()
            ->all();
    }

    protected function rootId(ProductionPartDefinition $part): int
    {
        return (int) ($part->revision_root_id ?: $part->id);
    }
}
