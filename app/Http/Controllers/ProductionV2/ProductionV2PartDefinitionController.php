<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Project;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Support\ProductionV2\RevisionImpactAnalyzer;
use App\Support\ProductionV2\RouteSnapshotManager;
use App\Models\Uom;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionV2PartDefinitionController extends Controller
{
    public function __construct(
        protected RevisionImpactAnalyzer $revisionImpactAnalyzer,
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

        $baseQuery = ProductionPartDefinition::query()
            ->where('project_id', $project->id)
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($sub) use ($q) {
                    $sub->where('part_code', 'like', '%' . $q . '%')
                        ->orWhere('part_name', 'like', '%' . $q . '%')
                        ->orWhere('material_grade', 'like', '%' . $q . '%')
                        ->orWhere('drawing_ref', 'like', '%' . $q . '%');
                });
            });

        $parts = (clone $baseQuery)
            ->with(['uom', 'materialItem'])
            ->orderBy('part_code')
            ->paginate(25)
            ->withQueryString();

        return view('production_v2.parts.index', [
            'project' => $project,
            'parts' => $parts,
            'q' => $q,
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'released' => (clone $baseQuery)->where('status', 'released')->count(),
                'cuttable' => (clone $baseQuery)->where('is_cuttable', true)->count(),
            ],
        ]);
    }

    public function create(Project $project)
    {
        return view('production_v2.parts.form', $this->formData($project, new ProductionPartDefinition([
            'status' => 'draft',
            'part_type' => 'cuttable_plate',
            'is_interchangeable' => true,
            'is_cuttable' => true,
        ])));
    }

    public function store(Request $request, Project $project)
    {
        $data = $this->validatedData($request, $project);
        $data['project_id'] = $project->id;
        $data['revision_no'] = (int) ($data['revision_no'] ?? 1);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $part = ProductionPartDefinition::query()->create($data);
        $part->forceFill(['revision_root_id' => $part->id])->save();
        $this->routeSnapshotManager->syncPart($part);

        return redirect()
            ->route('projects.production-v2.parts.show', ['project' => $project->id, 'part' => $part->id])
            ->with('success', 'Production V2 part definition created.');
    }

    public function show(Project $project, ProductionPartDefinition $part)
    {
        abort_unless((int) $part->project_id === (int) $project->id, 404);
        $part->load(['uom', 'materialItem', 'designRelease', 'releasedBy', 'previousRevision', 'supersededByRevision', 'routeTemplate', 'routeSteps']);

        $revisionHistory = ProductionPartDefinition::query()
            ->where('project_id', $project->id)
            ->where('revision_root_id', $part->revision_root_id ?: $part->id)
            ->orderBy('revision_no')
            ->orderBy('id')
            ->get();

        return view('production_v2.parts.show', [
            'project' => $project,
            'part' => $part,
            'revisionHistory' => $revisionHistory,
            'usageImpact' => $this->revisionImpactAnalyzer->partUsageImpact($part),
        ]);
    }

    public function edit(Project $project, ProductionPartDefinition $part)
    {
        abort_unless((int) $part->project_id === (int) $project->id, 404);
        abort_if($part->status === 'released', 403, 'Released part definitions cannot be edited directly. Create a revision instead.');

        return view('production_v2.parts.form', $this->formData($project, $part));
    }

    public function update(Request $request, Project $project, ProductionPartDefinition $part)
    {
        abort_unless((int) $part->project_id === (int) $project->id, 404);
        abort_if($part->status === 'released', 403, 'Released part definitions cannot be edited directly. Create a revision instead.');

        $data = $this->validatedData($request, $project, $part->id);
        $data['updated_by'] = auth()->id();
        $part->update($data);
        $this->routeSnapshotManager->syncPart($part);

        return redirect()
            ->route('projects.production-v2.parts.show', ['project' => $project->id, 'part' => $part->id])
            ->with('success', 'Production V2 part definition updated.');
    }

    public function revise(Project $project, ProductionPartDefinition $part)
    {
        abort_unless((int) $part->project_id === (int) $project->id, 404);
        abort_unless(in_array($part->status, ['released', 'superseded'], true), 403);

        $revision = ProductionPartDefinition::query()->create([
            'project_id' => $part->project_id,
            'part_code' => $part->part_code,
            'part_name' => $part->part_name,
            'part_type' => $part->part_type,
            'uom_id' => $part->uom_id,
            'required_qty' => $part->required_qty,
            'description' => $part->description,
            'material_item_id' => $part->material_item_id,
            'material_grade' => $part->material_grade,
            'material_category' => $part->material_category,
            'thickness_mm' => $part->thickness_mm,
            'width_mm' => $part->width_mm,
            'length_mm' => $part->length_mm,
            'unit_weight_kg' => $part->unit_weight_kg,
            'unit_area_m2' => $part->unit_area_m2,
            'unit_cut_length_m' => $part->unit_cut_length_m,
            'unit_weld_length_m' => $part->unit_weld_length_m,
            'is_interchangeable' => $part->is_interchangeable,
            'is_cuttable' => $part->is_cuttable,
            'is_section_item' => $part->is_section_item,
            'is_bought_out' => $part->is_bought_out,
            'drawing_ref' => $part->drawing_ref,
            'route_template_id' => $part->route_template_id,
            'status' => 'draft',
            'revision_no' => ((int) $part->revision_no) + 1,
            'revision_root_id' => $part->revision_root_id ?: $part->id,
            'previous_revision_id' => $part->id,
            'remarks' => $part->remarks,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->routeSnapshotManager->clonePartSteps($part, $revision);

        return redirect()
            ->route('projects.production-v2.parts.edit', ['project' => $project->id, 'part' => $revision->id])
            ->with('success', 'Revision draft created from released part definition.');
    }

    protected function formData(Project $project, ProductionPartDefinition $part): array
    {
        return [
            'project' => $project,
            'part' => $part,
            'uoms' => Uom::query()->orderBy('code')->get(),
            'items' => Item::query()
                ->with(['type:id,code', 'category:id,code'])
                ->orderBy('name')
                ->limit(500)
                ->get([
                    'id',
                    'name',
                    'code',
                    'uom_id',
                    'grade',
                    'thickness',
                    'density',
                    'weight_per_meter',
                    'material_type_id',
                    'material_category_id',
                ]),
        ];
    }

    protected function validatedData(Request $request, Project $project, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'part_code' => ['required', 'string', 'max:120'],
            'part_name' => ['required', 'string', 'max:200'],
            'part_type' => ['required', 'string', 'max:60'],
            'uom_id' => ['nullable', 'integer', 'exists:uoms,id'],
            'required_qty' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'material_item_id' => ['nullable', 'integer', 'exists:items,id'],
            'material_grade' => ['nullable', 'string', 'max:120'],
            'material_category' => ['nullable', 'string', 'max:120'],
            'thickness_mm' => ['nullable', 'numeric', 'min:0'],
            'width_mm' => ['nullable', 'numeric', 'min:0'],
            'length_mm' => ['nullable', 'numeric', 'min:0'],
            'unit_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'unit_area_m2' => ['nullable', 'numeric', 'min:0'],
            'unit_cut_length_m' => ['nullable', 'numeric', 'min:0'],
            'unit_weld_length_m' => ['nullable', 'numeric', 'min:0'],
            'drawing_ref' => ['nullable', 'string', 'max:150'],
            'route_template_id' => [
                'nullable',
                'integer',
                Rule::exists('production_v2_route_templates', 'id')
                    ->where('project_id', $project->id)
                    ->where('applies_to', 'part'),
            ],
            'status' => ['required', Rule::in(['draft', 'reviewed', 'approved', 'released', 'superseded', 'active', 'obsolete'])],
            'revision_no' => ['nullable', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string'],
            'is_interchangeable' => ['nullable', 'boolean'],
            'is_cuttable' => ['nullable', 'boolean'],
            'is_section_item' => ['nullable', 'boolean'],
            'is_bought_out' => ['nullable', 'boolean'],
        ]);

        $revisionNo = (int) ($data['revision_no'] ?? ($request->input('revision_no') ?: 1));
        $data['revision_no'] = $revisionNo;

        $partCodeRule = Rule::unique('production_v2_part_definitions', 'part_code')
            ->where('project_id', $project->id)
            ->where('revision_no', $revisionNo);

        if ($ignoreId) {
            $partCodeRule = $partCodeRule->ignore($ignoreId);
        }

        validator(
            ['part_code' => $data['part_code']],
            ['part_code' => [$partCodeRule]]
        )->validate();

        $data['is_interchangeable'] = $request->boolean('is_interchangeable');
        $data['is_cuttable'] = $request->boolean('is_cuttable');
        $data['is_section_item'] = $request->boolean('is_section_item');
        $data['is_bought_out'] = $request->boolean('is_bought_out');

        $item = ! empty($data['material_item_id'])
            ? Item::query()->find((int) $data['material_item_id'])
            : null;

        $data = ProductionPartDefinition::applyMaterialItemDefaults($data, $item);

        return $data;
    }
}
