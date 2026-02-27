<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\CuttingPlan;
use App\Models\CuttingPlanPlate;
use App\Models\StoreStockItem;
use App\Models\Production\ProductionDpr;
use App\Models\Production\ProductionDprLine;
use App\Models\Production\ProductionPiece;
use App\Models\Production\ProductionAssembly;
use App\Models\Production\ProductionAssemblyComponent;
use App\Models\Production\ProductionRemnant;
use App\Services\Production\ProductionAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionTraceabilityController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dpr.view');
    }

    public function edit(Project $project, ProductionDpr $production_dpr)
    {
        if ((int) $production_dpr->plan->project_id !== (int) $project->id) {
            abort(404);
        }

        // HARDEN: only allow traceability on APPROVED DPR
        if ($production_dpr->status !== 'approved') {
            return redirect()
                ->route('projects.production-dprs.show', [$project, $production_dpr])
                ->with('error', 'Traceability can be captured only after DPR is Approved.');
        }

        $production_dpr->load(['activity', 'plan', 'lines.planItem', 'lines.planItemActivity.activity']);

        $activity = $production_dpr->activity;

        $isCutting = str_contains(strtoupper((string) $activity->code), 'CUT')
            || str_contains(strtoupper((string) $activity->name), 'CUT');

        $isFitup = (bool) ($activity->is_fitupp ?? false);

        // Only lines that are actually done in this DPR should be eligible for traceability.
        // (DPR contains draft lines for all eligible items, but user ticks Done for few.)
        $traceLines = $production_dpr->lines
            ->filter(function ($ln) {
                return (int) ($ln->is_completed ?? 0) === 1;
            })
            ->values();

        // For cutting, show captured counts to support partial tagging across multiple plates.
        $capturedPieces = [];
        if ($isCutting && $traceLines->isNotEmpty()) {
            $ids = $traceLines->pluck('id')->all();

            $capturedPieces = ProductionPiece::query()
                ->selectRaw('production_dpr_line_id, COUNT(*) as cnt')
                ->whereIn('production_dpr_line_id', $ids)
                ->groupBy('production_dpr_line_id')
                ->pluck('cnt', 'production_dpr_line_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        $stockItems = StoreStockItem::query()
            ->with(['item', 'project'])
            ->whereIn('material_category', ['steel_plate', 'steel_section'])
            ->where('status', 'available')
            ->where(function ($q) use ($project) {
                $q->whereNull('project_id')->orWhere('project_id', $project->id);
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $pieces = ProductionPiece::query()
            ->where('project_id', $project->id)
            ->where('status', 'available')
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        // Optional: Cutting Plan integration (Design -> Production)
        // Used to auto-fill batch quantities per plate.
        $cuttingPlanPlates = [];
        $cuttingPlanPlateMap = [];

        if ($isCutting) {
            $bomId = (int) ($production_dpr->plan?->bom_id ?? 0);

            if ($bomId > 0) {
                $plansQuery = CuttingPlan::query()
                    ->where('project_id', $project->id)
                    ->where('bom_id', $bomId);

                // If the DPR was created from a specific cutting plan, show only that plan's plates.
                $selectedCpId = (int) ($production_dpr->cutting_plan_id ?? 0);
                if ($selectedCpId > 0) {
                    $plansQuery->where('id', $selectedCpId);
                }

                $plans = $plansQuery
                    ->with(['plates.allocations', 'plates.materialStockPiece'])
                    ->orderBy('thickness_mm')
                    ->orderBy('grade')
                    ->orderBy('id')
                    ->get();

                foreach ($plans as $plan) {
                    foreach ($plan->plates as $plate) {
                        $allocMap = [];
                        foreach (($plate->allocations ?? []) as $al) {
                            $allocMap[(int) $al->bom_item_id] = (int) $al->quantity;
                        }

                        $msp = $plate->materialStockPiece;

                        $payload = [
                            'id' => (int) $plate->id,
                            'plan_id' => (int) $plan->id,
                            'plan_name' => (string) ($plan->name ?? ('CuttingPlan#' . $plan->id)),
                            'plan_status' => (string) ($plan->status ?? 'draft'),
                            'grade' => $plan->grade,
                            'thickness_mm' => (int) ($plate->thickness_mm ?? $plan->thickness_mm ?? 0),
                            'width_mm' => $plate->width_mm ? (int) $plate->width_mm : null,
                            'length_mm' => $plate->length_mm ? (int) $plate->length_mm : null,
                            'plate_label' => $plate->plate_label,
                            'material_stock_piece_id' => $plate->material_stock_piece_id,
                            'plate_number' => $msp?->plate_number,
                            'heat_number' => $msp?->heat_number,
                            'mtc_number' => $msp?->mtc_number,
                            'allocations' => $allocMap,
                        ];

                        $cuttingPlanPlateMap[(int) $plate->id] = $payload;
                        $cuttingPlanPlates[] = $payload;
                    }
                }

                // Stable ordering: by plan name then plate label/id
                usort($cuttingPlanPlates, function ($a, $b) {
                    $pa = (string) ($a['plan_name'] ?? '');
                    $pb = (string) ($b['plan_name'] ?? '');
                    if ($pa !== $pb) return $pa <=> $pb;

                    $la = (string) ($a['plate_label'] ?? '');
                    $lb = (string) ($b['plate_label'] ?? '');
                    if ($la !== $lb) return $la <=> $lb;

                    return ((int) $a['id']) <=> ((int) $b['id']);
                });
            }
        }

        return view('projects.production_traceability.edit', [
            'project' => $project,
            'dpr' => $production_dpr,
            'activity' => $activity,
            'stockItems' => $stockItems,
            'pieces' => $pieces,
            'traceLines' => $traceLines,
            'capturedPieces' => $capturedPieces,
            'isCutting' => $isCutting,
            'isFitup' => $isFitup,
            'cuttingPlanPlates' => $cuttingPlanPlates,
            'cuttingPlanPlateMap' => $cuttingPlanPlateMap,
        ]);
    }

    public function update(Request $request, Project $project, ProductionDpr $production_dpr)
    {
        if ((int) $production_dpr->plan->project_id !== (int) $project->id) {
            abort(404);
        }

        // HARDEN: only allow traceability on APPROVED DPR
        if ($production_dpr->status !== 'approved') {
            return back()->with('error', 'Traceability save is allowed only after DPR approval.');
        }

        $production_dpr->load(['activity', 'plan', 'lines.planItem', 'lines.planItemActivity.activity']);
        $activity = $production_dpr->activity;

        $isCutting = str_contains(strtoupper((string) $activity->code), 'CUT')
            || str_contains(strtoupper((string) $activity->name), 'CUT');

        $isFitup = (bool) ($activity->is_fitupp ?? false);

        if (! ($isCutting || $isFitup)) {
            return back()->with('error', 'Traceability capture is only required for Cutting or Fitup activities.');
        }

        if ($isCutting) {
            // New: Cutting Batch mode (one plate -> many parts + multiple remnants)
            // Detect by presence of batch fields.
            if ($request->has('batch_mother_stock_item_id') || $request->has('batch_lines')) {
                return $this->saveCuttingBatch($request, $project, $production_dpr);
            }

            return $this->saveCutting($request, $project, $production_dpr);
        }

        return $this->saveFitup($request, $project, $production_dpr);
    }

    /**
     * Legacy / Single-line cutting capture.
     * Improved: supports partial tagging (multiple plates for same DPR line) by
     * comparing required qty (Nos) vs already created pieces.
     */

    /**
     * Legacy / Single-line cutting capture.
     *
     * Improvements:
     *  - Allows saving only the lines you entered (Pieces can be 0 to skip).
     *  - Supports partial tagging across multiple plates by comparing DPR Qty (Nos) vs already created pieces.
     *  - Prevents selecting the same mother plate in multiple rows in a single submission.
     */
    protected function saveCutting(Request $request, Project $project, ProductionDpr $dpr)
    {
        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.dpr_line_id' => ['required', 'integer', 'exists:production_dpr_lines,id'],

            // Optional when Pieces=0 (skip). Required only when Pieces>0.
            'rows.*.mother_stock_item_id' => ['nullable', 'integer', 'exists:store_stock_items,id'],
            'rows.*.piece_count' => ['nullable', 'integer', 'min:0', 'max:500'],

            'rows.*.piece_thickness_mm' => ['nullable', 'integer', 'min:0'],
            'rows.*.piece_width_mm' => ['nullable', 'integer', 'min:0'],
            'rows.*.piece_length_mm' => ['nullable', 'integer', 'min:0'],
            'rows.*.piece_weight_kg' => ['nullable', 'numeric', 'min:0'],

            // Legacy mode: single remnant per stock selection
            'rows.*.remnant_width_mm' => ['nullable', 'integer', 'min:0'],
            'rows.*.remnant_length_mm' => ['nullable', 'integer', 'min:0'],
            'rows.*.remnant_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'rows.*.remnant_is_usable' => ['nullable', 'boolean'],
            'rows.*.remnant_remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $activeRows = collect($data['rows'] ?? [])
            ->filter(fn ($r) => (int) ($r['piece_count'] ?? 0) > 0)
            ->values();

        if ($activeRows->isEmpty()) {
            return back()->withInput()->with('error', 'Please enter Pieces > 0 for at least one line.');
        }

        // Validate mother stock present for all active rows
        foreach ($activeRows as $r) {
            if (empty($r['mother_stock_item_id'])) {
                return back()->withInput()->with('error', 'Please select Mother Stock for all lines where Pieces > 0.');
            }
        }

        // Prevent duplicate mother stock in same submission
        $motherIds = $activeRows
            ->pluck('mother_stock_item_id')
            ->map(fn ($v) => (int) $v);

        if ($motherIds->count() !== $motherIds->unique()->count()) {
            return back()
                ->withInput()
                ->with('error', 'Same mother plate is selected in multiple rows. Use Cutting Batch mode (one plate -> many parts) for this, or select different plates.');
        }

        try {
            DB::transaction(function () use ($data, $project, $dpr) {
                foreach (($data['rows'] ?? []) as $row) {
                    $count = (int) ($row['piece_count'] ?? 0);
                    if ($count <= 0) {
                        continue;
                    }

                    $line = ProductionDprLine::lockForUpdate()
                        ->where('production_dpr_id', $dpr->id)
                        ->where('id', (int) $row['dpr_line_id'])
                        ->with(['planItem'])
                        ->firstOrFail();

                    // Only allow traceability for completed DPR lines.
                    if ((int) ($line->is_completed ?? 0) !== 1) {
                        continue;
                    }

                    $requiredQty = (float) ($line->qty ?? 0);
                    $requiredPieces = (int) round($requiredQty);

                    if ($requiredPieces <= 0) {
                        throw new \RuntimeException('Cannot tag pieces because DPR Qty is 0 for item ' . ($line->planItem?->item_code ?? ('Line#' . $line->id)) . '.');
                    }

                    // Qty must be a whole number for piece-based traceability.
                    if (abs($requiredQty - $requiredPieces) > 0.0001) {
                        throw new \RuntimeException('DPR Qty must be a whole number (Nos) for item ' . ($line->planItem?->item_code ?? ('Line#' . $line->id)) . '.');
                    }

                    $already = (int) ProductionPiece::query()
                        ->where('production_dpr_line_id', $line->id)
                        ->count();

                    $remaining = $requiredPieces - $already;

                    if ($remaining <= 0) {
                        throw new \RuntimeException('This line is already fully tagged: ' . ($line->planItem?->item_code ?? ('Line#' . $line->id)) . '.');
                    }

                    if ($count > $remaining) {
                        throw new \RuntimeException('Pieces entered exceeds remaining qty for item ' . ($line->planItem?->item_code ?? ('Line#' . $line->id)) . '. Remaining: ' . $remaining);
                    }

                    $stock = StoreStockItem::lockForUpdate()->findOrFail((int) $row['mother_stock_item_id']);
                    if ($stock->status !== 'available') {
                        throw new \RuntimeException('Selected mother plate/stock #' . $stock->id . ' is not available (already consumed/scrap).');
                    }

                    for ($i = 0; $i < $count; $i++) {
                        $pieceNo = ProductionPiece::generatePieceNumber($project->code);

                        ProductionPiece::create([
                            'project_id' => $project->id,
                            'production_plan_id' => $dpr->production_plan_id,
                            'production_plan_item_id' => $line->production_plan_item_id,
                            'production_dpr_line_id' => $line->id,
                            'mother_stock_item_id' => $stock->id,
                            'piece_number' => $pieceNo,
                            'piece_tag' => $line->planItem?->item_code,
                            'thickness_mm' => $row['piece_thickness_mm'] ?? $stock->thickness_mm,
                            'width_mm' => $row['piece_width_mm'] ?? null,
                            'length_mm' => $row['piece_length_mm'] ?? null,
                            'weight_kg' => $row['piece_weight_kg'] ?? null,
                            'plate_number' => $stock->plate_number,
                            'heat_number' => $stock->heat_number,
                            'mtc_number' => $stock->mtc_number,
                            'status' => 'available',
                        ]);
                    }

                    // Mark mother stock consumed (legacy assumption: this stock item is fully used in this row).
                    $stock->qty_pcs_available = 0;
                    $stock->weight_kg_available = 0;
                    $stock->status = 'consumed';
                    $stock->remarks = trim(($stock->remarks ?? '') . "\nConsumed in Production DPR #{$dpr->id} (Cutting)");
                    $stock->save();

                    // Remnant capture
                    $remW = $row['remnant_width_mm'] ?? null;
                    $remL = $row['remnant_length_mm'] ?? null;
                    $remWt = $row['remnant_weight_kg'] ?? null;

                    if ($remW || $remL || $remWt) {
                        $isUsable = (bool) ($row['remnant_is_usable'] ?? true);
                        $remnantStockId = null;

                        if ($isUsable) {
                            $isClientMaterial = (bool) $stock->is_client_material;
                            $new = StoreStockItem::create([
                                'material_receipt_line_id' => null,
                                'item_id' => $stock->item_id,
                                'brand' => $stock->brand,
                                'project_id' => $isClientMaterial ? ((int) ($stock->project_id ?: $project->id)) : null,
                                'client_party_id' => $isClientMaterial ? $stock->client_party_id : null,
                                'is_client_material' => $isClientMaterial,
                                'is_remnant' => true,
                                'mother_stock_item_id' => $stock->id,
                                'material_category' => $stock->material_category,

                                'thickness_mm' => $stock->thickness_mm,
                                'width_mm' => $remW ?: $stock->width_mm,
                                'length_mm' => $remL ?: $stock->length_mm,

                                'section_profile' => $stock->section_profile,
                                'grade' => $stock->grade,

                                'plate_number' => $stock->plate_number,
                                'heat_number' => $stock->heat_number,
                                'mtc_number' => $stock->mtc_number,

                                'qty_pcs_total' => 1,
                                'qty_pcs_available' => 1,
                                'weight_kg_total' => $remWt,
                                'weight_kg_available' => $remWt,

                                'source_type' => 'production_remnant',
                                'source_reference' => "DPR#{$dpr->id}",
                                'opening_unit_rate' => $stock->opening_unit_rate,
                                'opening_rate_uom_id' => $stock->opening_rate_uom_id,
                                'status' => 'available',
                                'location' => $stock->location,
                                'remarks' => 'Usable remnant generated from stock #' . $stock->id,
                            ]);

                            $remnantStockId = $new->id;
                        }

                        ProductionRemnant::create([
                            'project_id' => $project->id,
                            'production_plan_id' => $dpr->production_plan_id,
                            'production_dpr_line_id' => $line->id,
                            'mother_stock_item_id' => $stock->id,
                            'remnant_stock_item_id' => $remnantStockId,
                            'thickness_mm' => $stock->thickness_mm,
                            'width_mm' => $remW,
                            'length_mm' => $remL,
                            'weight_kg' => $remWt,
                            'is_usable' => $isUsable,
                            'status' => $isUsable ? 'available' : 'scrap',
                            'remarks' => $row['remnant_remarks'] ?? null,
                        ]);
                    }

                    $newAlready = $already + $count;
                    $isDone = ($newAlready >= $requiredPieces);

                    $line->traceability_done = $isDone;
                    $line->traceability_done_at = $isDone ? ($line->traceability_done_at ?: now()) : null;
                    $line->save();

                    ProductionAudit::log(
                        $project->id,
                        'traceability.cutting',
                        'ProductionDprLine',
                        $line->id,
                        'Cutting traceability saved',
                        [
                            'mother_stock_item_id' => $stock->id,
                            'piece_count' => $count,
                            'required_pieces' => $requiredPieces,
                            'already_pieces' => $newAlready,
                        ]
                    );
                }
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('projects.production-dprs.show', [$project, $dpr])
            ->with('success', 'Cutting traceability saved.');
    }
    protected function saveCuttingBatch(Request $request, Project $project, ProductionDpr $dpr)
    {
        $data = $request->validate([
            'batch_cutting_plan_plate_id' => ['nullable', 'integer', 'exists:cutting_plan_plates,id'],
            'batch_mother_stock_item_id' => ['required', 'integer', 'exists:store_stock_items,id'],
            'batch_lines' => ['required', 'array'],
            'batch_lines.*.dpr_line_id' => ['required', 'integer', 'exists:production_dpr_lines,id'],
            'batch_lines.*.piece_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'batch_lines.*.piece_thickness_mm' => ['nullable', 'integer', 'min:0'],
            'batch_lines.*.piece_width_mm' => ['nullable', 'integer', 'min:0'],
            'batch_lines.*.piece_length_mm' => ['nullable', 'integer', 'min:0'],
            'batch_lines.*.piece_weight_kg' => ['nullable', 'numeric', 'min:0'],

            'batch_remnants' => ['nullable', 'array'],
            'batch_remnants.*.width_mm' => ['nullable', 'integer', 'min:0'],
            'batch_remnants.*.length_mm' => ['nullable', 'integer', 'min:0'],
            'batch_remnants.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'batch_remnants.*.is_usable' => ['nullable', 'boolean'],
            'batch_remnants.*.remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $hasAny = false;
        foreach (($data['batch_lines'] ?? []) as $r) {
            if ((int) ($r['piece_count'] ?? 0) > 0) {
                $hasAny = true;
                break;
            }
        }

        if (! $hasAny) {
            return back()->withInput()->with('error', 'Please enter at least one line with Pieces > 0 for this batch.');
        }

        try {
            DB::transaction(function () use ($data, $project, $dpr) {
                // Optional link to Design Cutting Plan plate (for auto-fill & audit)
                $cpPlate = null;
                if (! empty($data['batch_cutting_plan_plate_id'])) {
                    $cpPlate = CuttingPlanPlate::query()
                        ->with(['cuttingPlan'])
                        ->find((int) $data['batch_cutting_plan_plate_id']);

                    $bomId = (int) ($dpr->plan?->bom_id ?? 0);

                    if (! $cpPlate || ! $cpPlate->cuttingPlan || (int) $cpPlate->cuttingPlan->project_id !== (int) $project->id || (int) $cpPlate->cuttingPlan->bom_id !== $bomId) {
                        throw new \RuntimeException('Selected cutting plan plate does not belong to this project/BOM.');
                    }
                }

                $stock = StoreStockItem::lockForUpdate()->findOrFail((int) $data['batch_mother_stock_item_id']);

                if ($stock->status !== 'available') {
                    throw new \RuntimeException('Selected mother plate/stock is not available (already consumed/scrap).');
                }

                $totalPieces = 0;
                $anchorLineId = null;

                foreach ($data['batch_lines'] as $row) {
                    $count = (int) ($row['piece_count'] ?? 0);
                    if ($count <= 0) {
                        continue;
                    }

                    $line = ProductionDprLine::lockForUpdate()
                        ->where('production_dpr_id', $dpr->id)
                        ->where('id', (int) $row['dpr_line_id'])
                        ->with(['planItem'])
                        ->firstOrFail();

                    // Only allow traceability for completed DPR lines.
                    if ((int) ($line->is_completed ?? 0) !== 1) {
                        continue;
                    }

                    $requiredQty = (float) ($line->qty ?? 0);
                    $requiredPieces = (int) round($requiredQty);

                    if ($requiredPieces <= 0) {
                        throw new \RuntimeException('DPR Qty is 0 for item ' . ($line->planItem?->item_code ?? ('Line#' . $line->id)) . '. Please ensure Qty is entered before approving DPR.');
                    }

                    $already = (int) ProductionPiece::query()
                        ->where('production_dpr_line_id', $line->id)
                        ->count();

                    $remaining = $requiredPieces - $already;

                    if ($remaining <= 0) {
                        // Already fully tagged
                        if (! $line->traceability_done) {
                            $line->traceability_done = true;
                            $line->traceability_done_at = $line->traceability_done_at ?: now();
                            $line->save();
                        }
                        continue;
                    }

                    if ($count > $remaining) {
                        throw new \RuntimeException('Pieces entered exceeds remaining qty for item ' . ($line->planItem?->item_code ?? ('Line#' . $line->id)) . '. Remaining: ' . $remaining);
                    }

                    $anchorLineId = $anchorLineId ?: $line->id;

                    for ($i = 0; $i < $count; $i++) {
                        $pieceNo = ProductionPiece::generatePieceNumber($project->code);

                        ProductionPiece::create([
                            'project_id' => $project->id,
                            'production_plan_id' => $dpr->production_plan_id,
                            'production_plan_item_id' => $line->production_plan_item_id,
                            'production_dpr_line_id' => $line->id,
                            'mother_stock_item_id' => $stock->id,
                            'piece_number' => $pieceNo,
                            'piece_tag' => $line->planItem?->item_code,
                            'thickness_mm' => $row['piece_thickness_mm'] ?? $stock->thickness_mm,
                            'width_mm' => $row['piece_width_mm'] ?? null,
                            'length_mm' => $row['piece_length_mm'] ?? null,
                            'weight_kg' => $row['piece_weight_kg'] ?? null,
                            'plate_number' => $stock->plate_number,
                            'heat_number' => $stock->heat_number,
                            'mtc_number' => $stock->mtc_number,
                            'status' => 'available',
                        ]);
                    }

                    $newAlready = $already + $count;

                    $line->traceability_done = ($newAlready >= $requiredPieces);
                    $line->traceability_done_at = $line->traceability_done ? ($line->traceability_done_at ?: now()) : null;
                    $line->save();

                    $totalPieces += $count;
                }

                if ($totalPieces <= 0) {
                    throw new \RuntimeException('No pieces were created. Please enter Pieces > 0 for at least one item.');
                }

                // Consume the mother plate/stock once for this batch.
                $stock->qty_pcs_available = 0;
                $stock->weight_kg_available = 0;
                $stock->status = 'consumed';
                $cpSuffix = $cpPlate ? (' | CPPlate#' . $cpPlate->id) : '';
                $stock->remarks = trim(($stock->remarks ?? '') . "\nConsumed in Production DPR #{$dpr->id} (Cutting Batch)" . $cpSuffix);
                $stock->save();

                // Multiple remnants from same plate
                $remnants = $data['batch_remnants'] ?? [];
                $remCount = 0;

                foreach ($remnants as $r) {
                    $remW = $r['width_mm'] ?? null;
                    $remL = $r['length_mm'] ?? null;
                    $remWt = $r['weight_kg'] ?? null;

                    // Skip empty rows
                    if (!($remW || $remL || $remWt)) {
                        continue;
                    }

                    $isUsable = (bool) ($r['is_usable'] ?? true);
                    $remnantStockId = null;

                    if ($isUsable) {
                        $isClientMaterial = (bool) $stock->is_client_material;
                        $new = StoreStockItem::create([
                            'material_receipt_line_id' => null,
                            'item_id' => $stock->item_id,
                            'brand' => $stock->brand,
                            'project_id' => $isClientMaterial ? ((int) ($stock->project_id ?: $project->id)) : null,
                            'client_party_id' => $isClientMaterial ? $stock->client_party_id : null,
                            'is_client_material' => $isClientMaterial,
                            'is_remnant' => true,
                            'mother_stock_item_id' => $stock->id,
                            'material_category' => $stock->material_category,

                            'thickness_mm' => $stock->thickness_mm,
                            'width_mm' => $remW ?: $stock->width_mm,
                            'length_mm' => $remL ?: $stock->length_mm,

                            'section_profile' => $stock->section_profile,
                            'grade' => $stock->grade,

                            'plate_number' => $stock->plate_number,
                            'heat_number' => $stock->heat_number,
                            'mtc_number' => $stock->mtc_number,

                            'qty_pcs_total' => 1,
                            'qty_pcs_available' => 1,
                            'weight_kg_total' => $remWt,
                            'weight_kg_available' => $remWt,

                            'source_type' => 'production_remnant',
                            'source_reference' => $cpPlate ? ("DPR#{$dpr->id} (Batch) CPPlate#" . $cpPlate->id) : "DPR#{$dpr->id} (Batch)",
                            'opening_unit_rate' => $stock->opening_unit_rate,
                            'opening_rate_uom_id' => $stock->opening_rate_uom_id,
                            'status' => 'available',
                            'location' => $stock->location,
                            'remarks' => 'Usable remnant generated from stock #' . $stock->id,
                        ]);

                        $remnantStockId = $new->id;
                    }

                    ProductionRemnant::create([
                        'project_id' => $project->id,
                        'production_plan_id' => $dpr->production_plan_id,
                        'production_dpr_line_id' => $anchorLineId,
                        'mother_stock_item_id' => $stock->id,
                        'remnant_stock_item_id' => $remnantStockId,
                        'thickness_mm' => $stock->thickness_mm,
                        'width_mm' => $remW,
                        'length_mm' => $remL,
                        'weight_kg' => $remWt,
                        'is_usable' => $isUsable,
                        'status' => $isUsable ? 'available' : 'scrap',
                        'remarks' => $r['remarks'] ?? null,
                    ]);

                    $remCount++;
                }

                ProductionAudit::log(
                    $project->id,
                    'traceability.cutting_batch',
                    'ProductionDpr',
                    $dpr->id,
                    'Cutting batch traceability saved',
                    [
                        'mother_stock_item_id' => $stock->id,
                        'total_pieces' => $totalPieces,
                        'remnants' => $remCount,
                        'cutting_plan_plate_id' => $cpPlate?->id,
                        'cutting_plan_id' => $cpPlate?->cutting_plan_id,
                        'cutting_plan_name' => $cpPlate?->cuttingPlan?->name,
                    ]
                );
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('projects.production-dprs.show', [$project, $dpr])
            ->with('success', 'Cutting batch traceability saved.');
    }

    protected function saveFitup(Request $request, Project $project, ProductionDpr $dpr)
    {
        $data = $request->validate([
            'assemblies' => ['required', 'array'],
            'assemblies.*.dpr_line_id' => ['required', 'integer', 'exists:production_dpr_lines,id'],
            'assemblies.*.piece_ids' => ['nullable', 'array'],
            'assemblies.*.piece_ids.*' => ['integer', 'exists:production_pieces,id'],
            'assemblies.*.assembly_weight_kg' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $project, $dpr) {
            foreach ($data['assemblies'] as $row) {
                $line = ProductionDprLine::lockForUpdate()
                    ->where('production_dpr_id', $dpr->id)
                    ->where('id', (int) $row['dpr_line_id'])
                    ->with(['planItem'])
                    ->firstOrFail();

                // Only allow traceability for completed DPR lines.
                if ((int) ($line->is_completed ?? 0) !== 1) {
                    continue;
                }

                if ($line->traceability_done) {
                    continue;
                }

                $planItem = $line->planItem;

                $assembly = ProductionAssembly::create([
                    'project_id' => $project->id,
                    'production_plan_id' => $dpr->production_plan_id,
                    'production_plan_item_id' => $line->production_plan_item_id,
                    'production_dpr_line_id' => $line->id,
                    'assembly_mark' => $planItem?->assembly_mark ?: ($planItem?->item_code ?: 'ASM'),
                    'assembly_type' => $planItem?->assembly_type,
                    'weight_kg' => $row['assembly_weight_kg'] ?? $planItem?->planned_weight_kg,
                    'status' => 'in_progress',
                ]);

                $pieceIds = $row['piece_ids'] ?? [];

                foreach ($pieceIds as $pid) {
                    // HARDEN: prevent consuming already consumed piece
                    $piece = ProductionPiece::lockForUpdate()
                        ->where('project_id', $project->id)
                        ->where('status', 'available')
                        ->find($pid);

                    if (! $piece) {
                        continue;
                    }

                    ProductionAssemblyComponent::create([
                        'production_assembly_id' => $assembly->id,
                        'production_piece_id' => $piece->id,
                    ]);

                    $piece->status = 'consumed';
                    $piece->save();
                }

                $line->traceability_done = true;
                $line->traceability_done_at = now();
                $line->save();

                ProductionAudit::log(
                    $project->id,
                    'traceability.fitup',
                    'ProductionDprLine',
                    $line->id,
                    'Fitup traceability saved',
                    ['assembly_id' => $assembly->id, 'piece_count' => count($pieceIds)]
                );
            }
        });

        return redirect()
            ->route('projects.production-dprs.show', [$project, $dpr])
            ->with('success', 'Fitup traceability saved.');
    }
}


