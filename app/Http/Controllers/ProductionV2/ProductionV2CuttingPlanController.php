<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Project;
use App\Models\ProductionV2\ProductionCuttingPlan;
use App\Models\ProductionV2\ProductionCuttingPlanAllocation;
use App\Models\ProductionV2\ProductionCuttingPlanPlate;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Support\ProductionV2\RevisionImpactAnalyzer;
use App\Support\ProductionV2\RevisionDraftBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductionV2CuttingPlanController extends Controller
{
    public function __construct(
        protected RevisionImpactAnalyzer $revisionImpactAnalyzer,
        protected RevisionDraftBuilder $revisionDraftBuilder
    )
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.view')->only(['index', 'show']);
        $this->middleware('permission:production.plan.create')->only(['create', 'store']);
        $this->middleware('permission:production.plan.update')->only(['edit', 'update', 'revise']);
    }

    public function index(Project $project)
    {
        $baseQuery = ProductionCuttingPlan::query()
            ->where('project_id', $project->id)
            ->with('materialItem:id,code,name,grade,thickness')
            ->withCount('allocations');

        $plans = (clone $baseQuery)
            ->orderByDesc('id')
            ->paginate(25);

        return view('production_v2.cutting_plans.index', [
            'project' => $project,
            'plans' => $plans,
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'released' => (clone $baseQuery)->where('status', 'released')->count(),
                'plates' => ProductionCuttingPlanPlate::query()
                    ->whereHas('cuttingPlan', fn ($query) => $query->where('project_id', $project->id))
                    ->count(),
                'allocations' => ProductionCuttingPlanAllocation::query()
                    ->whereHas('cuttingPlan', fn ($query) => $query->where('project_id', $project->id))
                    ->count(),
            ],
        ]);
    }

    public function create(Project $project)
    {
        return view('production_v2.cutting_plans.form', $this->formData($project, new ProductionCuttingPlan([
            'source_mode' => 'fresh_plate',
            'status' => 'draft',
            'revision_no' => 1,
        ])));
    }

    public function store(Request $request, Project $project)
    {
        $data = $this->validatedData($request, $project);

        $partMap = $this->partMapForPlannedPlates($project, $data);

        $normalized = $this->normalizeAndValidatePlannedPlates($project, $data, $partMap);
        $materialItem = $this->selectedMaterialItem($data);
        $data['grade'] = $normalized['grade'];
        $data['thickness_mm'] = $normalized['thickness_mm'];
        $data['planned_plates'] = $normalized['planned_plates'];

        $plans = DB::transaction(function () use ($project, $data, $materialItem) {
            $createdPlans = collect();

            foreach ($data['planned_plates'] as $plateRow) {
                $planNumber = $this->nextPlanNumber($project, $materialItem);
                $plateRows = $this->assignPlateRefs($planNumber, [$plateRow]);

                $plan = ProductionCuttingPlan::query()->create([
                    'project_id' => $project->id,
                    'plan_number' => $planNumber,
                    'plan_date' => $data['plan_date'] ?? null,
                    'material_item_id' => $data['material_item_id'],
                    'grade' => $data['grade'] ?? null,
                    'thickness_mm' => $data['thickness_mm'] ?? null,
                    'source_mode' => $data['source_mode'],
                    'status' => $data['status'],
                    'revision_no' => (int) ($data['revision_no'] ?? 1),
                    'revision_root_id' => null,
                    'remarks' => $data['remarks'] ?? null,
                    'created_by' => auth()->id(),
                    'approved_by' => null,
                    'approved_at' => null,
                ]);

                $plan->forceFill(['revision_root_id' => $plan->id])->save();

                $this->syncPlannedPlates($plan, $plateRows);
                $createdPlans->push($plan);
            }

            return $createdPlans;
        });

        if ($plans->count() === 1) {
            $plan = $plans->first();

            return redirect()
                ->route('projects.production-v2.cutting-plans.show', ['project' => $project->id, 'cuttingPlan' => $plan->id])
                ->with('success', 'Production V2 cutting plan created.');
        }

        return redirect()
            ->route('projects.production-v2.cutting-plans.index', ['project' => $project->id])
            ->with('success', $plans->count() . ' cutting plans created, one per planned plate.');
    }

    public function show(Project $project, ProductionCuttingPlan $cuttingPlan)
    {
        abort_unless((int) $cuttingPlan->project_id === (int) $project->id, 404);
        $cuttingPlan->load(['materialItem', 'plannedPlates.allocations.partDefinition', 'designRelease', 'releasedBy', 'previousRevision', 'supersededByRevision']);

        $revisionHistory = ProductionCuttingPlan::query()
            ->where('project_id', $project->id)
            ->where('revision_root_id', $cuttingPlan->revision_root_id ?: $cuttingPlan->id)
            ->orderBy('revision_no')
            ->orderBy('id')
            ->get();

        return view('production_v2.cutting_plans.show', [
            'project' => $project,
            'plan' => $cuttingPlan,
            'revisionHistory' => $revisionHistory,
            'dependencyImpact' => $this->revisionImpactAnalyzer->cuttingPlanDependencyImpact($cuttingPlan),
        ]);
    }

    public function edit(Project $project, ProductionCuttingPlan $cuttingPlan)
    {
        abort_unless((int) $cuttingPlan->project_id === (int) $project->id, 404);
        abort_if($cuttingPlan->status === 'released', 403, 'Released cutting plans cannot be edited directly. Create a revision instead.');

        $cuttingPlan->load('plannedPlates.allocations');

        return view('production_v2.cutting_plans.form', $this->formData($project, $cuttingPlan));
    }

    public function update(Request $request, Project $project, ProductionCuttingPlan $cuttingPlan)
    {
        abort_unless((int) $cuttingPlan->project_id === (int) $project->id, 404);
        abort_if($cuttingPlan->status === 'released', 403, 'Released cutting plans cannot be edited directly. Create a revision instead.');

        $data = $this->validatedData($request, $project, $cuttingPlan->id);

        $partMap = $this->partMapForPlannedPlates($project, $data);

        $normalized = $this->normalizeAndValidatePlannedPlates($project, $data, $partMap);
        if (count($normalized['planned_plates']) !== 1) {
            throw ValidationException::withMessages([
                'planned_plates' => 'One cutting plan can contain only one planned plate. Create separate plans for additional plates.',
            ]);
        }
        $data['grade'] = $normalized['grade'];
        $data['thickness_mm'] = $normalized['thickness_mm'];
        $data['planned_plates'] = $this->assignPlateRefs($cuttingPlan->plan_number, $normalized['planned_plates']);

        DB::transaction(function () use ($cuttingPlan, $data) {
            $cuttingPlan->update([
                'plan_date' => $data['plan_date'] ?? null,
                'material_item_id' => $data['material_item_id'],
                'grade' => $data['grade'] ?? null,
                'thickness_mm' => $data['thickness_mm'] ?? null,
                'source_mode' => $data['source_mode'],
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->syncPlannedPlates($cuttingPlan, $data['planned_plates']);
        });

        return redirect()
            ->route('projects.production-v2.cutting-plans.show', ['project' => $project->id, 'cuttingPlan' => $cuttingPlan->id])
            ->with('success', 'Production V2 cutting plan updated.');
    }

    public function revise(Project $project, ProductionCuttingPlan $cuttingPlan)
    {
        abort_unless((int) $cuttingPlan->project_id === (int) $project->id, 404);
        abort_unless(in_array($cuttingPlan->status, ['released', 'superseded'], true), 403);

        $result = $this->revisionDraftBuilder->createCuttingPlanRevisionWithLatestParts($cuttingPlan, (int) auth()->id());
        /** @var \App\Models\ProductionV2\ProductionCuttingPlan $revision */
        $revision = $result['revision'];
        $autoReplaced = $result['auto_replaced']->all();

        return redirect()
            ->route('projects.production-v2.cutting-plans.edit', ['project' => $project->id, 'cuttingPlan' => $revision->id])
            ->with('success', $this->revisionDraftMessage('cutting plan', $autoReplaced));
    }

    protected function formData(Project $project, ProductionCuttingPlan $cuttingPlan): array
    {
        $allocatedByPart = ProductionCuttingPlanAllocation::query()
            ->select('part_definition_id', DB::raw('SUM(planned_qty) as planned_qty_total'))
            ->whereHas('cuttingPlan', function ($query) use ($project, $cuttingPlan) {
                $query->where('project_id', $project->id)
                    ->whereNotIn('status', ['cancelled', 'superseded']);

                if ($cuttingPlan->exists) {
                    $query->where('id', '!=', $cuttingPlan->id);
                }
            })
            ->groupBy('part_definition_id')
            ->pluck('planned_qty_total', 'part_definition_id');

        return [
            'project' => $project,
            'plan' => $cuttingPlan,
            'materialItems' => $this->cuttingPlanMaterialItems(),
            'partDefinitions' => ProductionPartDefinition::query()
                ->where('project_id', $project->id)
                ->whereIn('status', ['approved', 'released'])
                ->where('is_cuttable', true)
                ->where(function ($query) {
                    $query->where('material_category', 'steel_plate')
                        ->orWhere('part_type', 'cuttable_plate');
                })
                ->with(['materialItem:id,code,name,grade,thickness,density,weight_per_meter'])
                ->orderBy('part_code')
                ->get()
                ->map(function (ProductionPartDefinition $part) use ($allocatedByPart) {
                    $requiredQty = (float) ($part->required_qty ?? 0);
                    $alreadyAllocated = (float) ($allocatedByPart[$part->id] ?? 0);
                    $part->setAttribute('remaining_qty_base', round(max($requiredQty - $alreadyAllocated, 0), 3));

                    return $part;
                }),
        ];
    }

    protected function validatedData(Request $request, Project $project, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'plan_date' => ['nullable', 'date'],
            'material_item_id' => ['required', 'integer', 'exists:items,id'],
            'source_mode' => ['nullable', Rule::in(['fresh_plate', 'remnant', 'mixed'])],
            'status' => ['required', Rule::in(['draft', 'reviewed', 'approved', 'released', 'superseded', 'cancelled'])],
            'revision_no' => ['nullable', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],
            'planned_plates' => ['required', 'array', 'min:1'],
            'planned_plates.*.plate_ref' => ['nullable', 'string', 'max:120'],
            'planned_plates.*.planned_width_mm' => ['required', 'numeric', 'min:0.001'],
            'planned_plates.*.planned_length_mm' => ['required', 'numeric', 'min:0.001'],
            'planned_plates.*.remarks' => ['nullable', 'string'],
            'planned_plates.*.allocations' => ['required', 'array', 'min:1'],
            'planned_plates.*.allocations.*.part_definition_id' => [
                'required',
                'integer',
                Rule::exists('production_v2_part_definitions', 'id')->where('project_id', $project->id),
            ],
            'planned_plates.*.allocations.*.planned_qty' => ['required', 'numeric', 'min:0.001'],
            'planned_plates.*.allocations.*.remarks' => ['nullable', 'string'],
        ]);

        $revisionNo = (int) $request->input('revision_no', 1);
        $data['revision_no'] = $revisionNo;

        $data['source_mode'] = $data['source_mode'] ?? 'fresh_plate';

        return $data;
    }

    protected function revisionDraftMessage(string $label, array $autoReplaced): string
    {
        $autoReplaced = collect($autoReplaced)->unique()->values();
        if ($autoReplaced->isEmpty()) {
            return 'Revision draft created from released ' . $label . '.';
        }

        return 'Revision draft created from released ' . $label . '. Auto-updated part references: ' . $autoReplaced->implode(', ') . '.';
    }

    protected function normalizeAndValidatePlannedPlates(Project $project, array $data, $partMap): array
    {
        $errors = [];
        $normalizedPlates = [];
        $rawProfiles = [];
        $materialItem = $this->selectedMaterialItem($data);

        foreach ($data['planned_plates'] as $plateIndex => $plateRow) {
            $plateWidth = $this->floatOrNull($plateRow['planned_width_mm'] ?? null);
            $plateLength = $this->floatOrNull($plateRow['planned_length_mm'] ?? null);
            $plateQty = 1.0;
            $plateArea = ($plateWidth && $plateLength && $plateQty) ? ($plateWidth * $plateLength * $plateQty) : 0.0;
            $allocatedArea = 0.0;
            $normalizedAllocations = [];

            foreach ($plateRow['allocations'] as $allocationIndex => $row) {
                $partId = (int) $row['part_definition_id'];
                /** @var \App\Models\ProductionV2\ProductionPartDefinition|null $part */
                $part = $partMap->get($partId);
                if (! $part) {
                    $errors["planned_plates.$plateIndex.allocations.$allocationIndex.part_definition_id"] = 'Selected part was not found.';
                    continue;
                }

                $profile = $this->partMaterialProfile($part, $row);

                if (! $part->is_cuttable || $profile['material_category'] !== 'steel_plate') {
                    $errors["planned_plates.$plateIndex.allocations.$allocationIndex.part_definition_id"] = 'Only cuttable steel plate parts can be used in this cutting plan.';
                    continue;
                }
                if (! $this->partMatchesMaterialItem($part, $profile, $materialItem)) {
                    $errors["planned_plates.$plateIndex.allocations.$allocationIndex.part_definition_id"] = 'Selected part does not match the selected material item profile.';
                    continue;
                }

                $cutWidth = $profile['width_mm'];
                $cutLength = $profile['length_mm'];
                if (! $cutWidth || ! $cutLength) {
                    $errors["planned_plates.$plateIndex.allocations.$allocationIndex.part_definition_id"] = 'Selected part must have width and length defined.';
                    continue;
                }

                $allocationQty = $this->floatOrNull($row['planned_qty'] ?? null) ?? 0.0;
                $allocatedArea += ($cutWidth * $cutLength * $allocationQty);

                $rawProfiles[] = $profile;
                $normalizedAllocations[] = [
                    'part_definition_id' => $partId,
                    'planned_qty' => $allocationQty,
                    'planned_blank_ref' => $plateRow['plate_ref'] ?? null,
                    'planned_blank_width_mm' => $plateWidth,
                    'planned_blank_length_mm' => $plateLength,
                    'cut_size_text' => $this->buildCutSizeText($profile, [
                        'cut_width_mm' => $cutWidth,
                        'cut_length_mm' => $cutLength,
                    ]),
                    'cut_width_mm' => $cutWidth,
                    'cut_length_mm' => $cutLength,
                    'thickness_mm' => $profile['thickness_mm'],
                    'allocation_group' => $plateRow['plate_ref'] ?? null,
                    'remarks' => $row['remarks'] ?? null,
                ];
            }

            if ($allocatedArea > ($plateArea + 0.0001)) {
                $errors["planned_plates.$plateIndex.allocations"] = 'Allocated part area exceeds planned plate area.';
            }

            $normalizedPlates[] = [
                'plate_ref' => trim((string) ($plateRow['plate_ref'] ?? '')),
                'planned_width_mm' => $plateWidth,
                'planned_length_mm' => $plateLength,
                'planned_qty' => $plateQty,
                'remarks' => $plateRow['remarks'] ?? null,
                'allocations' => $normalizedAllocations,
            ];
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $planProfile = $this->resolvePlanProfile($rawProfiles, $data, $materialItem);

        return [
            'material_item_id' => $materialItem->id,
            'grade' => $planProfile['grade'],
            'thickness_mm' => $planProfile['thickness_mm'],
            'planned_plates' => $normalizedPlates,
        ];
    }

    protected function nextPlanNumber(Project $project, Item $materialItem): string
    {
        $thickness = $this->floatOrNull($materialItem->thickness);
        $thicknessLabel = $thickness !== null
            ? str_replace('.', '', rtrim(rtrim(number_format($thickness, 3, '.', ''), '0'), '.'))
            : (string) $materialItem->id;
        $prefix = 'P' . $thicknessLabel . '-';

        $last = ProductionCuttingPlan::query()
            ->where('project_id', $project->id)
            ->where('plan_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('plan_number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    protected function resolvePlanProfile(array $rawProfiles, array $data, Item $materialItem): array
    {
        if (empty($rawProfiles)) {
            return [
                'grade' => $materialItem->grade ?: null,
                'thickness_mm' => $this->floatOrNull($materialItem->thickness),
            ];
        }

        $first = $rawProfiles[0];
        foreach ($rawProfiles as $profile) {
            if (($profile['material_category'] ?? null) !== ($first['material_category'] ?? null)) {
                throw ValidationException::withMessages([
                    'allocations' => 'A cutting plan cannot mix plate and section parts.',
                ]);
            }

            if ($this->normalizedString($profile['grade']) !== $this->normalizedString($first['grade'])) {
                throw ValidationException::withMessages([
                    'grade' => 'All raw parts in one cutting plan must have the same material grade.',
                ]);
            }

            if (! $this->sameDecimal($profile['thickness_mm'], $first['thickness_mm'])) {
                throw ValidationException::withMessages([
                    'thickness_mm' => 'All raw parts in one cutting plan must have the same thickness.',
                ]);
            }
        }

        $grade = $materialItem->grade ?: $first['grade'];
        $thickness = $this->floatOrNull($materialItem->thickness) ?? $first['thickness_mm'];

        if ($this->normalizedString($grade) !== $this->normalizedString($first['grade'])) {
            throw ValidationException::withMessages([
                'grade' => 'Plan grade does not match the selected part material grade.',
            ]);
        }

        if (! $this->sameDecimal($thickness, $first['thickness_mm'])) {
            throw ValidationException::withMessages([
                'thickness_mm' => 'Plan thickness does not match the selected part thickness.',
            ]);
        }

        return [
            'grade' => $first['grade'],
            'thickness_mm' => $first['thickness_mm'],
        ];
    }

    protected function cuttingPlanMaterialItems()
    {
        return Item::query()
            ->with(['type:id,code,name', 'category:id,code,name'])
            ->where('is_active', true)
            ->whereNotNull('thickness')
            ->whereHas('type', fn ($query) => $query->where('code', 'RAW'))
            ->orderBy('thickness')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'grade',
                'thickness',
                'material_type_id',
                'material_category_id',
            ]);
    }

    protected function selectedMaterialItem(array $data): Item
    {
        /** @var \App\Models\Item|null $materialItem */
        $materialItem = Item::query()
            ->with(['type:id,code,name', 'category:id,code,name'])
            ->find((int) $data['material_item_id']);

        if (! $materialItem || ! $materialItem->is_active) {
            throw ValidationException::withMessages([
                'material_item_id' => 'Selected material item is invalid.',
            ]);
        }

        if ($this->normalizedString($materialItem->type?->code) !== 'RAW' || $this->floatOrNull($materialItem->thickness) === null) {
            throw ValidationException::withMessages([
                'material_item_id' => 'Selected material item must be a raw material plate item with thickness.',
            ]);
        }

        return $materialItem;
    }

    protected function partMatchesMaterialItem(ProductionPartDefinition $part, array $profile, Item $materialItem): bool
    {
        if (! $this->sameDecimal($profile['thickness_mm'], $materialItem->thickness)) {
            return false;
        }

        if ((int) ($part->material_item_id ?? 0) > 0) {
            return (int) $part->material_item_id === (int) $materialItem->id;
        }

        if ($this->normalizedString($part->material_grade) !== '' && $this->normalizedString($materialItem->grade) !== '') {
            return $this->normalizedString($part->material_grade) === $this->normalizedString($materialItem->grade);
        }

        return true;
    }

    protected function partMaterialProfile(ProductionPartDefinition $part, array $row): array
    {
        $materialCategory = strtolower(trim((string) ($part->material_category ?? '')));
        $partType = strtolower(trim((string) ($part->part_type ?? '')));
        $thickness = $this->floatOrNull($row['thickness_mm'] ?? null) ?? $this->floatOrNull($part->thickness_mm);
        $width = $this->floatOrNull($row['cut_width_mm'] ?? null) ?? $this->floatOrNull($part->width_mm);
        $length = $this->floatOrNull($row['cut_length_mm'] ?? null) ?? $this->floatOrNull($part->length_mm);
        $grade = $part->material_grade ?: ($part->materialItem?->grade ?: null);

        if ($materialCategory === '') {
            $materialCategory = match ($partType) {
                'cuttable_plate' => 'steel_plate',
                'section' => 'steel_section',
                default => $materialCategory,
            };
        }

        return [
            'material_category' => $materialCategory,
            'grade' => $grade,
            'thickness_mm' => $thickness,
            'width_mm' => $width,
            'length_mm' => $length,
            'is_raw' => in_array($materialCategory, ['steel_plate', 'steel_section'], true),
        ];
    }

    protected function buildCutSizeText(array $profile, array $row): ?string
    {
        if ($profile['material_category'] === 'steel_plate') {
            $width = $this->floatOrNull($row['cut_width_mm'] ?? null) ?? $profile['width_mm'];
            $length = $this->floatOrNull($row['cut_length_mm'] ?? null) ?? $profile['length_mm'];

            if ($width && $length) {
                return rtrim(rtrim(number_format($width, 3, '.', ''), '0'), '.') . ' x '
                    . rtrim(rtrim(number_format($length, 3, '.', ''), '0'), '.') . ' mm';
            }
        }

        if ($profile['material_category'] === 'steel_section' && $profile['length_mm']) {
            return rtrim(rtrim(number_format((float) $profile['length_mm'], 3, '.', ''), '0'), '.') . ' mm';
        }

        return null;
    }

    protected function sameDecimal(mixed $left, mixed $right): bool
    {
        $left = $this->floatOrNull($left);
        $right = $this->floatOrNull($right);

        if ($left === null || $right === null) {
            return $left === $right;
        }

        return abs($left - $right) < 0.0001;
    }

    protected function normalizedString(mixed $value): string
    {
        return strtoupper(trim((string) ($value ?? '')));
    }

    protected function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    protected function partMapForPlannedPlates(Project $project, array $data)
    {
        $partIds = collect($data['planned_plates'] ?? [])
            ->pluck('allocations')
            ->flatten(1)
            ->pluck('part_definition_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return ProductionPartDefinition::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $partIds)
            ->with(['materialItem:id,code,name,grade,thickness,density,weight_per_meter'])
            ->get()
            ->keyBy('id');
    }

    protected function assignPlateRefs(string $planNumber, array $plates): array
    {
        return collect($plates)->values()->map(function (array $plate, int $index) use ($planNumber) {
            $ref = trim((string) ($plate['plate_ref'] ?? ''));
            $plate['plate_ref'] = $ref !== '' ? $ref : sprintf('%s-P%02d', $planNumber, $index + 1);

            foreach ($plate['allocations'] as $allocationIndex => $allocation) {
                $plate['allocations'][$allocationIndex]['planned_blank_ref'] = $plate['plate_ref'];
                $plate['allocations'][$allocationIndex]['allocation_group'] = $plate['plate_ref'];
            }

            return $plate;
        })->all();
    }

    protected function syncPlannedPlates(ProductionCuttingPlan $plan, array $plannedPlates): void
    {
        ProductionCuttingPlanAllocation::query()
            ->where('cutting_plan_id', $plan->id)
            ->delete();

        ProductionCuttingPlanPlate::query()
            ->where('cutting_plan_id', $plan->id)
            ->delete();

        foreach ($plannedPlates as $plateRow) {
            $plate = ProductionCuttingPlanPlate::query()->create([
                'cutting_plan_id' => $plan->id,
                'plate_ref' => $plateRow['plate_ref'],
                'planned_width_mm' => $plateRow['planned_width_mm'],
                'planned_length_mm' => $plateRow['planned_length_mm'],
                'planned_qty' => $plateRow['planned_qty'],
                'remarks' => $plateRow['remarks'] ?? null,
            ]);

            foreach ($plateRow['allocations'] as $row) {
                ProductionCuttingPlanAllocation::query()->create([
                    'cutting_plan_id' => $plan->id,
                    'cutting_plan_plate_id' => $plate->id,
                    'part_definition_id' => (int) $row['part_definition_id'],
                    'planned_qty' => $row['planned_qty'],
                    'planned_blank_ref' => $row['planned_blank_ref'] ?: null,
                    'planned_blank_width_mm' => $row['planned_blank_width_mm'] ?: null,
                    'planned_blank_length_mm' => $row['planned_blank_length_mm'] ?: null,
                    'mother_stock_item_id' => null,
                    'cut_size_text' => $row['cut_size_text'] ?? null,
                    'cut_width_mm' => $row['cut_width_mm'] ?: null,
                    'cut_length_mm' => $row['cut_length_mm'] ?: null,
                    'thickness_mm' => $row['thickness_mm'] ?: null,
                    'allocation_group' => $row['allocation_group'] ?? null,
                    'remarks' => $row['remarks'] ?? null,
                ]);
            }
        }
    }
}
