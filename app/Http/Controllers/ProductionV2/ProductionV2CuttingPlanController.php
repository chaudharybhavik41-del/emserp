<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionCuttingPlan;
use App\Models\ProductionV2\ProductionCuttingPlanAllocation;
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
                'mixed' => (clone $baseQuery)->where('source_mode', 'mixed')->count(),
                'allocations' => ProductionCuttingPlanAllocation::query()
                    ->whereHas('cuttingPlan', fn ($query) => $query->where('project_id', $project->id))
                    ->count(),
            ],
        ]);
    }

    public function create(Project $project)
    {
        return view('production_v2.cutting_plans.form', $this->formData($project, new ProductionCuttingPlan([
            'source_mode' => 'mixed',
            'status' => 'draft',
            'revision_no' => 1,
        ])));
    }

    public function store(Request $request, Project $project)
    {
        $data = $this->validatedData($request, $project);

        $partMap = ProductionPartDefinition::query()
            ->where('project_id', $project->id)
            ->whereIn('id', collect($data['allocations'])->pluck('part_definition_id')->map(fn ($id) => (int) $id)->unique()->values())
            ->with(['materialItem:id,code,name,grade,thickness,density,weight_per_meter'])
            ->get()
            ->keyBy('id');

        $normalized = $this->normalizeAndValidateAllocations($project, $data, $partMap);
        $data['grade'] = $normalized['grade'];
        $data['thickness_mm'] = $normalized['thickness_mm'];
        $data['allocations'] = $normalized['allocations'];

        $plan = DB::transaction(function () use ($project, $data) {
            $plan = ProductionCuttingPlan::query()->create([
                'project_id' => $project->id,
                'plan_number' => $data['plan_number'],
                'plan_date' => $data['plan_date'] ?? null,
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

            foreach ($data['allocations'] as $row) {
                ProductionCuttingPlanAllocation::query()->create([
                    'cutting_plan_id' => $plan->id,
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

            return $plan;
        });

        return redirect()
            ->route('projects.production-v2.cutting-plans.show', ['project' => $project->id, 'cuttingPlan' => $plan->id])
            ->with('success', 'Production V2 cutting plan created.');
    }

    public function show(Project $project, ProductionCuttingPlan $cuttingPlan)
    {
        abort_unless((int) $cuttingPlan->project_id === (int) $project->id, 404);
        $cuttingPlan->load(['allocations.partDefinition', 'allocations.motherStock', 'designRelease', 'releasedBy', 'previousRevision', 'supersededByRevision']);

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

        $cuttingPlan->load('allocations');

        return view('production_v2.cutting_plans.form', $this->formData($project, $cuttingPlan));
    }

    public function update(Request $request, Project $project, ProductionCuttingPlan $cuttingPlan)
    {
        abort_unless((int) $cuttingPlan->project_id === (int) $project->id, 404);
        abort_if($cuttingPlan->status === 'released', 403, 'Released cutting plans cannot be edited directly. Create a revision instead.');

        $data = $this->validatedData($request, $project, $cuttingPlan->id);

        $partMap = ProductionPartDefinition::query()
            ->where('project_id', $project->id)
            ->whereIn('id', collect($data['allocations'])->pluck('part_definition_id')->map(fn ($id) => (int) $id)->unique()->values())
            ->with(['materialItem:id,code,name,grade,thickness,density,weight_per_meter'])
            ->get()
            ->keyBy('id');

        $normalized = $this->normalizeAndValidateAllocations($project, $data, $partMap);
        $data['grade'] = $normalized['grade'];
        $data['thickness_mm'] = $normalized['thickness_mm'];
        $data['allocations'] = $normalized['allocations'];

        DB::transaction(function () use ($cuttingPlan, $data) {
            $cuttingPlan->update([
                'plan_number' => $data['plan_number'],
                'plan_date' => $data['plan_date'] ?? null,
                'grade' => $data['grade'] ?? null,
                'thickness_mm' => $data['thickness_mm'] ?? null,
                'source_mode' => $data['source_mode'],
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            ProductionCuttingPlanAllocation::query()
                ->where('cutting_plan_id', $cuttingPlan->id)
                ->delete();

            foreach ($data['allocations'] as $row) {
                ProductionCuttingPlanAllocation::query()->create([
                    'cutting_plan_id' => $cuttingPlan->id,
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
        return [
            'project' => $project,
            'plan' => $cuttingPlan,
            'partDefinitions' => ProductionPartDefinition::query()
                ->where('project_id', $project->id)
                ->whereIn('status', ['approved', 'released'])
                ->with(['materialItem:id,code,name,grade,thickness,density,weight_per_meter'])
                ->orderBy('part_code')
                ->get(),
        ];
    }

    protected function validatedData(Request $request, Project $project, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'plan_number' => ['required', 'string', 'max:80'],
            'plan_date' => ['nullable', 'date'],
            'grade' => ['nullable', 'string', 'max:120'],
            'thickness_mm' => ['nullable', 'numeric', 'min:0'],
            'source_mode' => ['required', Rule::in(['fresh_plate', 'remnant', 'mixed'])],
            'status' => ['required', Rule::in(['draft', 'reviewed', 'approved', 'released', 'superseded', 'cancelled'])],
            'revision_no' => ['nullable', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.part_definition_id' => [
                'required',
                'integer',
                Rule::exists('production_v2_part_definitions', 'id')->where('project_id', $project->id),
            ],
            'allocations.*.planned_qty' => ['required', 'numeric', 'min:0.001'],
            'allocations.*.planned_blank_ref' => ['nullable', 'string', 'max:120'],
            'allocations.*.planned_blank_width_mm' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.planned_blank_length_mm' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.cut_size_text' => ['nullable', 'string', 'max:200'],
            'allocations.*.cut_width_mm' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.cut_length_mm' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.thickness_mm' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.allocation_group' => ['nullable', 'string', 'max:80'],
            'allocations.*.remarks' => ['nullable', 'string'],
        ]);

        $revisionNo = (int) $request->input('revision_no', 1);
        $data['revision_no'] = $revisionNo;

        $planNumberRule = Rule::unique('production_v2_cutting_plans', 'plan_number')
            ->where('project_id', $project->id)
            ->where('revision_no', $revisionNo);

        if ($ignoreId) {
            $planNumberRule = $planNumberRule->ignore($ignoreId);
        }

        validator(
            ['plan_number' => $data['plan_number']],
            ['plan_number' => [$planNumberRule]]
        )->validate();

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

    protected function normalizeAndValidateAllocations(Project $project, array $data, $partMap): array
    {
        $errors = [];
        $normalizedAllocations = [];
        $rawProfiles = [];

        foreach ($data['allocations'] as $index => $row) {
            $partId = (int) $row['part_definition_id'];
            /** @var \App\Models\ProductionV2\ProductionPartDefinition|null $part */
            $part = $partMap->get($partId);
            if (! $part) {
                $errors["allocations.$index.part_definition_id"] = 'Selected part was not found.';
                continue;
            }

            if (! $part->is_cuttable) {
                $errors["allocations.$index.part_definition_id"] = 'Only cuttable parts can be used in a cutting plan.';
                continue;
            }

            $profile = $this->partMaterialProfile($part, $row);
            if (! $profile['is_raw']) {
                $errors["allocations.$index.part_definition_id"] = 'Cutting plans are only allowed for raw cuttable parts like plates and sections.';
                continue;
            }

            if ($profile['material_category'] === 'steel_plate') {
                $cutWidth = $this->floatOrNull($row['cut_width_mm'] ?? null) ?? $profile['width_mm'];
                $cutLength = $this->floatOrNull($row['cut_length_mm'] ?? null) ?? $profile['length_mm'];
                if (! $cutWidth || ! $cutLength) {
                    $errors["allocations.$index.cut_width_mm"] = 'Plate allocations need cut width and cut length.';
                }
                $row['cut_width_mm'] = $cutWidth;
                $row['cut_length_mm'] = $cutLength;
            }

            $row['thickness_mm'] = $profile['thickness_mm'];
            if (blank($row['cut_size_text'] ?? null)) {
                $row['cut_size_text'] = $this->buildCutSizeText($profile, $row);
            }

            $rawProfiles[] = $profile;
            $normalizedAllocations[] = $row;
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $planProfile = $this->resolvePlanProfile($rawProfiles, $data);

        return [
            'grade' => $planProfile['grade'],
            'thickness_mm' => $planProfile['thickness_mm'],
            'allocations' => $normalizedAllocations,
        ];
    }

    protected function resolvePlanProfile(array $rawProfiles, array $data): array
    {
        if (empty($rawProfiles)) {
            return [
                'grade' => $data['grade'] ?? null,
                'thickness_mm' => $data['thickness_mm'] ?? null,
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

        $grade = $data['grade'] ?? $first['grade'];
        $thickness = $data['thickness_mm'] ?? $first['thickness_mm'];

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
}
