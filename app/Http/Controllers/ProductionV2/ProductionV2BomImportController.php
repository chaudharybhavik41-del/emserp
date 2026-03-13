<?php

namespace App\Http\Controllers\ProductionV2;

use App\Enums\BomItemMaterialCategory;
use App\Enums\BomStatus;
use App\Http\Controllers\Controller;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionAssemblyPartRequirement;
use App\Models\ProductionV2\ProductionPartDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionV2BomImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.create');
    }

    public function form(Project $project)
    {
        $boms = Bom::query()
            ->where('project_id', $project->id)
            ->whereIn('status', [BomStatus::FINALIZED->value, BomStatus::ACTIVE->value])
            ->orderByDesc('id')
            ->get(['id', 'bom_number', 'version', 'status']);

        return view('production_v2.import_bom', [
            'project' => $project,
            'boms' => $boms,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'bom_id' => ['required', 'integer', 'exists:boms,id'],
            'replace_existing' => ['nullable', 'boolean'],
            'activate_v2' => ['nullable', 'boolean'],
        ]);

        $bom = Bom::query()->with(['items.item', 'items.uom'])->findOrFail((int) $data['bom_id']);
        if ((int) $bom->project_id !== (int) $project->id) {
            throw ValidationException::withMessages([
                'bom_id' => 'Selected BOM does not belong to the selected project.',
            ]);
        }

        $status = $bom->status instanceof BomStatus ? $bom->status->value : (string) $bom->status;
        if (! in_array($status, [BomStatus::FINALIZED->value, BomStatus::ACTIVE->value], true)) {
            throw ValidationException::withMessages([
                'bom_id' => 'Only finalized or active BOMs can be imported into Production V2.',
            ]);
        }

        $items = $bom->items
            ->sortBy([
                ['level', 'asc'],
                ['sequence_no', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'bom_id' => 'The selected BOM has no items to import.',
            ]);
        }

        [$assemblySeeds, $partSeeds, $requirementSeeds] = $this->buildV2Seeds($project, $bom, $items);

        DB::transaction(function () use ($project, $assemblySeeds, $partSeeds, $requirementSeeds, $request) {
            if ($request->boolean('replace_existing', true)) {
                $this->guardProjectForPlanningReset($project);
                ProductionAssembly::query()->where('project_id', $project->id)->delete();
                ProductionPartDefinition::query()->where('project_id', $project->id)->delete();
            }

            $partIdMap = [];
            foreach ($partSeeds as $seed) {
                $part = ProductionPartDefinition::query()->create($seed + [
                    'project_id' => $project->id,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
                $partIdMap[$seed['_signature']] = $part->id;
            }

            $assemblyIdMap = [];
            foreach ($assemblySeeds as $seed) {
                $assembly = ProductionAssembly::query()->create($seed + [
                    'project_id' => $project->id,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
                $assemblyIdMap[$seed['_assembly_key']] = $assembly->id;
            }

            foreach ($requirementSeeds as $seed) {
                ProductionAssemblyPartRequirement::query()->create([
                    'assembly_id' => $assemblyIdMap[$seed['_assembly_key']],
                    'part_definition_id' => $partIdMap[$seed['_signature']],
                    'required_qty' => $seed['required_qty'],
                    'uom_id' => $seed['uom_id'],
                    'consumption_sequence' => $seed['consumption_sequence'],
                    'is_mandatory' => true,
                    'remarks' => $seed['remarks'],
                ]);
            }

            if ($request->boolean('activate_v2', true)) {
                $project->production_mode = 'v2_enabled';
                $project->save();
            }
        });

        return redirect()
            ->route('production-v2.project.design', ['project' => $project->id])
            ->with('success', 'Production V2 planning core imported from BOM.');
    }

    protected function buildV2Seeds(Project $project, Bom $bom, Collection $items): array
    {
        $itemsById = $items->keyBy('id')->all();
        $leafAssemblyIds = $this->leafAssemblyIds($items);
        $assemblyWeights = $bom->assembly_weights ?? [];

        $assemblySeeds = [];
        $assemblyCodeRegistry = [];
        $syntheticAssemblyKey = null;

        if (empty($leafAssemblyIds)) {
            $syntheticAssemblyKey = 'synthetic:' . $bom->id;
            $assemblySeeds[$syntheticAssemblyKey] = [
                '_assembly_key' => $syntheticAssemblyKey,
                'assembly_code' => $this->uniqueCode('BOM-' . $bom->id, $assemblyCodeRegistry),
                'assembly_name' => $bom->bom_number ?: ('BOM #' . $bom->id),
                'assembly_type' => 'project_scope',
                'drawing_ref' => null,
                'sequence_no' => 0,
                'planned_qty' => 1,
                'planned_weight_kg' => (float) ($bom->total_weight ?? 0),
                'status' => 'approved',
                'remarks' => 'Synthetic V2 assembly created because BOM has no fabricated assembly nodes.',
            ];
        } else {
            foreach ($items as $item) {
                if (! $item->isAssembly() || ! in_array((int) $item->id, $leafAssemblyIds, true)) {
                    continue;
                }

                $key = 'assembly:' . $item->id;
                $assemblySeeds[$key] = [
                    '_assembly_key' => $key,
                    'assembly_code' => $this->uniqueCode($item->item_code ?: ('ASM-' . $item->id), $assemblyCodeRegistry),
                    'assembly_name' => $item->description ?: ($item->item_code ?: ('Assembly #' . $item->id)),
                    'assembly_type' => $item->assembly_type ?: 'fabricated_assembly',
                    'drawing_ref' => $item->drawing_number,
                    'sequence_no' => (int) ($item->sequence_no ?? 0),
                    'planned_qty' => max(0.001, (float) $item->effectiveQuantity($itemsById)),
                    'planned_weight_kg' => (float) ($assemblyWeights[$item->id] ?? $item->effectiveTotalWeight($itemsById)),
                    'status' => 'approved',
                    'remarks' => 'Imported from BOM ' . ($bom->bom_number ?: ('#' . $bom->id)),
                ];
            }
        }

        $partAggregates = [];
        $requirementAggregates = [];
        $partCodeRegistry = [];

        foreach ($items as $item) {
            if (! $item->isLeafMaterial()) {
                continue;
            }

            $assemblyKey = $syntheticAssemblyKey ?: $this->nearestLeafAssemblyKey($item, $itemsById, $leafAssemblyIds);
            if (! $assemblyKey || ! isset($assemblySeeds[$assemblyKey])) {
                continue;
            }

            $signature = $this->partSignature($item);
            $effectiveQty = (float) $item->effectiveQuantity($itemsById);
            $effectiveWeight = (float) $item->effectiveTotalWeight($itemsById);
            if ($effectiveQty <= 0) {
                continue;
            }

            if (! isset($partAggregates[$signature])) {
                $partAggregates[$signature] = $this->partSeedFromBomItem($item, $signature, $partCodeRegistry);
            }

            $partAggregates[$signature]['required_qty'] += $effectiveQty;

            $assemblyPlannedQty = (float) ($assemblySeeds[$assemblyKey]['planned_qty'] ?? 1);
            if ($assemblyPlannedQty <= 0) {
                $assemblyPlannedQty = 1;
            }

            $requirementKey = $assemblyKey . '|' . $signature;
            if (! isset($requirementAggregates[$requirementKey])) {
                $requirementAggregates[$requirementKey] = [
                    '_assembly_key' => $assemblyKey,
                    '_signature' => $signature,
                    'required_qty' => 0,
                    'uom_id' => $item->uom_id,
                    'consumption_sequence' => (int) ($item->sequence_no ?? 0),
                    'remarks' => $item->description ?: null,
                ];
            }

            $requirementAggregates[$requirementKey]['required_qty'] += ($effectiveQty / $assemblyPlannedQty);

            if (($partAggregates[$signature]['unit_weight_kg'] ?? 0) <= 0 && $effectiveQty > 0) {
                $partAggregates[$signature]['unit_weight_kg'] = $effectiveWeight / $effectiveQty;
            }
        }

        return [
            array_values($assemblySeeds),
            array_values($partAggregates),
            array_values($requirementAggregates),
        ];
    }

    protected function leafAssemblyIds(Collection $items): array
    {
        $assemblyChildParents = [];

        foreach ($items as $item) {
            if (! $item->isAssembly() || empty($item->parent_item_id)) {
                continue;
            }

            $assemblyChildParents[(int) $item->parent_item_id] = true;
        }

        return $items
            ->filter(fn (BomItem $item) => $item->isAssembly() && ! isset($assemblyChildParents[(int) $item->id]))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    protected function nearestLeafAssemblyKey(BomItem $item, array $itemsById, array $leafAssemblyIds): ?string
    {
        $current = $item;
        $visited = [];

        while (! empty($current->parent_item_id)) {
            $parentId = (int) $current->parent_item_id;
            if (isset($visited[$parentId])) {
                break;
            }
            $visited[$parentId] = true;

            $parent = $itemsById[$parentId] ?? null;
            if (! $parent) {
                break;
            }

            if (in_array((int) $parent->id, $leafAssemblyIds, true)) {
                return 'assembly:' . $parent->id;
            }

            $current = $parent;
        }

        return null;
    }

    protected function partSeedFromBomItem(BomItem $item, string $signature, array &$registry): array
    {
        $category = $item->material_category?->value;
        $dims = $item->dimensions ?? [];
        $preferredCode = $item->item_code ?: (($item->item?->code ?? null) ?: strtoupper((string) $category));
        $code = $this->uniqueCode($preferredCode ?: 'PART', $registry);
        $seed = [
            '_signature' => $signature,
            'part_code' => $code,
            'part_name' => $item->description ?: ($item->item?->name ?: $code),
            'part_type' => $this->partTypeFromCategory($category),
            'uom_id' => $item->uom_id,
            'required_qty' => 0,
            'description' => $item->description,
            'material_item_id' => $item->item_id,
            'material_grade' => $item->grade,
            'material_category' => $category,
            'thickness_mm' => $dims['thickness_mm'] ?? ($item->item?->thickness ?? null),
            'width_mm' => $dims['width_mm'] ?? null,
            'length_mm' => $dims['length_mm'] ?? null,
            'unit_weight_kg' => $item->effectiveUnitWeight(),
            'unit_area_m2' => $item->unit_area_m2,
            'unit_cut_length_m' => $item->unit_cut_length_m,
            'unit_weld_length_m' => $item->unit_weld_length_m,
            'is_interchangeable' => in_array($category, [
                BomItemMaterialCategory::STEEL_PLATE->value,
                BomItemMaterialCategory::STEEL_SECTION->value,
            ], true),
            'is_cuttable' => in_array($category, [
                BomItemMaterialCategory::STEEL_PLATE->value,
                BomItemMaterialCategory::STEEL_SECTION->value,
            ], true),
            'is_section_item' => $category === BomItemMaterialCategory::STEEL_SECTION->value,
            'is_bought_out' => $category === BomItemMaterialCategory::BOUGHT_OUT->value,
            'drawing_ref' => $item->drawing_number,
            'status' => 'approved',
            'remarks' => 'Imported from BOM item #' . $item->id,
        ];

        return ProductionPartDefinition::applyMaterialItemDefaults($seed, $item->item);
    }

    protected function partSignature(BomItem $item): string
    {
        $dims = $item->dimensions ?? [];
        ksort($dims);

        return implode('|', [
            $item->material_category?->value ?: 'unknown',
            $item->item_code ?: '',
            $item->description ?: '',
            $item->grade ?: '',
            (string) ($item->uom_id ?: ''),
            (string) ($item->item_id ?: ''),
            json_encode($dims),
        ]);
    }

    protected function partTypeFromCategory(?string $category): string
    {
        return match ($category) {
            BomItemMaterialCategory::STEEL_PLATE->value => 'cuttable_plate',
            BomItemMaterialCategory::STEEL_SECTION->value => 'section',
            BomItemMaterialCategory::BOUGHT_OUT->value => 'bought_out',
            BomItemMaterialCategory::CONSUMABLE->value => 'consumable',
            default => 'fabricated',
        };
    }

    protected function uniqueCode(string $preferred, array &$registry): string
    {
        $base = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $preferred), '-'));
        if ($base === '') {
            $base = 'PART';
        }

        $candidate = substr($base, 0, 100);
        $suffix = 2;

        while (in_array($candidate, $registry, true)) {
            $candidate = substr($base, 0, 94) . '-' . $suffix;
            $suffix++;
        }

        $registry[] = $candidate;

        return $candidate;
    }

    protected function guardProjectForPlanningReset(Project $project): void
    {
        $downstreamCounts = [
            'cutting_plans' => DB::table('production_v2_cutting_plans')->where('project_id', $project->id)->count(),
            'cut_batches' => DB::table('production_v2_cut_batches')->where('project_id', $project->id)->count(),
            'wip_items' => DB::table('production_v2_wip_items')->where('project_id', $project->id)->count(),
            'fitups' => DB::table('production_v2_fitups')->where('project_id', $project->id)->count(),
            'welding' => DB::table('production_v2_welding_events')->where('project_id', $project->id)->count(),
            'inspection' => DB::table('production_v2_inspection_events')->where('project_id', $project->id)->count(),
            'trial' => DB::table('production_v2_trial_assemblies')->where('project_id', $project->id)->count(),
            'rework' => DB::table('production_v2_rework_events')->where('project_id', $project->id)->count(),
        ];

        $hasExecution = collect($downstreamCounts)->first(fn ($count) => (int) $count > 0);
        if ($hasExecution) {
            throw ValidationException::withMessages([
                'bom_id' => 'V2 execution data already exists for this project. Reset downstream V2 execution before replacing imported planning records.',
            ]);
        }
    }
}
