<?php

namespace App\Support\ProductionV2;

use App\Models\ProductionV2\ProductionMaterialRequirement;
use App\Models\ProductionV2\ProductionMaterialRequirementItem;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionAssemblyPartRequirement;
use App\Models\ProductionV2\ProductionCuttingPlan;
use App\Models\ProductionV2\ProductionCuttingPlanAllocation;
use App\Models\ProductionV2\ProductionCuttingPlanPlate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevisionDraftBuilder
{
    public function __construct(
        protected RevisionImpactAnalyzer $revisionImpactAnalyzer,
        protected RouteSnapshotManager $routeSnapshotManager
    )
    {
    }

    public function createAssemblyRevisionWithLatestParts(ProductionAssembly $assembly, int $userId): array
    {
        $assembly->loadMissing('requirements.partDefinition');
        $latestReleased = $this->revisionImpactAnalyzer->effectiveLatestPartsByRoot((int) $assembly->project_id);
        $autoReplaced = [];

        $revision = DB::transaction(function () use ($assembly, $latestReleased, $userId, &$autoReplaced) {
            $revision = ProductionAssembly::query()->create([
                'project_id' => $assembly->project_id,
                'assembly_code' => $assembly->assembly_code,
                'assembly_name' => $assembly->assembly_name,
                'assembly_type' => $assembly->assembly_type,
                'span_no' => $assembly->span_no,
                'leaf_no' => $assembly->leaf_no,
                'segment_no' => $assembly->segment_no,
                'girder_no' => $assembly->girder_no,
                'drawing_ref' => $assembly->drawing_ref,
                'route_template_id' => $assembly->route_template_id,
                'sequence_no' => $assembly->sequence_no,
                'planned_qty' => $assembly->planned_qty,
                'planned_weight_kg' => $assembly->planned_weight_kg,
                'status' => 'draft',
                'revision_no' => ((int) $assembly->revision_no) + 1,
                'revision_root_id' => $assembly->revision_root_id ?: $assembly->id,
                'previous_revision_id' => $assembly->id,
                'remarks' => $assembly->remarks,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($assembly->requirements as $requirement) {
                $replacement = $this->resolveLatestPartReplacement($requirement->partDefinition, $latestReleased);
                if ($replacement['replaced']) {
                    $autoReplaced[] = $replacement['label'];
                }

                ProductionAssemblyPartRequirement::query()->create([
                    'assembly_id' => $revision->id,
                    'part_definition_id' => $replacement['part_id'] ?? $requirement->part_definition_id,
                    'required_qty' => $requirement->required_qty,
                    'uom_id' => $requirement->uom_id,
                    'consumption_sequence' => $requirement->consumption_sequence,
                    'is_mandatory' => $requirement->is_mandatory,
                    'is_client_dispatchable' => $requirement->is_client_dispatchable,
                    'remarks' => $requirement->remarks,
                ]);
            }

            return $revision;
        });

        $this->routeSnapshotManager->cloneAssemblySteps($assembly, $revision);

        return [
            'revision' => $revision,
            'auto_replaced' => collect($autoReplaced)->unique()->values(),
        ];
    }

    public function createCuttingPlanRevisionWithLatestParts(ProductionCuttingPlan $cuttingPlan, int $userId): array
    {
        $cuttingPlan->loadMissing('plannedPlates.allocations.partDefinition', 'allocations.partDefinition');
        $latestReleased = $this->revisionImpactAnalyzer->effectiveLatestPartsByRoot((int) $cuttingPlan->project_id);
        $autoReplaced = [];

        $revision = DB::transaction(function () use ($cuttingPlan, $latestReleased, $userId, &$autoReplaced) {
            $revision = ProductionCuttingPlan::query()->create([
                'project_id' => $cuttingPlan->project_id,
                'plan_number' => $cuttingPlan->plan_number,
                'plan_date' => $cuttingPlan->plan_date,
                'material_item_id' => $cuttingPlan->material_item_id,
                'grade' => $cuttingPlan->grade,
                'thickness_mm' => $cuttingPlan->thickness_mm,
                'source_mode' => $cuttingPlan->source_mode,
                'status' => 'draft',
                'revision_no' => ((int) $cuttingPlan->revision_no) + 1,
                'revision_root_id' => $cuttingPlan->revision_root_id ?: $cuttingPlan->id,
                'previous_revision_id' => $cuttingPlan->id,
                'remarks' => $cuttingPlan->remarks,
                'created_by' => $userId,
            ]);

            if ($cuttingPlan->plannedPlates->isNotEmpty()) {
                foreach ($cuttingPlan->plannedPlates as $plate) {
                    $newPlate = ProductionCuttingPlanPlate::query()->create([
                        'cutting_plan_id' => $revision->id,
                        'plate_ref' => $plate->plate_ref,
                        'planned_width_mm' => $plate->planned_width_mm,
                        'planned_length_mm' => $plate->planned_length_mm,
                        'planned_qty' => $plate->planned_qty,
                        'remarks' => $plate->remarks,
                    ]);

                    foreach ($plate->allocations as $allocation) {
                        $replacement = $this->resolveLatestPartReplacement($allocation->partDefinition, $latestReleased);
                        if ($replacement['replaced']) {
                            $autoReplaced[] = $replacement['label'];
                        }

                        ProductionCuttingPlanAllocation::query()->create([
                            'cutting_plan_id' => $revision->id,
                            'cutting_plan_plate_id' => $newPlate->id,
                            'part_definition_id' => $replacement['part_id'] ?? $allocation->part_definition_id,
                            'planned_qty' => $allocation->planned_qty,
                            'planned_blank_ref' => $allocation->planned_blank_ref,
                            'planned_blank_width_mm' => $allocation->planned_blank_width_mm,
                            'planned_blank_length_mm' => $allocation->planned_blank_length_mm,
                            'mother_stock_item_id' => null,
                            'cut_size_text' => $allocation->cut_size_text,
                            'cut_width_mm' => $allocation->cut_width_mm,
                            'cut_length_mm' => $allocation->cut_length_mm,
                            'thickness_mm' => $allocation->thickness_mm,
                            'allocation_group' => $allocation->allocation_group,
                            'remarks' => $allocation->remarks,
                        ]);
                    }
                }
            } else {
                foreach ($cuttingPlan->allocations as $allocation) {
                    $replacement = $this->resolveLatestPartReplacement($allocation->partDefinition, $latestReleased);
                    if ($replacement['replaced']) {
                        $autoReplaced[] = $replacement['label'];
                    }

                    ProductionCuttingPlanAllocation::query()->create([
                        'cutting_plan_id' => $revision->id,
                        'part_definition_id' => $replacement['part_id'] ?? $allocation->part_definition_id,
                        'planned_qty' => $allocation->planned_qty,
                        'planned_blank_ref' => $allocation->planned_blank_ref,
                        'planned_blank_width_mm' => $allocation->planned_blank_width_mm,
                        'planned_blank_length_mm' => $allocation->planned_blank_length_mm,
                        'mother_stock_item_id' => null,
                        'cut_size_text' => $allocation->cut_size_text,
                        'cut_width_mm' => $allocation->cut_width_mm,
                        'cut_length_mm' => $allocation->cut_length_mm,
                        'thickness_mm' => $allocation->thickness_mm,
                        'allocation_group' => $allocation->allocation_group,
                        'remarks' => $allocation->remarks,
                    ]);
                }
            }

            return $revision;
        });

        return [
            'revision' => $revision,
            'auto_replaced' => collect($autoReplaced)->unique()->values(),
        ];
    }

    public function createMaterialRequirementRevision(ProductionMaterialRequirement $materialRequirement, int $userId): array
    {
        $materialRequirement->loadMissing('items');

        $rows = $materialRequirement->basis === 'released_design'
            ? $this->buildMaterialRequirementRows((int) $materialRequirement->project_id, 'released_design')
            : $materialRequirement->items->map(function (ProductionMaterialRequirementItem $row) {
                return [
                    'material_item_id' => $row->material_item_id,
                    'material_category' => $row->material_category,
                    'material_grade' => $row->material_grade,
                    'thickness_mm' => $row->thickness_mm,
                    'width_mm' => $row->width_mm,
                    'length_mm' => $row->length_mm,
                    'profile_text' => $row->profile_text,
                    'part_revision_root_ids_json' => $row->part_revision_root_ids_json ?? [],
                    'required_qty' => $row->required_qty,
                    'required_weight_kg' => $row->required_weight_kg,
                    'planned_cut_qty_snapshot' => $row->planned_cut_qty_snapshot,
                    'remarks' => $row->remarks,
                ];
            })->values();

        $revision = DB::transaction(function () use ($materialRequirement, $rows, $userId) {
            $revision = ProductionMaterialRequirement::query()->create([
                'project_id' => $materialRequirement->project_id,
                'requirement_number' => $materialRequirement->requirement_number,
                'requirement_date' => now()->toDateString(),
                'basis' => $materialRequirement->basis,
                'status' => 'draft',
                'revision_no' => ((int) $materialRequirement->revision_no) + 1,
                'revision_root_id' => $materialRequirement->revision_root_id ?: $materialRequirement->id,
                'previous_revision_id' => $materialRequirement->id,
                'remarks' => $materialRequirement->remarks,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($rows as $row) {
                ProductionMaterialRequirementItem::query()->create([
                    'material_requirement_id' => $revision->id,
                    'material_item_id' => $row['material_item_id'] ?: null,
                    'material_category' => $row['material_category'] ?: null,
                    'material_grade' => $row['material_grade'] ?: null,
                    'thickness_mm' => $row['thickness_mm'] ?: null,
                    'width_mm' => $row['width_mm'] ?: null,
                    'length_mm' => $row['length_mm'] ?: null,
                    'profile_text' => $row['profile_text'] ?: null,
                    'part_revision_root_ids_json' => collect($row['part_revision_root_ids_json'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all(),
                    'required_qty' => $row['required_qty'],
                    'required_weight_kg' => $row['required_weight_kg'] ?: null,
                    'planned_cut_qty_snapshot' => $row['planned_cut_qty_snapshot'] ?: 0,
                    'remarks' => $row['remarks'] ?? null,
                ]);
            }

            return $revision;
        });

        return [
            'revision' => $revision,
            'refreshed' => $materialRequirement->basis === 'released_design',
            'row_count' => $rows->count(),
        ];
    }

    protected function buildMaterialRequirementRows(int $projectId, string $basis): Collection
    {
        $partStatuses = $basis === 'released_design' ? ['released'] : ['approved', 'released'];
        $planStatuses = $basis === 'released_design' ? ['released'] : ['approved', 'released'];

        $plannedCuts = ProductionCuttingPlanAllocation::query()
            ->select('part_definition_id', DB::raw('SUM(planned_qty) as planned_cut_qty'))
            ->whereHas('cuttingPlan', fn ($query) => $query->where('project_id', $projectId)->whereIn('status', $planStatuses))
            ->groupBy('part_definition_id')
            ->pluck('planned_cut_qty', 'part_definition_id');

        $parts = ProductionPartDefinition::query()
            ->where('project_id', $projectId)
            ->whereIn('status', $partStatuses)
            ->with('materialItem')
            ->orderBy('part_code')
            ->get();

        return $parts
            ->groupBy(function (ProductionPartDefinition $part) {
                return implode('|', [
                    (int) ($part->material_item_id ?? 0),
                    strtolower((string) ($part->material_category ?? '')),
                    strtolower((string) ($part->material_grade ?? '')),
                    (string) ($part->thickness_mm ?? ''),
                    (string) ($part->width_mm ?? ''),
                    (string) ($part->length_mm ?? ''),
                ]);
            })
            ->map(function (Collection $group) use ($plannedCuts) {
                /** @var ProductionPartDefinition $sample */
                $sample = $group->first();
                $requiredQty = (float) $group->sum(fn (ProductionPartDefinition $part) => (float) $part->required_qty);
                $requiredWeight = (float) $group->sum(fn (ProductionPartDefinition $part) => (float) (($part->unit_weight_kg ?? 0) * ($part->required_qty ?? 0)));
                $plannedCutQty = (float) $group->sum(fn (ProductionPartDefinition $part) => (float) ($plannedCuts[$part->id] ?? 0));

                return [
                    'material_item_id' => $sample->material_item_id,
                    'material_category' => $sample->material_category,
                    'material_grade' => $sample->material_grade,
                    'thickness_mm' => $sample->thickness_mm,
                    'width_mm' => $sample->width_mm,
                    'length_mm' => $sample->length_mm,
                    'profile_text' => $this->profileText($sample),
                    'part_revision_root_ids_json' => $group->map(fn (ProductionPartDefinition $part) => (int) ($part->revision_root_id ?: $part->id))
                        ->unique()
                        ->values()
                        ->all(),
                    'required_qty' => round($requiredQty, 3),
                    'required_weight_kg' => round($requiredWeight, 3),
                    'planned_cut_qty_snapshot' => round($plannedCutQty, 3),
                    'remarks' => null,
                ];
            })
            ->values();
    }

    protected function resolveLatestPartReplacement($sourcePart, Collection $latestReleased): array
    {
        if (! $sourcePart) {
            return [
                'part_id' => null,
                'replaced' => false,
                'label' => null,
            ];
        }

        $latestPart = $latestReleased->get((int) ($sourcePart->revision_root_id ?: $sourcePart->id));
        if (! $latestPart || (int) $latestPart->id === (int) $sourcePart->id) {
            return [
                'part_id' => (int) $sourcePart->id,
                'replaced' => false,
                'label' => null,
            ];
        }

        return [
            'part_id' => (int) $latestPart->id,
            'replaced' => true,
            'label' => ($sourcePart->part_code ?: ('Part #' . $sourcePart->id)) . ' -> ' . $latestPart->part_code,
        ];
    }

    protected function profileText(ProductionPartDefinition $part): ?string
    {
        if ($part->is_section_item) {
            return $part->materialItem?->code ?: ($part->length_mm ? ('L ' . $part->length_mm . ' mm') : null);
        }

        if ($part->thickness_mm && $part->width_mm && $part->length_mm) {
            return 'T ' . $part->thickness_mm . ' x W ' . $part->width_mm . ' x L ' . $part->length_mm . ' mm';
        }

        if ($part->width_mm && $part->length_mm) {
            return 'W ' . $part->width_mm . ' x L ' . $part->length_mm . ' mm';
        }

        return $part->drawing_ref;
    }
}
