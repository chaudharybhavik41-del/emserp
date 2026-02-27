<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\BomTemplate;
use App\Models\BomTemplateItem;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BomTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:project.bom_template.view')->only(['index', 'show', 'createBomForm']);
        $this->middleware('permission:project.bom_template.create')->only(['create', 'store', 'storeFromBom', 'createBomStore']);
        $this->middleware('permission:project.bom_template.update')->only(['edit', 'update']);
        $this->middleware('permission:project.bom_template.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $query = BomTemplate::query()->orderByDesc('created_at');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($structure = $request->get('structure_type')) {
            $query->where('structure_type', $structure);
        }

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('template_code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        $templates = $query->paginate(20)->withQueryString();

        $statuses = ['draft', 'approved', 'archived'];

        return view('bom-templates.index', compact('templates', 'statuses'));
    }

    public function create(): View
    {
        $template = new BomTemplate();
        $template->status = 'draft';

        return view('bom-templates.create', compact('template'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'structure_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,approved,archived'],
        ]);

        $structure = $data['structure_type'] ?? null;

        $template = new BomTemplate();
        $template->template_code = BomTemplate::generateCode($structure);
        $template->name = $data['name'];
        $template->structure_type = $structure;
        $template->description = $data['description'] ?? null;
        $template->status = $data['status'];
        $template->total_weight = 0;
        $template->created_by = auth()->id();
        $template->save();

        return redirect()
            ->route('bom-templates.show', $template)
            ->with('success', 'BOM template created.');
    }

    public function show(BomTemplate $bomTemplate): View
    {
        $bomTemplate->load([
            'items' => function ($q) {
                $q->orderBy('level')->orderBy('sequence_no');
            },
            'items.item',
            'items.uom',
        ]);

        $assemblyWeights = $bomTemplate->assembly_weights;
        $categorySummary = $bomTemplate->category_summary;

        return view('bom-templates.show', [
            'template' => $bomTemplate,
            'assemblyWeights' => $assemblyWeights,
            'categorySummary' => $categorySummary,
        ]);
    }

    public function edit(BomTemplate $bomTemplate): View
    {
        return view('bom-templates.edit', ['template' => $bomTemplate]);
    }

    public function update(Request $request, BomTemplate $bomTemplate): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'structure_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,approved,archived'],
        ]);

        $bomTemplate->name = $data['name'];
        $bomTemplate->structure_type = $data['structure_type'] ?? null;
        $bomTemplate->description = $data['description'] ?? null;
        $bomTemplate->status = $data['status'];

        $bomTemplate->save();

        return redirect()
            ->route('bom-templates.show', $bomTemplate)
            ->with('success', 'BOM template updated.');
    }

    public function destroy(BomTemplate $bomTemplate): RedirectResponse
    {
        $bomTemplate->delete();

        return redirect()
            ->route('bom-templates.index')
            ->with('success', 'BOM template deleted.');
    }

    /**
     * Save an existing BOM as a template in the library.
     */
    public function storeFromBom(Project $project, Bom $bom): RedirectResponse
    {
        if ($bom->project_id !== $project->id) {
            abort(404);
        }

        $bom->load('items');

        if ($bom->items->isEmpty()) {
            return redirect()
                ->route('projects.boms.show', [$project, $bom])
                ->with('error', 'Cannot create template from empty BOM.');
        }

        // Guess structure type from first top-level assembly
        $topAssembly = $bom->items
            ->where('level', 0)
            ->sortBy('sequence_no')
            ->first();

        $structureType = $topAssembly?->assembly_type ?: null;

        $template = new BomTemplate();
        $template->template_code = BomTemplate::generateCode($structureType);
        $template->name = $bom->bom_number;
        $template->structure_type = $structureType;
        $template->description = 'Generated from BOM ' . $bom->bom_number;
        $template->status = 'draft';
        $template->total_weight = $bom->total_weight;
        $template->metadata = [
            'source_bom_id' => $bom->id,
            'source_project_id' => $project->id,
        ];
        $template->created_by = auth()->id();
        $template->save();

        // Clone items with preserved hierarchy
        $idMap = [];

        foreach ($bom->items as $item) {
            $newItem = new BomTemplateItem([
                'bom_template_id' => $template->id,
                'parent_item_id' => null, // temporary
                'level' => $item->level,
                'sequence_no' => $item->sequence_no,
                'item_code' => $item->item_code,
                'description' => $item->description,
                'assembly_type' => $item->assembly_type,
                'drawing_number' => $item->drawing_number,
                'drawing_revision' => $item->drawing_revision,
                'material_category' => $item->material_category,
                'item_id' => $item->item_id,
                'uom_id' => $item->uom_id,
                'grade' => $item->grade,
                'dimensions' => $item->dimensions,
                'quantity' => $item->quantity,
                'unit_weight' => $item->unit_weight,
                'total_weight' => $item->total_weight,
                'unit_area_m2' => $item->unit_area_m2,
                'total_area_m2' => $item->total_area_m2,
                'unit_cut_length_m' => $item->unit_cut_length_m,
                'total_cut_length_m' => $item->total_cut_length_m,
                'unit_weld_length_m' => $item->unit_weld_length_m,
                'total_weld_length_m' => $item->total_weld_length_m,
                'scrap_percentage' => $item->scrap_percentage,
                'procurement_type' => $item->procurement_type,
                'material_source' => $item->material_source,
                'is_billable' => $item->is_billable,
                'remarks' => $item->remarks,
            ]);

            $newItem->save();

            $idMap[$item->id] = $newItem->id;
        }

        // Fix parent relations
        foreach ($bom->items as $oldItem) {
            $newItemId = $idMap[$oldItem->id] ?? null;
            if (! $newItemId) {
                continue;
            }

            $newItem = BomTemplateItem::find($newItemId);
            if (! $newItem) {
                continue;
            }

            if ($oldItem->parent_item_id && isset($idMap[$oldItem->parent_item_id])) {
                $newItem->parent_item_id = $idMap[$oldItem->parent_item_id];
                $newItem->save();
            }
        }

        $template->recalculateTotalWeight();

        return redirect()
            ->route('bom-templates.show', $template)
            ->with('success', 'Template created from BOM ' . $bom->bom_number . '.');
    }

    /**
     * Show form to create a BOM for a project from this template.
     */
    public function createBomForm(BomTemplate $bomTemplate): View
    {
        $projects = Project::orderBy('code')->get();

        return view('bom-templates.create_bom', [
            'template' => $bomTemplate,
            'projects' => $projects,
        ]);
    }

    /**
     * Create a BOM on a specific project from this template.
     */
    public function createBomStore(Request $request, BomTemplate $bomTemplate): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'remarks' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($data['project_id']);

        $bom = new Bom();
        $bom->project_id = $project->id;
        $bom->bom_number = Bom::generateNumberForProject($project);
        $bom->version = 1;
        $bom->status = \App\Enums\BomStatus::DRAFT;
        $bom->metadata = [
            'remarks' => $data['remarks'] ?? ('Created from template ' . $bomTemplate->template_code),
            'source_template_id' => $bomTemplate->id,
        ];
        $bom->total_weight = 0;
        $bom->save();

        $bomTemplate->load('items');
        $idMap = [];

        // First pass: clone items
        foreach ($bomTemplate->items as $item) {
            $newItem = new BomItem([
                'bom_id' => $bom->id,
                'parent_item_id' => null, // temporary
                'level' => $item->level,
                'sequence_no' => $item->sequence_no,
                'item_code' => $item->item_code,
                'description' => $item->description,
                'assembly_type' => $item->assembly_type,
                'drawing_number' => $item->drawing_number,
                'drawing_revision' => $item->drawing_revision,
                'material_category' => $item->material_category,
                'item_id' => $item->item_id,
                'uom_id' => $item->uom_id,
                'grade' => $item->grade,
                'dimensions' => $item->dimensions,
                'quantity' => $item->quantity,
                'unit_weight' => $item->unit_weight,
                'total_weight' => $item->total_weight,
                'unit_area_m2' => $item->unit_area_m2,
                'total_area_m2' => $item->total_area_m2,
                'unit_cut_length_m' => $item->unit_cut_length_m,
                'total_cut_length_m' => $item->total_cut_length_m,
                'unit_weld_length_m' => $item->unit_weld_length_m,
                'total_weld_length_m' => $item->total_weld_length_m,
                'scrap_percentage' => $item->scrap_percentage,
                'procurement_type' => $item->procurement_type,
                'material_source' => $item->material_source,
                'is_billable' => $item->is_billable,
                'remarks' => $item->remarks,
            ]);

            $newItem->save();

            $idMap[$item->id] = $newItem->id;
        }

        // Second pass: fix parent relations
        foreach ($bomTemplate->items as $oldItem) {
            $newItemId = $idMap[$oldItem->id] ?? null;
            if (! $newItemId) {
                continue;
            }

            $newItem = BomItem::find($newItemId);
            if (! $newItem) {
                continue;
            }

            if ($oldItem->parent_item_id && isset($idMap[$oldItem->parent_item_id])) {
                $newItem->parent_item_id = $idMap[$oldItem->parent_item_id];
                $newItem->save();
            }
        }

        $bom->recalculateTotalWeight();

        return redirect()
            ->route('projects.boms.show', [$project, $bom])
            ->with('success', 'BOM created from template ' . $bomTemplate->template_code . '.');
    }
}
