<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionCuttingPlanAllocation;
use App\Models\ProductionV2\ProductionMaterialRequirement;
use App\Models\ProductionV2\ProductionMaterialRequirementItem;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Support\ProductionV2\RevisionImpactAnalyzer;
use App\Support\ProductionV2\RevisionDraftBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductionV2MaterialRequirementController extends Controller
{
    public function __construct(
        protected RevisionImpactAnalyzer $revisionImpactAnalyzer,
        protected RevisionDraftBuilder $revisionDraftBuilder
    )
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.view')->only(['index', 'show']);
        $this->middleware('permission:production.plan.create')->only(['create', 'store', 'release']);
        $this->middleware('permission:production.plan.update')->only(['edit', 'update', 'revise']);
    }

    public function index(Project $project)
    {
        $rows = ProductionMaterialRequirement::query()
            ->where('project_id', $project->id)
            ->with(['releasedBy', 'designRelease'])
            ->withCount('items')
            ->latest('requirement_date')
            ->latest('id')
            ->paginate(25);

        return view('production_v2.material_requirements.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }

    public function create(Project $project)
    {
        return view('production_v2.material_requirements.form', $this->formData(
            $project,
            new ProductionMaterialRequirement([
                'basis' => 'design_snapshot',
                'status' => 'approved',
                'revision_no' => 1,
            ]),
            $this->seedRows($project)->toArray()
        ));
    }

    public function store(Request $request, Project $project)
    {
        $data = $this->validatedData($request, $project);

        $rows = collect($data['rows'])
            ->filter(fn ($row) => (float) ($row['required_qty'] ?? 0) > 0)
            ->values();

        $requirement = DB::transaction(function () use ($project, $data, $rows) {
            $requirement = ProductionMaterialRequirement::query()->create([
                'project_id' => $project->id,
                'requirement_number' => $data['requirement_number'],
                'requirement_date' => $data['requirement_date'],
                'basis' => $data['basis'],
                'status' => $data['status'],
                'revision_no' => (int) ($data['revision_no'] ?? 1),
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $requirement->forceFill(['revision_root_id' => $requirement->id])->save();

            foreach ($rows as $row) {
                ProductionMaterialRequirementItem::query()->create([
                    'material_requirement_id' => $requirement->id,
                    'material_item_id' => $row['material_item_id'] ?: null,
                    'material_category' => $row['material_category'] ?: null,
                    'material_grade' => $row['material_grade'] ?: null,
                    'thickness_mm' => $row['thickness_mm'] ?: null,
                    'width_mm' => $row['width_mm'] ?: null,
                    'length_mm' => $row['length_mm'] ?: null,
                    'profile_text' => $row['profile_text'] ?: null,
                    'part_revision_root_ids_json' => $this->normalizePartRevisionRootIds($row['part_revision_root_ids_json'] ?? null),
                    'required_qty' => $row['required_qty'],
                    'required_weight_kg' => $row['required_weight_kg'] ?: null,
                    'planned_cut_qty_snapshot' => $row['planned_cut_qty_snapshot'] ?: 0,
                    'remarks' => $row['remarks'] ?? null,
                ]);
            }

            return $requirement;
        });

        return redirect()
            ->route('projects.production-v2.material-requirements.show', ['project' => $project->id, 'materialRequirement' => $requirement->id])
            ->with('success', 'Material requirement created.');
    }

    public function show(Project $project, ProductionMaterialRequirement $materialRequirement)
    {
        abort_unless((int) $materialRequirement->project_id === (int) $project->id, 404);
        $materialRequirement->load(['items.materialItem', 'releasedBy', 'designRelease', 'previousRevision', 'supersededByRevision']);

        $revisionHistory = ProductionMaterialRequirement::query()
            ->where('project_id', $project->id)
            ->where('revision_root_id', $materialRequirement->revision_root_id ?: $materialRequirement->id)
            ->orderBy('revision_no')
            ->orderBy('id')
            ->get();

        return view('production_v2.material_requirements.show', [
            'project' => $project,
            'materialRequirement' => $materialRequirement,
            'revisionHistory' => $revisionHistory,
            'dependencyImpact' => $this->revisionImpactAnalyzer->materialRequirementDependencyImpact($project, $materialRequirement),
        ]);
    }

    public function edit(Project $project, ProductionMaterialRequirement $materialRequirement)
    {
        abort_unless((int) $materialRequirement->project_id === (int) $project->id, 404);
        abort_if($materialRequirement->status === 'released', 403, 'Released material requirements cannot be edited directly. Create a revision instead.');

        $materialRequirement->load('items.materialItem');

        return view('production_v2.material_requirements.form', $this->formData(
            $project,
            $materialRequirement,
            $materialRequirement->items->map(function (ProductionMaterialRequirementItem $row) {
                return [
                    'material_item_id' => $row->material_item_id,
                    'material_item_label' => $row->materialItem?->code
                        ? ($row->materialItem->code . ' - ' . $row->materialItem->name)
                        : ($row->materialItem?->name ?: '-'),
                    'material_category' => $row->material_category,
                    'material_grade' => $row->material_grade,
                    'thickness_mm' => $row->thickness_mm,
                    'width_mm' => $row->width_mm,
                    'length_mm' => $row->length_mm,
                    'profile_text' => $row->profile_text,
                    'part_revision_root_ids_json' => json_encode($row->part_revision_root_ids_json ?? []),
                    'required_qty' => $row->required_qty,
                    'required_weight_kg' => $row->required_weight_kg,
                    'planned_cut_qty_snapshot' => $row->planned_cut_qty_snapshot,
                    'remarks' => $row->remarks,
                ];
            })->all()
        ));
    }

    public function update(Request $request, Project $project, ProductionMaterialRequirement $materialRequirement)
    {
        abort_unless((int) $materialRequirement->project_id === (int) $project->id, 404);
        abort_if($materialRequirement->status === 'released', 403, 'Released material requirements cannot be edited directly. Create a revision instead.');

        $data = $this->validatedData($request, $project, $materialRequirement->id);
        $rows = collect($data['rows'])
            ->filter(fn ($row) => (float) ($row['required_qty'] ?? 0) > 0)
            ->values();

        DB::transaction(function () use ($materialRequirement, $data, $rows) {
            $materialRequirement->update([
                'requirement_number' => $data['requirement_number'],
                'requirement_date' => $data['requirement_date'],
                'basis' => $data['basis'],
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            ProductionMaterialRequirementItem::query()
                ->where('material_requirement_id', $materialRequirement->id)
                ->delete();

            foreach ($rows as $row) {
                ProductionMaterialRequirementItem::query()->create([
                    'material_requirement_id' => $materialRequirement->id,
                    'material_item_id' => $row['material_item_id'] ?: null,
                    'material_category' => $row['material_category'] ?: null,
                    'material_grade' => $row['material_grade'] ?: null,
                    'thickness_mm' => $row['thickness_mm'] ?: null,
                    'width_mm' => $row['width_mm'] ?: null,
                    'length_mm' => $row['length_mm'] ?: null,
                    'profile_text' => $row['profile_text'] ?: null,
                    'part_revision_root_ids_json' => $this->normalizePartRevisionRootIds($row['part_revision_root_ids_json'] ?? null),
                    'required_qty' => $row['required_qty'],
                    'required_weight_kg' => $row['required_weight_kg'] ?: null,
                    'planned_cut_qty_snapshot' => $row['planned_cut_qty_snapshot'] ?: 0,
                    'remarks' => $row['remarks'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('projects.production-v2.material-requirements.show', ['project' => $project->id, 'materialRequirement' => $materialRequirement->id])
            ->with('success', 'Material requirement updated.');
    }

    public function release(Project $project, ProductionMaterialRequirement $materialRequirement)
    {
        abort_unless((int) $materialRequirement->project_id === (int) $project->id, 404);

        if ($materialRequirement->status === 'released') {
            return back()->with('success', 'Material requirement is already released.');
        }

        abort_unless($materialRequirement->status === 'approved', 403, 'Only approved material requirements can be released.');

        $staleRoots = $this->revisionImpactAnalyzer->materialRequirementDependencyImpact($project, $materialRequirement);
        if ($staleRoots->isNotEmpty()) {
            throw ValidationException::withMessages([
                'status' => 'Material requirement is stale against newer part revisions: ' . $staleRoots->pluck('part_code')->filter()->unique()->implode(', ') . '.',
            ]);
        }

        $materialRequirement->update([
            'status' => 'released',
            'released_by' => auth()->id(),
            'released_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        $this->supersedeReleasedRevisions($materialRequirement);

        return redirect()
            ->route('projects.production-v2.material-requirements.show', ['project' => $project->id, 'materialRequirement' => $materialRequirement->id])
            ->with('success', 'Material requirement released.');
    }

    public function revise(Project $project, ProductionMaterialRequirement $materialRequirement)
    {
        abort_unless((int) $materialRequirement->project_id === (int) $project->id, 404);
        abort_unless(in_array($materialRequirement->status, ['released', 'superseded'], true), 403);

        $result = $this->revisionDraftBuilder->createMaterialRequirementRevision($materialRequirement, (int) auth()->id());
        /** @var \App\Models\ProductionV2\ProductionMaterialRequirement $revision */
        $revision = $result['revision'];

        return redirect()
            ->route('projects.production-v2.material-requirements.edit', ['project' => $project->id, 'materialRequirement' => $revision->id])
            ->with('success', $this->revisionDraftMessage($materialRequirement, (bool) $result['refreshed'], (int) $result['row_count']));
    }

    protected function seedRows(Project $project): Collection
    {
        $plannedCuts = ProductionCuttingPlanAllocation::query()
            ->select('part_definition_id', DB::raw('SUM(planned_qty) as planned_cut_qty'))
            ->whereHas('cuttingPlan', fn ($query) => $query->where('project_id', $project->id)->whereIn('status', ['approved', 'released']))
            ->groupBy('part_definition_id')
            ->pluck('planned_cut_qty', 'part_definition_id');

        $parts = ProductionPartDefinition::query()
            ->where('project_id', $project->id)
            ->whereIn('status', ['approved', 'released'])
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
                    'material_item_label' => $sample->materialItem?->code
                        ? ($sample->materialItem->code . ' - ' . $sample->materialItem->name)
                        : ($sample->materialItem?->name ?: '-'),
                    'material_category' => $sample->material_category,
                    'material_grade' => $sample->material_grade,
                    'thickness_mm' => $sample->thickness_mm,
                    'width_mm' => $sample->width_mm,
                    'length_mm' => $sample->length_mm,
                    'profile_text' => $this->profileText($sample),
                    'part_revision_root_ids_json' => json_encode(
                        $group->map(fn (ProductionPartDefinition $part) => (int) ($part->revision_root_id ?: $part->id))
                            ->unique()
                            ->values()
                            ->all()
                    ),
                    'required_qty' => round($requiredQty, 3),
                    'required_weight_kg' => round($requiredWeight, 3),
                    'planned_cut_qty_snapshot' => round($plannedCutQty, 3),
                    'remarks' => null,
                ];
            })
            ->values();
    }

    protected function nextRequirementNumber(Project $project): string
    {
        $year = now()->year;
        $prefix = 'MR-' . strtoupper($project->code) . '-' . $year . '-';

        $last = ProductionMaterialRequirement::query()
            ->where('project_id', $project->id)
            ->where('requirement_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('requirement_number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
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

    protected function validatedData(Request $request, Project $project, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'requirement_number' => ['required', 'string', 'max:80'],
            'requirement_date' => ['required', 'date'],
            'basis' => ['required', Rule::in(['design_snapshot', 'released_design'])],
            'status' => ['required', Rule::in(['draft', 'approved'])],
            'revision_no' => ['nullable', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.material_item_id' => ['nullable', 'integer', 'exists:items,id'],
            'rows.*.material_category' => ['nullable', 'string', 'max:120'],
            'rows.*.material_grade' => ['nullable', 'string', 'max:120'],
            'rows.*.thickness_mm' => ['nullable', 'numeric', 'min:0'],
            'rows.*.width_mm' => ['nullable', 'numeric', 'min:0'],
            'rows.*.length_mm' => ['nullable', 'numeric', 'min:0'],
            'rows.*.profile_text' => ['nullable', 'string', 'max:150'],
            'rows.*.part_revision_root_ids_json' => ['nullable'],
            'rows.*.required_qty' => ['required', 'numeric', 'min:0.001'],
            'rows.*.required_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'rows.*.planned_cut_qty_snapshot' => ['nullable', 'numeric', 'min:0'],
            'rows.*.remarks' => ['nullable', 'string'],
        ]);

        $revisionNo = (int) $request->input('revision_no', 1);
        $data['revision_no'] = $revisionNo;

        $rule = Rule::unique('production_v2_material_requirements', 'requirement_number')
            ->where('project_id', $project->id)
            ->where('revision_no', $revisionNo);

        if ($ignoreId) {
            $rule = $rule->ignore($ignoreId);
        }

        validator(
            ['requirement_number' => $data['requirement_number']],
            ['requirement_number' => [$rule]]
        )->validate();

        return $data;
    }

    protected function formData(Project $project, ProductionMaterialRequirement $materialRequirement, array $seedRows): array
    {
        return [
            'project' => $project,
            'materialRequirement' => $materialRequirement,
            'defaultRequirementNumber' => $materialRequirement->requirement_number ?: $this->nextRequirementNumber($project),
            'seedRows' => $seedRows,
        ];
    }

    protected function supersedeReleasedRevisions(ProductionMaterialRequirement $materialRequirement): void
    {
        $rootId = $materialRequirement->revision_root_id ?: $materialRequirement->id;

        ProductionMaterialRequirement::query()
            ->where('project_id', $materialRequirement->project_id)
            ->where('id', '!=', $materialRequirement->id)
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
                'superseded_by_revision_id' => $materialRequirement->id,
                'superseded_at' => now(),
                'updated_at' => now(),
            ]);
    }

    protected function normalizePartRevisionRootIds(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return collect(is_array($value) ? $value : [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function revisionDraftMessage(ProductionMaterialRequirement $materialRequirement, bool $refreshed, int $rowCount): string
    {
        if ($refreshed) {
            return 'Revision draft created from released material requirement. Released-design snapshot refreshed with ' . $rowCount . ' row(s).';
        }

        return 'Revision draft created from released material requirement.';
    }
}
