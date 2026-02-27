<?php

namespace App\Http\Controllers;

use App\Enums\BomStatus;
use App\Http\Requests\StoreBomRequest;
use App\Http\Requests\UpdateBomRequest;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Project;
use App\Models\Tasks\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Enums\BomItemMaterialCategory;


class BomController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:project.bom.view')->only(['index', 'show', 'export', 'exportAssembly', 'copyForm']);
        $this->middleware('permission:project.bom.create')->only(['create', 'store', 'cloneVersion', 'copyStore']);
        $this->middleware('permission:project.bom.update')->only(['edit', 'update']);
        $this->middleware('permission:project.bom.finalize')->only(['finalize']);
        $this->middleware('permission:project.bom.delete')->only(['destroy']);
    }

    protected function ensureProjectBom(Project $project, Bom $bom): Bom
    {
        if ($bom->project_id !== $project->id) {
            abort(404);
        }

        return $bom;
    }

    public function index(Project $project, Request $request): View
    {
        $query = $project->boms()->orderByDesc('created_at');

        $statusFilter = $request->get('status');
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $boms = $query->paginate(20)->withQueryString();

        $statuses = BomStatus::cases();

        return view('projects.boms.index', compact(
            'project',
            'boms',
            'statuses',
            'statusFilter'
        ));
    }

    public function create(Project $project): View
    {
        $bom = new Bom();
        $bom->project_id = $project->id;
        $bom->version = 1;
        $bom->status = BomStatus::DRAFT;

        return view('projects.boms.create', compact('project', 'bom'));
    }

    public function store(StoreBomRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();

        $bom = new Bom();
        $bom->project_id = $project->id;
        $bom->bom_number = Bom::generateNumberForProject($project);
        $bom->version = $data['version'] ?? 1;
        $bom->status = BomStatus::DRAFT;
        $bom->metadata = [
            'remarks' => $data['metadata']['remarks'] ?? null,
        ];
        $bom->total_weight = 0;

        $bom->save();

        return redirect()
            ->route('projects.boms.show', [$project, $bom])
            ->with('success', 'BOM created successfully.');
    }

    public function show(Project $project, Bom $bom): View
    {
        $this->ensureProjectBom($project, $bom);

        $bom->load([
            'items' => function ($q) {
                $q->orderBy('level')->orderBy('sequence_no');
            },
            'items.item',
            'items.uom',
        ]);

        $assemblyWeights = $bom->assembly_weights;
        $categorySummary = $bom->category_summary;
        $taskStats = [
            'total' => 0,
            'open' => 0,
            'completed' => 0,
            'overdue' => 0,
        ];
        $recentTasks = collect();

        if (auth()->user()?->can('tasks.view') && Schema::hasTable('tasks')) {
            $taskQuery = Task::query()
                ->with(['status', 'priority', 'assignee'])
                ->where('project_id', $project->id)
                ->where('bom_id', $bom->id)
                ->notArchived();

            $taskStats = [
                'total' => (clone $taskQuery)->count(),
                'open' => (clone $taskQuery)->open()->count(),
                'completed' => (clone $taskQuery)->closed()->count(),
                'overdue' => (clone $taskQuery)->overdue()->count(),
            ];

            $recentTasks = (clone $taskQuery)
                ->orderByDesc('updated_at')
                ->limit(6)
                ->get();
        }

        return view('projects.boms.show', compact(
            'project',
            'bom',
            'assemblyWeights',
            'categorySummary',
            'taskStats',
            'recentTasks'
        ));
    }
	
 	 public function requirements(Project $project, Bom $bom): View
	{
    $this->ensureProjectBom($project, $bom);

    $bom->load([
        'items' => function ($q) {
            $q->orderBy('level')->orderBy('sequence_no');
        },
        'items.item',
        'items.uom',
    ]);

    $items = $bom->items;


    // Map BOM items by id for effective qty calculations (avoids N+1)
    $itemsById = $items->keyBy('id')->all();

    $raw = [];
    $boughtOut = [];
    $consumables = [];

    foreach ($items as $item) {
        // Only leaf materials (ignore assemblies)
        if (! method_exists($item, 'isLeafMaterial') || ! $item->isLeafMaterial()) {
            continue;
        }

        $category = $item->material_category?->value;

        if (! $category) {
            continue;
        }

        // Basic data used in all groups
        $grade        = $item->grade;
        $dims         = $item->dimensions ?? [];
        $qty = method_exists($item, 'effectiveQuantity') ? (float) $item->effectiveQuantity($itemsById) : (float) ($item->quantity ?? 0);
        $totalWeight = method_exists($item, 'effectiveTotalWeight') ? (float) $item->effectiveTotalWeight($itemsById) : (float) ($item->total_weight ?? 0);
        $uomCode      = $item->uom?->code ?? null;
        $itemCode     = $item->item?->code ?? $item->item_code ?? null;
        $itemName     = $item->item?->name ?? null;
        $description  = $item->description ?? $itemName;

        // 1) Raw materials: plates + sections
        if (in_array($category, [
            BomItemMaterialCategory::STEEL_PLATE->value,
            BomItemMaterialCategory::STEEL_SECTION->value,
        ], true)) {
            if ($category === BomItemMaterialCategory::STEEL_PLATE->value) {
                $thickness = isset($dims['thickness_mm'])
                    ? (float) $dims['thickness_mm']
                    : null;

                $key = implode('|', [
                    'plate',
                    $grade ?? 'unknown',
                    $thickness ?? 'na',
                ]);

                if (! isset($raw[$key])) {
                    $raw[$key] = [
                        'category'        => 'plate',
                        'grade'           => $grade,
                        'thickness_mm'    => $thickness,
                        'section'         => null,
                        'lines'           => 0,
                        'total_qty'       => 0.0,
                        'uom'             => $uomCode,
                        'total_weight'    => 0.0,
                        'total_length_mm' => 0.0,
                    ];
                }

                $raw[$key]['lines']++;
                $raw[$key]['total_qty']    += $qty;
                $raw[$key]['total_weight'] += $totalWeight;
            }

            if ($category === BomItemMaterialCategory::STEEL_SECTION->value) {
                $section = $dims['section'] ?? null;

                $key = implode('|', [
                    'section',
                    $grade ?? 'unknown',
                    $section ?? 'na',
                ]);

                if (! isset($raw[$key])) {
                    $raw[$key] = [
                        'category'        => 'section',
                        'grade'           => $grade,
                        'thickness_mm'    => null,
                        'section'         => $section,
                        'lines'           => 0,
                        'total_qty'       => 0.0,
                        'uom'             => $uomCode,
                        'total_weight'    => 0.0,
                        'total_length_mm' => 0.0,
                    ];
                }

                $raw[$key]['lines']++;
                $raw[$key]['total_qty']    += $qty;
                $raw[$key]['total_weight'] += $totalWeight;

                // Capture total length requirement for sections if length_mm present
                if (isset($dims['length_mm'])) {
                    $lengthMm = (float) $dims['length_mm'];
                    $raw[$key]['total_length_mm'] += $lengthMm * $qty;
                }
            }

            continue;
        }

        // 2) Bought-out items
        if ($category === BomItemMaterialCategory::BOUGHT_OUT->value) {
            $key = $item->item_id
                ? 'item:' . $item->item_id
                : 'free:' . md5((string) $itemCode . '|' . (string) $description);

            if (! isset($boughtOut[$key])) {
                $boughtOut[$key] = [
                    'item_id'      => $item->item_id,
                    'item_code'    => $itemCode,
                    'item_name'    => $itemName,
                    'description'  => $description,
                    'grade'        => $grade,
                    'lines'        => 0,
                    'total_qty'    => 0.0,
                    'uom'          => $uomCode,
                    'total_weight' => 0.0,
                ];
            }

            $boughtOut[$key]['lines']++;
            $boughtOut[$key]['total_qty']    += $qty;
            $boughtOut[$key]['total_weight'] += $totalWeight;

            continue;
        }

        // 3) Consumables
        if ($category === BomItemMaterialCategory::CONSUMABLE->value) {
            $key = $item->item_id
                ? 'item:' . $item->item_id
                : 'free:' . md5((string) $itemCode . '|' . (string) $description);

            if (! isset($consumables[$key])) {
                $consumables[$key] = [
                    'item_id'      => $item->item_id,
                    'item_code'    => $itemCode,
                    'item_name'    => $itemName,
                    'description'  => $description,
                    'grade'        => $grade,
                    'lines'        => 0,
                    'total_qty'    => 0.0,
                    'uom'          => $uomCode,
                    'total_weight' => 0.0,
                ];
            }

            $consumables[$key]['lines']++;
            $consumables[$key]['total_qty']    += $qty;
            $consumables[$key]['total_weight'] += $totalWeight;

            continue;
        }
    }

    // Sort & normalize arrays for output
    $rawRequirements = collect($raw)
        ->sortBy([
            fn ($g) => $g['category'] ?? '',
            fn ($g) => $g['grade'] ?? '',
            fn ($g) => $g['thickness_mm'] ?? 0,
            fn ($g) => $g['section'] ?? '',
        ])
        ->values()
        ->all();

    $boughtOutRequirements = collect($boughtOut)
        ->sortBy(fn ($g) => $g['item_code'] ?? $g['description'] ?? '')
        ->values()
        ->all();

    $consumableRequirements = collect($consumables)
        ->sortBy(fn ($g) => $g['item_code'] ?? $g['description'] ?? '')
        ->values()
        ->all();

    return view('projects.boms.requirements', [
        'project'                => $project,
        'bom'                    => $bom,
        'rawRequirements'        => $rawRequirements,
        'boughtOutRequirements'  => $boughtOutRequirements,
        'consumableRequirements' => $consumableRequirements,
    ]);
	}

    public function edit(Project $project, Bom $bom): View
    {
        $this->ensureProjectBom($project, $bom);

        if (! $bom->isDraft()) {
            abort(403, 'Only draft BOM can be edited.');
        }

        return view('projects.boms.edit', compact('project', 'bom'));
    }

    public function update(UpdateBomRequest $request, Project $project, Bom $bom): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);

        if (! $bom->isDraft()) {
            return redirect()
                ->route('projects.boms.show', [$project, $bom])
                ->with('error', 'Only draft BOM can be updated.');
        }

        $data = $request->validated();

        $bom->version = $data['version'] ?? $bom->version;
        $bom->metadata = [
            'remarks' => $data['metadata']['remarks'] ?? null,
        ];

        $bom->save();

        return redirect()
            ->route('projects.boms.show', [$project, $bom])
            ->with('success', 'BOM updated successfully.');
    }

    public function destroy(Project $project, Bom $bom): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);

        if (! $bom->isDraft()) {
            return redirect()
                ->route('projects.boms.show', [$project, $bom])
                ->with('error', 'Only draft BOM can be deleted.');
        }

        $bom->delete();

        return redirect()
            ->route('projects.boms.index', $project)
            ->with('success', 'BOM deleted successfully.');
    }

    public function finalize(Project $project, Bom $bom): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);

        if (! $bom->isDraft()) {
            return redirect()
                ->route('projects.boms.show', [$project, $bom])
                ->with('error', 'Only draft BOM can be finalized.');
        }

        // Guardrails
        if ($bom->items()->count() === 0) {
            return redirect()
                ->route('projects.boms.show', [$project, $bom])
                ->with('error', 'Cannot finalize an empty BOM. Add at least one item.');
        }

        $bom->recalculateTotalWeight();

        if ($bom->total_weight <= 0) {
            return redirect()
                ->route('projects.boms.show', [$project, $bom])
                ->with('error', 'Cannot finalize BOM with total weight 0. Please check item weights.');
        }

        $bom->status = BomStatus::FINALIZED;
        $bom->finalized_by = auth()->id();
        $bom->finalized_date = now();
        $bom->save();

        return redirect()
            ->route('projects.boms.show', [$project, $bom])
            ->with('success', 'BOM finalized.');
    }

    /**
     * Clone BOM within same project as new version.
     */
    public function cloneVersion(Project $project, Bom $bom): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);

        $maxVersion = $project->boms()->max('version') ?? 0;
        $newVersion = $maxVersion + 1;

        $newBom = new Bom();
        $newBom->project_id = $project->id;
        $newBom->bom_number = Bom::generateNumberForProject($project);
        $newBom->version = $newVersion;
        $newBom->status = BomStatus::DRAFT;
        $newBom->metadata = $bom->metadata;
        $newBom->total_weight = $bom->total_weight;
        $newBom->save();

        $bom->load('items');
        $idMap = [];

        // First pass: clone items, temporarily drop parent
        foreach ($bom->items as $item) {
            $newItem = $item->replicate(['bom_id', 'parent_item_id']);
            $newItem->bom_id = $newBom->id;
            $newItem->parent_item_id = null;
            $newItem->save();

            $idMap[$item->id] = $newItem->id;
        }

        // Second pass: fix parents
        foreach ($bom->items as $oldItem) {
            $newItemId = $idMap[$oldItem->id] ?? null;
            if (! $newItemId) {
                continue;
            }
            $newItem = $newBom->items()->find($newItemId);
            if (! $newItem) {
                continue;
            }

            if ($oldItem->parent_item_id && isset($idMap[$oldItem->parent_item_id])) {
                $newItem->parent_item_id = $idMap[$oldItem->parent_item_id];
                $newItem->save();
            }
        }

        $newBom->recalculateTotalWeight();

        return redirect()
            ->route('projects.boms.show', [$project, $newBom])
            ->with('success', 'BOM cloned as new version.');
    }

    public function copyForm(Project $project, Bom $bom): View
    {
        $this->ensureProjectBom($project, $bom);

        $projects = Project::orderBy('code')->get();

        return view('projects.boms.copy', compact('project', 'bom', 'projects'));
    }

    public function copyStore(Request $request, Project $project, Bom $bom): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);

        $data = $request->validate([
            'target_project_id' => ['required', 'exists:projects,id'],
        ]);

        $targetProject = Project::findOrFail($data['target_project_id']);

        $newBom = new Bom();
        $newBom->project_id = $targetProject->id;
        $newBom->bom_number = Bom::generateNumberForProject($targetProject);
        $newBom->version = 1;
        $newBom->status = BomStatus::DRAFT;
        $newBom->metadata = $bom->metadata;
        $newBom->total_weight = $bom->total_weight;
        $newBom->save();

        $bom->load('items');
        $idMap = [];

        foreach ($bom->items as $item) {
            $newItem = $item->replicate(['bom_id', 'parent_item_id']);
            $newItem->bom_id = $newBom->id;
            $newItem->parent_item_id = null;
            $newItem->save();

            $idMap[$item->id] = $newItem->id;
        }

        foreach ($bom->items as $oldItem) {
            $newItemId = $idMap[$oldItem->id] ?? null;
            if (! $newItemId) {
                continue;
            }
            $newItem = $newBom->items()->find($newItemId);
            if (! $newItem) {
                continue;
            }

            if ($oldItem->parent_item_id && isset($idMap[$oldItem->parent_item_id])) {
                $newItem->parent_item_id = $idMap[$oldItem->parent_item_id];
                $newItem->save();
            }
        }

        $newBom->recalculateTotalWeight();

        return redirect()
            ->route('projects.boms.show', [$targetProject, $newBom])
            ->with('success', 'BOM copied to project ' . $targetProject->code . '.');
    }

    public function export(Project $project, Bom $bom): StreamedResponse
    {
        $this->ensureProjectBom($project, $bom);

        $bom->load([
            'items' => function ($q) {
                $q->orderBy('level')->orderBy('sequence_no');
            },
            'items.item',
            'items.uom',
        ]);

        $fileName = $bom->bom_number . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $columns = [
            'Seq',
            'Level',
            'Item Code',
            'Description',
            'Assembly Type',
            'Material Category',
            'Linked Item Code',
            'Linked Item Name',
            'Grade',
            'Dimensions',
            'Quantity',
            'UOM',
            'Unit Weight',
            'Total Weight',
            'Unit Area (m2)',
            'Total Area (m2)',
            'Unit Cut Length (m)',
            'Total Cut Length (m)',
            'Unit Weld Length (m)',
            'Total Weld Length (m)',
            'Scrap %',
            'Procurement Type',
            'Material Source',
            'Remarks',
        ];

        $callback = function () use ($bom, $columns) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $columns);

            foreach ($bom->items as $item) {
                fputcsv($handle, [
                    $item->sequence_no,
                    $item->level,
                    $item->item_code,
                    $item->description,
                    $item->assembly_type,
                    $item->material_category?->value,
                    $item->item?->code,
                    $item->item?->name,
                    $item->grade,
                    $item->formatted_dimensions,
                    $item->quantity,
                    $item->uom?->code,
                    $item->unit_weight,
                    $item->total_weight,
                    $item->unit_area_m2,
                    $item->total_area_m2,
                    $item->unit_cut_length_m,
                    $item->total_cut_length_m,
                    $item->unit_weld_length_m,
                    $item->total_weld_length_m,
                    $item->scrap_percentage,
                    $item->procurement_type?->value,
                    $item->material_source?->value,
                    $item->remarks,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportAssembly(Project $project, Bom $bom, BomItem $item): StreamedResponse
    {
        $this->ensureProjectBom($project, $bom);

        if ($item->bom_id !== $bom->id) {
            abort(404);
        }

        if (! $item->isAssembly()) {
            abort(400, 'Can only export for assembly items.');
        }

        $bom->load([
            'items' => function ($q) {
                $q->orderBy('level')->orderBy('sequence_no');
            },
            'items.item',
            'items.uom',
        ]);

        $items = $bom->items;

        $byParent = [];
        foreach ($items as $it) {
            $byParent[$it->parent_item_id ?? 0][] = $it;
        }

        $subtree = [];
        $stack = [$item];

        while ($stack) {
            $current = array_pop($stack);
            $subtree[] = $current;

            $children = $byParent[$current->id] ?? [];
            foreach ($children as $child) {
                $stack[] = $child;
            }
        }

        $fileName = $bom->bom_number . '-' . ($item->item_code ?: ('ASM-' . $item->id)) . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $columns = [
            'Seq',
            'Level',
            'Item Code',
            'Description',
            'Assembly Type',
            'Material Category',
            'Linked Item Code',
            'Linked Item Name',
            'Grade',
            'Dimensions',
            'Quantity',
            'UOM',
            'Unit Weight',
            'Total Weight',
            'Unit Area (m2)',
            'Total Area (m2)',
            'Unit Cut Length (m)',
            'Total Cut Length (m)',
            'Unit Weld Length (m)',
            'Total Weld Length (m)',
            'Scrap %',
            'Procurement Type',
            'Material Source',
            'Remarks',
        ];

        $callback = function () use ($subtree, $columns) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $columns);

            foreach ($subtree as $it) {
                fputcsv($handle, [
                    $it->sequence_no,
                    $it->level,
                    $it->item_code,
                    $it->description,
                    $it->assembly_type,
                    $it->material_category?->value,
                    $it->item?->code,
                    $it->item?->name,
                    $it->grade,
                    $it->formatted_dimensions,
                    $it->quantity,
                    $it->uom?->code,
                    $it->unit_weight,
                    $it->total_weight,
                    $it->unit_area_m2,
                    $it->total_area_m2,
                    $it->unit_cut_length_m,
                    $it->total_cut_length_m,
                    $it->unit_weld_length_m,
                    $it->total_weld_length_m,
                    $it->scrap_percentage,
                    $it->procurement_type?->value,
                    $it->material_source?->value,
                    $it->remarks,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
