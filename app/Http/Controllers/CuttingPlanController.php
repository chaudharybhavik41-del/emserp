<?php

namespace App\Http\Controllers;

use App\Enums\BomItemMaterialCategory;
use App\Enums\MaterialStockPieceStatus;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CuttingPlan;
use App\Models\CuttingPlanAllocation;
use App\Models\CuttingPlanPlate;
use App\Models\MaterialStockPiece;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CuttingPlanController extends Controller
{
    public function index(Project $project, Bom $bom): View
    {
        $this->ensureProjectBom($project, $bom);

        $plans = CuttingPlan::query()
            ->where('project_id', $project->id)
            ->where('bom_id', $bom->id)
            ->orderBy('thickness_mm')
            ->orderBy('grade')
            ->get();

        return view('projects.boms.cutting_plans.index', [
            'project' => $project,
            'bom'     => $bom,
            'plans'   => $plans,
        ]);
    }

    public function create(Project $project, Bom $bom, Request $request): View
    {
        $this->ensureProjectBom($project, $bom);

        // Prefill from query (e.g. coming from Material Planning group)
        $grade       = $request->string('grade')->toString() ?: null;
        $thicknessMm = $request->input('thickness_mm');

        return view('projects.boms.cutting_plans.create', [
            'project'      => $project,
            'bom'          => $bom,
            'grade'        => $grade,
            'thickness_mm' => $thicknessMm,
        ]);
    }

    public function store(Project $project, Bom $bom, Request $request): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);

        $data = $request->validate([
            'grade'        => ['nullable', 'string', 'max:50'],
            'thickness_mm' => ['required', 'integer', 'min:1'],
            'name'         => ['required', 'string', 'max:100'],
            'notes'        => ['nullable', 'string'],
        ]);

        $plan = CuttingPlan::create([
            'project_id'   => $project->id,
            'bom_id'       => $bom->id,
            'grade'        => $data['grade'] ?? null,
            'thickness_mm' => $data['thickness_mm'],
            'name'         => $data['name'],
            'status'       => 'draft',
            'notes'        => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('projects.boms.cutting-plans.edit', [$project, $bom, $plan])
            ->with('success', 'Cutting plan created.');
    }

    public function edit(Project $project, Bom $bom, CuttingPlan $cuttingPlan): View
    {
        $this->ensureProjectBom($project, $bom, $cuttingPlan);

        $cuttingPlan->load([
            'plates.allocations.bomItem',
            'plates.materialStockPiece',
        ]);

        // BOM plate components for this grade/thickness
        $bom->loadMissing([
            'items.item',
            'items.uom',
        ]);

        $plateItems = $bom->items->filter(function (BomItem $item) use ($cuttingPlan) {
            if (! method_exists($item, 'isLeafMaterial') || ! $item->isLeafMaterial()) {
                return false;
            }

            $cat = $item->material_category?->value;
            if ($cat !== BomItemMaterialCategory::STEEL_PLATE->value) {
                return false;
            }

            if ($cuttingPlan->grade && $item->grade !== $cuttingPlan->grade) {
                return false;
            }

            $dims = $item->dimensions ?? [];
            $thk  = isset($dims['thickness_mm']) ? (float) $dims['thickness_mm'] : null;

            if ($thk === null || (int) $thk !== (int) $cuttingPlan->thickness_mm) {
                return false;
            }

            return true;
        })->values();

        // -----------------------------
        // Allocation totals (for Remaining Qty UI)
        // -----------------------------
        $allocatedQtyByBomItem = [];
        foreach ($cuttingPlan->plates as $plate) {
            foreach ($plate->allocations as $alloc) {
                $bid = (int) $alloc->bom_item_id;
                $allocatedQtyByBomItem[$bid] = ($allocatedQtyByBomItem[$bid] ?? 0) + (int) $alloc->quantity;
            }
        }

        // IMPORTANT: Effective qty for Cutting Plan must respect parent assembly qty chain.
        // Material Planning uses effective totals; Cutting Plan must match it.
        $itemMap = $bom->items->keyBy('id');

        foreach ($plateItems as $pi) {
            $baseQty = (float) ($pi->getOriginal('quantity') ?? $pi->quantity ?? 0);
            $effQty  = $baseQty * $this->computeAssemblyMultiplierForCuttingPlan($pi, $itemMap);

            $requiredQty = (int) round($effQty);
            $allocated   = (int) ($allocatedQtyByBomItem[$pi->id] ?? 0);
            $remaining   = max($requiredQty - $allocated, 0);

            // Expose computed attributes for Blade (dropdown + table)
            $pi->setAttribute('effective_qty', $effQty);
            $pi->setAttribute('required_qty', $requiredQty);
            $pi->setAttribute('allocated_qty', $allocated);
            $pi->setAttribute('remaining_qty', $remaining);

            // Override quantity so existing Blade templates automatically show allocatable qty correctly.
            $pi->setAttribute('quantity', $requiredQty);

            // Keep weights consistent if available
            if (! is_null($pi->unit_weight)) {
                $pi->setAttribute('total_weight', round(((float) $pi->unit_weight) * $requiredQty, 3));
            }
        }

        // Remnant library filtered by thickness & grade for dropdown
        $remnantQuery = MaterialStockPiece::query()
            ->with('item')
            ->where('material_category', BomItemMaterialCategory::STEEL_PLATE->value)
            ->where('thickness_mm', $cuttingPlan->thickness_mm)
            ->where('status', MaterialStockPieceStatus::AVAILABLE->value);

        if ($cuttingPlan->grade) {
            $remnantQuery->whereHas('item', function ($q) use ($cuttingPlan) {
                $q->where('grade', $cuttingPlan->grade);
            });
        }

        $remnants = $remnantQuery
            ->orderBy('length_mm', 'desc')
            ->orderBy('width_mm', 'desc')
            ->limit(200)
            ->get();

        return view('projects.boms.cutting_plans.edit', [
            'project'    => $project,
            'bom'        => $bom,
            'plan'       => $cuttingPlan,
            'plateItems' => $plateItems,
            'remnants'   => $remnants,
        ]);
    }

    /**
     * Add a plate to the cutting plan.
     *
     * Unified logic:
     * - source_mode = 'new'     -> create & reserve a MaterialStockPiece (planned) + link it.
     * - source_mode = 'remnant' -> reserve existing MaterialStockPiece + link it.
     */
    public function addPlate(Project $project, Bom $bom, CuttingPlan $cuttingPlan, Request $request): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom, $cuttingPlan);

        $data = $request->validate([
            'plate_label'             => ['nullable', 'string', 'max:50'],
            'plate_qty'               => ['nullable', 'integer', 'min:1', 'max:200'],
            'source_mode'             => ['required', 'in:new,remnant'],
            'width_mm'                => ['nullable', 'integer', 'min:1'],
            'length_mm'               => ['nullable', 'integer', 'min:1'],
            'material_stock_piece_id' => ['nullable', 'integer', 'exists:material_stock_pieces,id'],
            'remarks'                 => ['nullable', 'string'],
        ]);

        $plateLabel = $data['plate_label'] ?? null;
        $plateQty   = (int) ($data['plate_qty'] ?? 1);
        $remarks    = $data['remarks'] ?? null;
        $sourceMode = $data['source_mode'];

        if ($plateQty < 1) {
            $plateQty = 1;
        }

        // We'll need a representative BOM plate item for density & item_id when creating new plates
        $bom->loadMissing(['items.item']);
        $plateItem = $bom->items->first(function (BomItem $item) use ($cuttingPlan) {
            $cat = $item->material_category?->value;
            if ($cat !== BomItemMaterialCategory::STEEL_PLATE->value) {
                return false;
            }
            if ($cuttingPlan->grade && $item->grade !== $cuttingPlan->grade) {
                return false;
            }
            $dims = $item->dimensions ?? [];
            $thk  = isset($dims['thickness_mm']) ? (float) $dims['thickness_mm'] : null;
            if ($thk === null || (int) $thk !== (int) $cuttingPlan->thickness_mm) {
                return false;
            }
            return $item->item !== null;
        });

        // Normalize labels (auto-generate sequential if qty > 1)
        $labels = $this->generatePlateLabels($cuttingPlan, $plateLabel, $plateQty);

        if ($sourceMode === 'remnant') {
            if ($plateQty > 1) {
                return back()->with('warning', 'Remnant plates must be added one by one (Qty must be 1 for remnant source).');
            }

            if (empty($data['material_stock_piece_id'])) {
                return back()->with('warning', 'Please select a remnant plate from the library.');
            }

            /** @var MaterialStockPiece $stock */
            $stock = MaterialStockPiece::query()
                ->with('item')
                ->where('id', $data['material_stock_piece_id'])
                ->where('material_category', BomItemMaterialCategory::STEEL_PLATE->value)
                ->where('thickness_mm', $cuttingPlan->thickness_mm)
                ->firstOrFail();

            if ($cuttingPlan->grade && $stock->item && $stock->item->grade !== $cuttingPlan->grade) {
                return back()->with('warning', 'Selected remnant does not match cutting plan grade.');
            }

            // Reserve this stock piece for this project/BOM (deduct from free remnant pool)
            $stock->reserved_for_project_id = $project->id;
            $stock->reserved_for_bom_id     = $bom->id;
            // Keep status as AVAILABLE for now; RESERVED/CONSUMED will be handled at DPR stage.
            $stock->save();

            $thicknessMm = (int) $stock->thickness_mm;
            $widthMm     = (int) $stock->width_mm;
            $lengthMm    = (int) $stock->length_mm;

            $grossAreaM2   = ($widthMm / 1000) * ($lengthMm / 1000);
            $grossWeightKg = $stock->weight_kg;

            CuttingPlanPlate::create([
                'cutting_plan_id'         => $cuttingPlan->id,
                'material_stock_piece_id' => $stock->id,
                'plate_label'             => $labels[0] ?? $plateLabel,
                'thickness_mm'            => $thicknessMm,
                'width_mm'                => $widthMm,
                'length_mm'               => $lengthMm,
                'gross_area_m2'           => $grossAreaM2,
                'gross_weight_kg'         => $grossWeightKg,
                'source_type'             => $stock->mother_piece_id ? 'remnant' : ($stock->source_type ?? 'stock'),
                'remarks'                 => $remarks,
            ]);
        } else {
            // New planned plate size -> create MaterialStockPiece + link (supports Qty)
            if (empty($data['width_mm']) || empty($data['length_mm'])) {
                return back()->with('warning', 'Please enter width and length for the new plate.');
            }

            $widthMm     = (int) $data['width_mm'];
            $lengthMm    = (int) $data['length_mm'];
            $thicknessMm = (int) $cuttingPlan->thickness_mm;

            $grossAreaM2 = ($widthMm / 1000) * ($lengthMm / 1000);

            // Determine density
            $density = 7850; // default structural steel
            if ($plateItem && $plateItem->item && $plateItem->item->density) {
                $density = (float) $plateItem->item->density;
            }

            $volumeM3      = ($thicknessMm / 1000) * $grossAreaM2;
            $grossWeightKg = round($volumeM3 * $density, 3);

            for ($i = 0; $i < $plateQty; $i++) {
                $label = $labels[$i] ?? null;

                // Create planned MaterialStockPiece so inventory & material planning see the same plate
                $stockPieceData = [
                    'item_id'                 => $plateItem && $plateItem->item ? $plateItem->item->id : null,
                    'material_category'       => BomItemMaterialCategory::STEEL_PLATE->value,
                    'thickness_mm'            => $thicknessMm,
                    'width_mm'                => $widthMm,
                    'length_mm'               => $lengthMm,
                    'section_profile'         => null,
                    'weight_kg'               => $grossWeightKg,
                    'plate_number'            => null,
                    'heat_number'             => null,
                    'mtc_number'              => null,
                    'origin_project_id'       => $project->id,
                    'origin_bom_id'           => $bom->id,
                    'mother_piece_id'         => null,
                    'status'                  => MaterialStockPieceStatus::AVAILABLE->value,
                    'reserved_for_project_id' => $project->id,
                    'reserved_for_bom_id'     => $bom->id,
                    'source_type'             => 'planned',
                    'source_reference'        => 'CUTPLAN:' . ($bom->bom_number ?? $bom->id) . ':' . $cuttingPlan->id . ($label ? (':PLATE:' . $label) : ''),
                    'location'                => null,
                    'remarks'                 => $remarks,
                ];

                $stockPiece = MaterialStockPiece::create($stockPieceData);

                CuttingPlanPlate::create([
                    'cutting_plan_id'         => $cuttingPlan->id,
                    'material_stock_piece_id' => $stockPiece->id,
                    'plate_label'             => $label,
                    'thickness_mm'            => $thicknessMm,
                    'width_mm'                => $widthMm,
                    'length_mm'               => $lengthMm,
                    'gross_area_m2'           => $grossAreaM2,
                    'gross_weight_kg'         => $grossWeightKg,
                    'source_type'             => $stockPiece->source_type ?? 'planned',
                    'remarks'                 => $remarks,
                ]);
            }
        }

        return redirect()
            ->route('projects.boms.cutting-plans.edit', [$project, $bom, $cuttingPlan])
            ->with('success', $plateQty > 1 ? "{$plateQty} plates added to cutting plan." : 'Plate added to cutting plan.');
    }

    public function addAllocation(Project $project, Bom $bom, CuttingPlan $cuttingPlan, CuttingPlanPlate $plate, Request $request): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom, $cuttingPlan);

        if ($plate->cutting_plan_id !== $cuttingPlan->id) {
            abort(404);
        }

        $data = $request->validate([
            'bom_item_id' => ['required', 'integer', 'exists:bom_items,id'],
            'quantity'    => ['required', 'integer', 'min:1'],
            'notes'       => ['nullable', 'string'],
        ]);

        /** @var BomItem $bomItem */
        $bomItem = BomItem::query()
            ->where('id', $data['bom_item_id'])
            ->where('bom_id', $bom->id)
            ->firstOrFail();

        // Enforce: Allocation qty cannot exceed remaining required qty
        $bom->loadMissing(['items']);
        $itemMap = $bom->items->keyBy('id');
        $requiredQty = (int) round(((float) ($bomItem->quantity ?? 0)) * $this->computeAssemblyMultiplierForCuttingPlan($bomItem, $itemMap));

        if ($requiredQty <= 0) {
            return back()->with('warning', 'Selected component has zero required quantity. Check BOM quantities/assemblies.');
        }

        $allocatedQty = (int) CuttingPlanAllocation::query()
            ->join('cutting_plan_plates as p', 'p.id', '=', 'cutting_plan_allocations.cutting_plan_plate_id')
            ->where('p.cutting_plan_id', $cuttingPlan->id)
            ->where('cutting_plan_allocations.bom_item_id', $bomItem->id)
            ->sum('cutting_plan_allocations.quantity');

        $remainingQty = $requiredQty - $allocatedQty;

        if ($remainingQty <= 0) {
            return back()->with('warning', "No remaining qty to allocate for this component. Required: {$requiredQty}, already allocated: {$allocatedQty}.");
        }

        $reqQty = (int) $data['quantity'];

        if ($reqQty > $remainingQty) {
            return back()->with('warning', "Allocation qty exceeds remaining requirement. Remaining: {$remainingQty} (Required: {$requiredQty}, Allocated: {$allocatedQty}).");
        }

        // Optional: auto-calc used weight from BOM item total_weight / quantity
        $perPieceWeight = null;
        if ($bomItem->total_weight && $bomItem->quantity) {
            $perPieceWeight = (float) $bomItem->total_weight / (float) $bomItem->quantity;
        }

        $usedWeight = $perPieceWeight !== null
            ? round($perPieceWeight * $reqQty, 3)
            : null;

        CuttingPlanAllocation::create([
            'cutting_plan_plate_id' => $plate->id,
            'bom_item_id'           => $bomItem->id,
            'quantity'              => $reqQty,
            'used_area_m2'          => null,
            'used_weight_kg'        => $usedWeight,
            'notes'                 => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('projects.boms.cutting-plans.edit', [$project, $bom, $cuttingPlan])
            ->with('success', 'Allocation added to plate.');
    }

    /**
     * Multiplier from parent fabricated assemblies for Cutting Plan (safe default).
     *
     * Cutting Plan should match Material Planning "effective" quantities:
     * leaf qty * product(parent fabricated assembly qty).
     *
     * @param \Illuminate\Support\Collection<int, BomItem> $itemMap
     */
    protected function computeAssemblyMultiplierForCuttingPlan(BomItem $leaf, $itemMap): float
    {
        $mult  = 1.0;
        $cur   = $leaf;
        $guard = 0;

        while ($cur && $cur->parent_item_id && $guard < 50) {
            $guard++;

            /** @var BomItem|null $parent */
            $parent = $itemMap->get($cur->parent_item_id);
            if (! $parent) {
                break;
            }

            $parentCat = $parent->material_category?->value ?? (string) $parent->material_category;

            // Multiply only fabricated assemblies
            if ($parentCat === 'fabricated_assembly') {
                $pq = (float) ($parent->quantity ?? 1);
                if ($pq <= 0) {
                    return 0.0;
                }
                $mult *= $pq;
            }

            $cur = $parent;
        }

        return $mult;
    }

    /**
     * Generate plate labels for bulk-add.
     *
     * Rules:
     * - If seed is empty -> auto P{next}, P{next+1}...
     * - If seed ends with digits -> increment digits sequentially
     * - Else -> seed-1, seed-2...
     * - Ensures no duplicates within the plan.
     */
    protected function generatePlateLabels(CuttingPlan $plan, ?string $seedLabel, int $qty): array
    {
        $qty = max(1, $qty);

        $seedLabel = $seedLabel !== null ? trim($seedLabel) : '';

        $existing = $plan->plates()
            ->whereNotNull('plate_label')
            ->pluck('plate_label')
            ->filter()
            ->map(fn ($v) => trim((string) $v))
            ->values()
            ->all();

        $existingSet = array_fill_keys($existing, true);

        $labels = [];

        // Auto: P{n}
        if ($seedLabel === '') {
            $maxN = 0;
            foreach ($existing as $lbl) {
                if (preg_match('/^P(\d+)$/i', $lbl, $m)) {
                    $maxN = max($maxN, (int) $m[1]);
                }
            }

            $n = $maxN + 1;
            for ($i = 0; $i < $qty; $i++) {
                $candBase = 'P' . ($n + $i);
                $cand = $candBase;
                $k = 1;
                while (isset($existingSet[$cand]) || in_array($cand, $labels, true)) {
                    $k++;
                    $cand = $candBase . '-' . $k;
                }
                $labels[] = $cand;
            }

            return $labels;
        }

        // Single label
        if ($qty === 1) {
            $cand = $seedLabel;
            if (isset($existingSet[$cand])) {
                $k = 2;
                while (isset($existingSet[$cand . '-' . $k])) {
                    $k++;
                }
                $cand = $cand . '-' . $k;
            }
            return [$cand];
        }

        // Seed ends with number -> increment number
        if (preg_match('/^(.*?)(\d+)$/', $seedLabel, $m)) {
            $prefix = $m[1];
            $num    = (int) $m[2];

            // Start at next free label
            while (isset($existingSet[$prefix . $num])) {
                $num++;
            }

            for ($i = 0; $i < $qty; $i++) {
                $candBase = $prefix . ($num + $i);
                $cand = $candBase;
                $k = 1;
                while (isset($existingSet[$cand]) || in_array($cand, $labels, true)) {
                    $k++;
                    $cand = $candBase . '-' . $k;
                }
                $labels[] = $cand;
            }

            return $labels;
        }

        // Seed without number -> seed-1, seed-2...
        for ($i = 1; $i <= $qty; $i++) {
            $candBase = $seedLabel . '-' . $i;
            $cand = $candBase;
            $k = 1;
            while (isset($existingSet[$cand]) || in_array($cand, $labels, true)) {
                $k++;
                $cand = $candBase . '-' . $k;
            }
            $labels[] = $cand;
        }

        return $labels;
    }

    protected function ensureProjectBom(Project $project, Bom $bom, ?CuttingPlan $plan = null): void
    {
        if ($bom->project_id !== $project->id) {
            abort(404);
        }

        if ($plan && ($plan->project_id !== $project->id || $plan->bom_id !== $bom->id)) {
            abort(404);
        }
    }
}
