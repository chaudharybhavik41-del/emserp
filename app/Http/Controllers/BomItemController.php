<?php

namespace App\Http\Controllers;

use App\Enums\BomItemMaterialCategory;
use App\Enums\BomItemMaterialSource;
use App\Enums\BomItemProcurementType;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Item;
use App\Models\Project;
use App\Models\Uom;
use App\Services\Engineering\BomKpiCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BomItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:project.bom.update')
            ->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    protected function ensureProjectBom(Project $project, Bom $bom): Bom
    {
        if ($bom->project_id !== $project->id) {
            abort(404);
        }

        return $bom;
    }

    protected function materialCategories(): array
    {
        return BomItemMaterialCategory::cases();
    }

    protected function rawMaterialItems()
    {
        // Used for BOM item linking dropdown.
        // We load ALL active items so Bought-out / Consumable items can also be linked.
        // RAW-only rule will be enforced in store/update for Steel Plate / Steel Section categories.
        return Item::with(['uom', 'type', 'category'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    protected function requiresRawItem(?string $category): bool
    {
        return in_array($category, [
            BomItemMaterialCategory::STEEL_PLATE->value,
            BomItemMaterialCategory::STEEL_SECTION->value,
        ], true);
    }

    protected function uoms()
    {
        return Uom::orderBy('code')->get();
    }

    public function create(Project $project, Bom $bom, Request $request): View
    {
        $this->ensureProjectBom($project, $bom);

        $parentItem = null;
        $parentId = $request->integer('parent_item_id');
        if ($parentId) {
            $parentItem = $bom->items()->where('id', $parentId)->firstOrFail();
        }

        $item = new BomItem();

        if ($parentItem) {
            $item->parent_item_id = $parentItem->id;
            $item->level = $parentItem->level + 1;

            // Inherit procurement defaults from parent to reduce repetitive selection
            $item->procurement_type = $parentItem->procurement_type;
            $item->material_source = $parentItem->material_source;

            // Suggest next sequence within this assembly
            $maxSiblingSeq = (int) $bom->items()
                ->where('parent_item_id', $parentItem->id)
                ->max('sequence_no');
            $item->sequence_no = max(1, $maxSiblingSeq + 1);
        } else {
            // Suggest next top-level sequence (10, 20, 30, ...)
            $maxRootSeq = (int) $bom->items()
                ->whereNull('parent_item_id')
                ->max('sequence_no');

            if ($maxRootSeq <= 0) {
                $item->sequence_no = 10;
            } else {
                $rounded = (int) (ceil($maxRootSeq / 10) * 10);
                $next = $rounded;
                if ($next <= $maxRootSeq) {
                    $next += 10;
                }
                $item->sequence_no = $next;
            }
        }

        $materialCategories = $this->materialCategories();
        $rawItems = $this->rawMaterialItems();
        $uoms = $this->uoms();

        return view('projects.boms.items.create', compact(
            'project',
            'bom',
            'item',
            'parentItem',
            'materialCategories',
            'rawItems',
            'uoms'
        ));
    }

    public function store(Project $project, Bom $bom, Request $request): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);

        $validated = $request->validate([
            'parent_item_id' => ['nullable', 'integer', 'exists:bom_items,id'],
            'item_code' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'assembly_type' => ['nullable', 'string', 'max:50'],
            'sequence_no' => ['nullable', 'integer', 'min:0'],
            'material_category' => ['required', 'string'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'uom_id' => ['nullable', 'integer', 'exists:uoms,id'],
            'grade' => ['nullable', 'string', 'max:100'],
            'dimensions.thickness_mm' => ['nullable', 'numeric', 'min:0'],
            'dimensions.width_mm' => ['nullable', 'numeric', 'min:0'],
            'dimensions.length_mm' => ['nullable', 'numeric', 'min:0'],
            'dimensions.section' => ['nullable', 'string', 'max:100'],
            'dimensions.span_length_m' => ['nullable', 'numeric', 'min:0'],
            'dimensions.depth_mm' => ['nullable', 'numeric', 'min:0'],
            'dimensions.leaves' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit_weight' => ['nullable', 'numeric', 'min:0'],
            'total_weight' => ['nullable', 'numeric', 'min:0'],
            'unit_area_m2' => ['nullable', 'numeric', 'min:0'],
            'unit_cut_length_m' => ['nullable', 'numeric', 'min:0'],
            'unit_weld_length_m' => ['nullable', 'numeric', 'min:0'],
            'scrap_percentage' => ['nullable', 'numeric', 'min:0'],
            'procurement_type' => ['required', 'string'],
            'material_source' => ['required', 'string'],
            'is_billable' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string'],
        ]);

        $validated['is_billable'] = $request->boolean('is_billable');

        $dimensions = $validated['dimensions'] ?? [];
        unset($validated['dimensions']);

        $validated['bom_id'] = $bom->id;
        $validated['sequence_no'] = $validated['sequence_no'] ?? null;
        $validated['scrap_percentage'] = $validated['scrap_percentage'] ?? 0;

        // Handle parent / level
        $parentItem = null;
        $validated['level'] = 0;
        if (! empty($validated['parent_item_id'])) {
            $parentItem = $bom->items()->where('id', $validated['parent_item_id'])->firstOrFail();
            $validated['level'] = $parentItem->level + 1;
        }

        $category = $validated['material_category'] ?? null;

        // Prevent accidental decimal qty for plates/sections/assemblies (mouse-wheel scroll issue)
        if (in_array($category, [
            BomItemMaterialCategory::FABRICATED_ASSEMBLY->value,
            BomItemMaterialCategory::STEEL_PLATE->value,
            BomItemMaterialCategory::STEEL_SECTION->value,
        ], true)) {
            $validated['quantity'] = round((float) ($validated['quantity'] ?? 0), 0);
        }

        // Auto-assign sequence when blank/0
        $seq = (int) ($validated['sequence_no'] ?? 0);
        if ($seq <= 0) {
            if ($parentItem) {
                $maxSibling = (int) $bom->items()
                    ->where('parent_item_id', $parentItem->id)
                    ->max('sequence_no');

                $validated['sequence_no'] = max(1, $maxSibling + 1);
            } else {
                $maxRoot = (int) $bom->items()
                    ->whereNull('parent_item_id')
                    ->max('sequence_no');

                if ($maxRoot <= 0) {
                    $validated['sequence_no'] = 10;
                } else {
                    $rounded = (int) (ceil($maxRoot / 10) * 10);
                    $next = $rounded;
                    if ($next <= $maxRoot) {
                        $next += 10;
                    }
                    $validated['sequence_no'] = $next;
                }
            }
        }

        $itemModel = null;
        if (! empty($validated['item_id'])) {
            $itemModel = Item::with(['type', 'uom', 'category'])->find($validated['item_id']);
        }

        // Enforce item linking rules (leaf vs assembly) and RAW restriction for steel categories
        if ($category !== BomItemMaterialCategory::FABRICATED_ASSEMBLY->value) {
            if (! $itemModel) {
                return back()
                    ->withInput()
                    ->withErrors(['item_id' => 'Leaf BOM items must be linked to an Item.']);
            }

            if ($this->requiresRawItem($category)) {
                $itemModel->loadMissing('type');

                $itemTypeCode = strtoupper((string) ($itemModel->type?->code ?? ''));
                if ($itemTypeCode !== 'RAW') {
                    return back()
                        ->withInput()
                        ->withErrors(['item_id' => 'Selected item must be a RAW material (material type RAW).']);
                }
            }
        }

        // Auto-fill UOM and grade from item if not provided
        if ($itemModel) {
            if (empty($validated['uom_id']) && $itemModel->uom_id) {
                $validated['uom_id'] = $itemModel->uom_id;
            }
            if (empty($validated['grade']) && $itemModel->grade) {
                $validated['grade'] = $itemModel->grade;
            }
        }

        // For plates: bring thickness from item if missing
        if (
            $itemModel
            && $category === BomItemMaterialCategory::STEEL_PLATE->value
            && (empty($dimensions['thickness_mm']) && ! is_null($itemModel->thickness))
        ) {
            $dimensions['thickness_mm'] = (float) $itemModel->thickness;
        }

        // Auto-calc weights + KPI metrics
        $this->applyAutoWeights($validated, $dimensions, $itemModel);
        $this->applyAutoKpiMetrics($validated, $dimensions, $itemModel);

        $item = new BomItem($validated);
        $item->dimensions = $dimensions;
        $item->save();

        $bom->recalculateTotalWeight();

        return redirect()
            ->route('projects.boms.show', [$project, $bom])
            ->with('success', 'BOM item added successfully.');
    }

    public function edit(Project $project, Bom $bom, BomItem $item): View|RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);

        if ($item->bom_id !== $bom->id) {
            return redirect()
                ->route('projects.boms.show', [$project, $bom])
                ->with('error', 'Invalid BOM item.');
        }

        $parentItem = $item->parent;
        $materialCategories = $this->materialCategories();
        $rawItems = $this->rawMaterialItems();
        $uoms = $this->uoms();

        return view('projects.boms.items.edit', compact(
            'project',
            'bom',
            'item',
            'parentItem',
            'materialCategories',
            'rawItems',
            'uoms'
        ));
    }

    public function update(Project $project, Bom $bom, BomItem $item, Request $request): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);

        if ($item->bom_id !== $bom->id) {
            return redirect()
                ->route('projects.boms.show', [$project, $bom])
                ->with('error', 'Invalid BOM item.');
        }

        $validated = $request->validate([
            'item_code' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'assembly_type' => ['nullable', 'string', 'max:50'],
            'sequence_no' => ['nullable', 'integer', 'min:0'],
            'material_category' => ['required', 'string'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'uom_id' => ['nullable', 'integer', 'exists:uoms,id'],
            'grade' => ['nullable', 'string', 'max:100'],
            'dimensions.thickness_mm' => ['nullable', 'numeric', 'min:0'],
            'dimensions.width_mm' => ['nullable', 'numeric', 'min:0'],
            'dimensions.length_mm' => ['nullable', 'numeric', 'min:0'],
            'dimensions.section' => ['nullable', 'string', 'max:100'],
            'dimensions.span_length_m' => ['nullable', 'numeric', 'min:0'],
            'dimensions.depth_mm' => ['nullable', 'numeric', 'min:0'],
            'dimensions.leaves' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit_weight' => ['nullable', 'numeric', 'min:0'],
            'total_weight' => ['nullable', 'numeric', 'min:0'],
            'unit_area_m2' => ['nullable', 'numeric', 'min:0'],
            'unit_cut_length_m' => ['nullable', 'numeric', 'min:0'],
            'unit_weld_length_m' => ['nullable', 'numeric', 'min:0'],
            'scrap_percentage' => ['nullable', 'numeric', 'min:0'],
            'procurement_type' => ['required', 'string'],
            'material_source' => ['required', 'string'],
            'is_billable' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string'],
        ]);

        $validated['is_billable'] = $request->boolean('is_billable');

        $dimensions = $validated['dimensions'] ?? [];
        unset($validated['dimensions']);

        $validated['sequence_no'] = $validated['sequence_no'] ?? $item->sequence_no;
        $validated['scrap_percentage'] = $validated['scrap_percentage'] ?? 0;

        $category = $validated['material_category'] ?? null;

        // Prevent accidental decimal qty for plates/sections/assemblies (mouse-wheel scroll issue)
        if (in_array($category, [
            BomItemMaterialCategory::FABRICATED_ASSEMBLY->value,
            BomItemMaterialCategory::STEEL_PLATE->value,
            BomItemMaterialCategory::STEEL_SECTION->value,
        ], true)) {
            $validated['quantity'] = round((float) ($validated['quantity'] ?? 0), 0);
        }

        // Auto-assign sequence when blank/0 (helps legacy rows with sequence_no=0)
        $seq = (int) ($validated['sequence_no'] ?? 0);
        if ($seq <= 0) {
            if (! empty($item->parent_item_id)) {
                $maxSibling = (int) $bom->items()
                    ->where('parent_item_id', $item->parent_item_id)
                    ->where('id', '!=', $item->id)
                    ->max('sequence_no');

                $validated['sequence_no'] = max(1, $maxSibling + 1);
            } else {
                $maxRoot = (int) $bom->items()
                    ->whereNull('parent_item_id')
                    ->where('id', '!=', $item->id)
                    ->max('sequence_no');

                if ($maxRoot <= 0) {
                    $validated['sequence_no'] = 10;
                } else {
                    $rounded = (int) (ceil($maxRoot / 10) * 10);
                    $next = $rounded;
                    if ($next <= $maxRoot) {
                        $next += 10;
                    }
                    $validated['sequence_no'] = $next;
                }
            }
        }

        $itemModel = null;
        if (! empty($validated['item_id'])) {
            $itemModel = Item::with(['type', 'uom', 'category'])->find($validated['item_id']);
        }

        // Enforce item linking rules (leaf vs assembly) and RAW restriction for steel categories
        if ($category !== BomItemMaterialCategory::FABRICATED_ASSEMBLY->value) {
            if (! $itemModel) {
                return back()
                    ->withInput()
                    ->withErrors(['item_id' => 'Leaf BOM items must be linked to an Item.']);
            }

            if ($this->requiresRawItem($category)) {
                $itemModel->loadMissing('type');

                $itemTypeCode = strtoupper((string) ($itemModel->type?->code ?? ''));
                if ($itemTypeCode !== 'RAW') {
                    return back()
                        ->withInput()
                        ->withErrors(['item_id' => 'Selected item must be a RAW material (material type RAW).']);
                }
            }
        }

        // Auto-fill UOM and grade from item if not provided
        if ($itemModel) {
            if (empty($validated['uom_id']) && $itemModel->uom_id) {
                $validated['uom_id'] = $itemModel->uom_id;
            }
            if (empty($validated['grade']) && $itemModel->grade) {
                $validated['grade'] = $itemModel->grade;
            }
        }

        // For plates: bring thickness from item if missing
        if (
            $itemModel
            && $category === BomItemMaterialCategory::STEEL_PLATE->value
            && (empty($dimensions['thickness_mm']) && ! is_null($itemModel->thickness))
        ) {
            $dimensions['thickness_mm'] = (float) $itemModel->thickness;
        }

        $this->applyAutoWeights($validated, $dimensions, $itemModel);
        $this->applyAutoKpiMetrics($validated, $dimensions, $itemModel);

        $item->fill($validated);
        $item->dimensions = $dimensions;
        $item->save();

        $bom->recalculateTotalWeight();

        return redirect()
            ->route('projects.boms.show', [$project, $bom])
            ->with('success', 'BOM item updated successfully.');
    }

    public function destroy(Project $project, Bom $bom, BomItem $item): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);

        if ($item->bom_id !== $bom->id) {
            return redirect()
                ->route('projects.boms.show', [$project, $bom])
                ->with('error', 'Invalid BOM item.');
        }

        $item->delete();
        $bom->recalculateTotalWeight();

        return redirect()
            ->route('projects.boms.show', [$project, $bom])
            ->with('success', 'BOM item deleted.');
    }

    /**
     * Apply auto KPI metric calculations (area m², cut length m, weld length m).
     *
     * Unit values can be provided manually; if left blank the system auto-calculates where possible
     * (plates: area + cut length, sections: area via surface_area_per_meter).
     */
    protected function applyAutoKpiMetrics(array &$data, array $dimensions, ?Item $itemModel): void
    {
        $category = $data['material_category'] ?? null;
        $qty = (float) ($data['quantity'] ?? 0);

        $calc = app(BomKpiCalculator::class)->calculate(
            $category,
            $dimensions,
            $qty,
            $itemModel,
            [
                'unit_area_m2' => $data['unit_area_m2'] ?? null,
                'unit_cut_length_m' => $data['unit_cut_length_m'] ?? null,
                'unit_weld_length_m' => $data['unit_weld_length_m'] ?? null,
            ]
        );

        $data['unit_area_m2'] = $calc['unit_area_m2'];
        $data['total_area_m2'] = $calc['total_area_m2'];
        $data['unit_cut_length_m'] = $calc['unit_cut_length_m'];
        $data['total_cut_length_m'] = $calc['total_cut_length_m'];
        $data['unit_weld_length_m'] = $calc['unit_weld_length_m'];
        $data['total_weld_length_m'] = $calc['total_weld_length_m'];
    }

    /**
     * Apply auto-calculation for unit_weight and total_weight based on
     * dimensions + item master data (density, weight_per_meter).
     */
    protected function applyAutoWeights(array &$data, array &$dimensions, ?Item $itemModel): void
    {
        $category = $data['material_category'] ?? null;
        $qty = (float) ($data['quantity'] ?? 0);

        // Steel plates: use thickness, width, length + density
        if ($category === BomItemMaterialCategory::STEEL_PLATE->value) {
            $t = isset($dimensions['thickness_mm']) ? (float) $dimensions['thickness_mm'] : null;
            $w = isset($dimensions['width_mm']) ? (float) $dimensions['width_mm'] : null;
            $L = isset($dimensions['length_mm']) ? (float) $dimensions['length_mm'] : null;

            if ($t && $w && $L && empty($data['unit_weight'])) {
                $density = 7850.0; // default steel
                if ($itemModel && ! is_null($itemModel->density)) {
                    $density = (float) $itemModel->density;
                }

                $volume_m3 = ($t * $w * $L) / 1_000_000_000.0;
                $unit = $density * $volume_m3; // kg per piece
                $data['unit_weight'] = round($unit, 3);
            }
        }

        // Steel sections: use item weight_per_meter + length
        if (
            $category === BomItemMaterialCategory::STEEL_SECTION->value
            && $itemModel
            && ! is_null($itemModel->weight_per_meter)
            && ! empty($dimensions['length_mm'])
            && empty($data['unit_weight'])
        ) {
            $length_m = (float) $dimensions['length_mm'] / 1000.0;
            if ($length_m > 0) {
                $unit = (float) $itemModel->weight_per_meter * $length_m;
                $data['unit_weight'] = round($unit, 3);
            }
        }

        // Always keep total_weight in sync with Qty × Unit Weight when unit_weight is present
        if (! empty($data['unit_weight'])) {
            $data['total_weight'] = round(((float) $data['unit_weight']) * $qty, 3);
        }
    }
}
