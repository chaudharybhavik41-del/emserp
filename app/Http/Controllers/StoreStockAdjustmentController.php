<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Party;
use App\Models\Project;
use App\Models\StoreStockAdjustment;
use App\Models\StoreStockAdjustmentLine;
use App\Models\StoreStockItem;
use App\Models\Uom;
use App\Models\ActivityLog;
use App\Services\Accounting\StoreStockAdjustmentPostingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreStockAdjustmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:store.stock.adjustment.view')
            ->only(['index', 'show']);

        $this->middleware('permission:store.stock.adjustment.create')
            ->only(['create', 'store']);
        $this->middleware('permission:store.stock.adjustment.create')
            ->only(['edit', 'update']);

        // Reuse the same posting permission as Store Issues (keeps roles simple)
        $this->middleware('permission:store.issue.post_to_accounts')
            ->only(['postToAccounts']);
    }

    public function index(Request $request): View
    {
        $query = StoreStockAdjustment::query()
            ->with(['project', 'createdBy', 'lines.stockItem']);

        if ($request->filled('project_id')) {
            if ($request->project_id === 'none') {
                $query->whereNull('project_id');
            } else {
                $query->where('project_id', $request->project_id);
            }
        }

        $adjustments = $query->orderByDesc('adjustment_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $adjustments->getCollection()->transform(function (StoreStockAdjustment $adjustment) {
            $totalAmount = 0.0;
            $hasValuation = false;

            foreach ($adjustment->lines as $line) {
                $qty = (float) ($line->quantity ?? 0);
                $rate = (float) ($line->stockItem?->opening_unit_rate ?? 0);

                if ($qty <= 0 || $rate <= 0) {
                    continue;
                }

                $hasValuation = true;
                $totalAmount += $qty * $rate;
            }

            $adjustment->total_amount = $hasValuation ? $totalAmount : null;

            return $adjustment;
        });

        $projects = Project::orderBy('code')->get();

        return view('store_stock_adjustments.index', compact('adjustments', 'projects'));
    }
public function getBrands($id)
{
    $item = Item::find($id);

    if (!$item) {
        return response()->json([]);
    }

    return response()->json($item->brands ?? []);
}
    public function create(): View
    {
        $projects    = Project::orderBy('code')->get();
        $contractors = Party::where('is_contractor', true)->orderBy('name')->get();
        $items       = Item::orderBy('name')->get();
        // dd($items);
        $uoms        = Uom::orderBy('name',)->get();
// $uoms = \App\Models\Uom::where('is_active', 1)->orderBy('name')->get();

        // Build item meta for Brand dropdown (id, name, brands)
        $itemMetaJson = $items->mapWithKeys(function (Item $i) {
            return [$i->id => [
                'id'     => $i->id,
                'name'   => ($i->code ? ($i->code . ' - ') : '') . $i->name,
                'uom_id' => $i->uom_id,
                'brands' => $this->normalizeBrands($i->brands),
            ]];
        })->toJson();

        // Only non-raw stock items for adjustment (no plates/sections)
        // Exclude QC-hold stock
        $stockItems = StoreStockItem::with(['item.uom', 'project'])
            ->whereNotIn('material_category', ['steel_plate', 'steel_section'])
            ->whereNotIn('status', ['blocked_qc'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        // Build UOM meta for decimal places
        $uomMetaJson = $uoms->mapWithKeys(function (Uom $u) {
            return [$u->id => [
                'id'             => $u->id,
                'decimal_places' => $u->decimal_places ?? 3,
            ]];
        })->toJson();

        return view('store_stock_adjustments.create', [
            'projects'    => $projects,
            'contractors' => $contractors,
            'items'       => $items,
            'uoms'        => $uoms,
            'itemMetaJson' => $itemMetaJson,
            'uomMetaJson'  => $uomMetaJson,
            'stockItems'  => $stockItems,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $baseRules = [
            'adjustment_date'   => ['required', 'date'],
            'adjustment_type'   => ['required', 'in:opening,increase,decrease'],
            'project_id'        => ['nullable'],
            'reason'            => ['nullable', 'string', 'max:255'],
            'remarks'           => ['nullable', 'string'],
        ];

        $type = $request->input('adjustment_type');

        if ($type === 'opening') {
            $rules = array_merge($baseRules, [
                'opening_lines'                         => ['required', 'array', 'min:1'],
                'opening_lines.*.item_id'              => ['required', 'integer', 'exists:items,id'],
                'opening_lines.*.uom_id'               => ['required', 'integer', 'exists:uoms,id'],
                'opening_lines.*.brand'               => ['nullable', 'string', 'max:100'],
                'opening_lines.*.quantity'             => ['required', 'numeric', 'min:0.001'],
                // Optional: if provided, Opening becomes valued and can be posted to accounts
                'opening_lines.*.unit_rate'            => ['nullable', 'numeric', 'min:0'],
                'opening_lines.*.remarks'              => ['nullable', 'string', 'max:255'],
            ]);
        } else {
            // increase / decrease
            $rules = array_merge($baseRules, [
                'adjustment_lines'                         => ['required', 'array', 'min:1'],
                'adjustment_lines.*.store_stock_item_id'   => ['required', 'integer', 'exists:store_stock_items,id'],
                'adjustment_lines.*.quantity'             => ['required', 'numeric', 'min:0.001'],
                'adjustment_lines.*.remarks'              => ['nullable', 'string', 'max:255'],
            ]);
        }

        $data = $request->validate($rules);

        DB::beginTransaction();

        try {
            $adjustment                  = new StoreStockAdjustment();
            $adjustment->adjustment_date = $data['adjustment_date'];
            $adjustment->adjustment_type = $type;
            
            $projectId = $request->input('project_id');
            if ($projectId === 'none' || $projectId === '0' || empty($projectId)) {
                $adjustment->project_id = null;
            } else {
                $adjustment->project_id = $projectId;
            }

            $adjustment->reason          = $data['reason'] ?? null;
            $adjustment->remarks         = $data['remarks'] ?? null;
            $adjustment->status          = 'posted';
            $adjustment->created_by      = $request->user()?->id;
            $adjustment->save();

            // Generate reference number via central service (same pattern STAD-YY-XXXX)
            $adjustment->reference_number = app(\App\Services\DocumentNumberService::class)
                ->stockAdjustment($adjustment);
            $adjustment->save();

            if ($type === 'opening') {
                $hasValuedOpening = false;

                // Opening: create new stock rows (non-raw, treated as consumable by default)
                foreach ($data['opening_lines'] as $lineData) {
                    $qty = (float) $lineData['quantity'];
                    if ($qty <= 0) {
                        continue;
                    }

                    // Brand (optional)
                    $brand = trim((string) ($lineData['brand'] ?? ''));
                    if ($brand === '') {
                        $brand = null;
                    }

                    $unitRate = (float) ($lineData['unit_rate'] ?? 0);
                    if ($unitRate > 0.0001) {
                        $hasValuedOpening = true;
                    } else {
                        $unitRate = 0.0;
                    }

                    $stock = StoreStockItem::create([
                        'material_receipt_line_id' => null,
                        'item_id'                  => $lineData['item_id'],
                        'brand'                    => $brand,
                        'project_id'               => $adjustment->project_id,
                        'is_client_material'       => false,
                        'material_category'        => 'consumable',
                        'thickness_mm'             => null,
                        'width_mm'                 => null,
                        'length_mm'                => null,
                        'section_profile'          => null,
                        'grade'                    => null,
                        // For non-raw items, PCS is not the primary qty
                        'qty_pcs_total'            => 0,
                        'qty_pcs_available'        => 0,
                        'weight_kg_total'          => $qty,
                        'weight_kg_available'      => $qty,
                        // Opening valuation (optional)
                        'opening_unit_rate'        => $unitRate > 0 ? $unitRate : null,
                        'opening_rate_uom_id'      => $unitRate > 0 ? ($lineData['uom_id'] ?? null) : null,
                        'source_type'              => 'opening',
                        'source_reference'         => $adjustment->reference_number,
                        'status'                   => 'available',
                        'remarks'                  => $lineData['remarks'] ?? null,
                    ]);

                    $line = new StoreStockAdjustmentLine();
                    $line->store_stock_adjustment_id = $adjustment->id;
                    $line->store_stock_item_id       = $stock->id;
                    $line->item_id                   = $stock->item_id;
                    $line->brand                     = $stock->brand;
                    $line->uom_id                    = $lineData['uom_id'];
                    $line->project_id                = $adjustment->project_id;
                    $line->quantity                  = $qty; // positive
                    $line->remarks                   = $lineData['remarks'] ?? null;
                    $line->save();
                }

                // If opening lines include a unit rate, allow posting to accounts.
                // Otherwise, mark as not_required (quantity-only opening).
                if ($hasValuedOpening) {
                    $adjustment->accounting_status = 'pending';
                    $adjustment->save();
                } else {
                    $adjustment->accounting_status    = 'not_required';
                    $adjustment->accounting_posted_by = $request->user()?->id;
                    $adjustment->accounting_posted_at = now();
                    $adjustment->save();

                    ActivityLog::logCustom(
                        'accounts_posting_not_required',
                        'Stock adjustment ' . ($adjustment->reference_number ?: ('#' . $adjustment->id)) . ' is OPENING type without valuation. No accounting entry required.',
                        $adjustment,
                        [
                            'accounting_status' => 'not_required',
                            'business_date'     => optional($adjustment->adjustment_date)->toDateString(),
                        ]
                    );
                }
            } else {
                // increase / decrease: adjust existing stock items (non-raw only)
                $isIncrease = $type === 'increase';

                foreach ($data['adjustment_lines'] as $lineData) {
                    $qty = (float) $lineData['quantity'];
                    if ($qty <= 0) {
                        continue;
                    }

                    /** @var StoreStockItem $stock */
                    $stock = StoreStockItem::with('item')
                        ->lockForUpdate()
                        ->findOrFail($lineData['store_stock_item_id']);

                    // Guard: do not allow QC-hold stock here
                    if (($stock->status ?? null) === 'blocked_qc') {
                        throw new \RuntimeException('QC-hold stock cannot be adjusted. Complete QC first.');
                    }

                    // Guard: only non-raw here
                    if (in_array($stock->material_category, ['steel_plate', 'steel_section'], true)) {
                        throw new \RuntimeException('Stock adjustment for raw material is not supported here. Use plate/section flows.');
                    }

                    $currentTotal     = (float) ($stock->weight_kg_total ?? 0);
                    $currentAvailable = (float) ($stock->weight_kg_available ?? 0);

                    if ($isIncrease) {
                        // Increase: add to both total & available
                        $stock->weight_kg_total     = $currentTotal + $qty;
                        $stock->weight_kg_available = $currentAvailable + $qty;
                        $lineQty                    = $qty; // +
                    } else {
                        // Decrease: reduce BOTH total & available (so it is not treated as "issued")
                        if ($qty > $currentAvailable + 0.0001) {
                            throw new \RuntimeException(
                                'Cannot decrease more than available for stock item #' . $stock->id . '.'
                            );
                        }

                        $stock->weight_kg_total     = max(0.0, $currentTotal - $qty);
                        $stock->weight_kg_available = max(0.0, $currentAvailable - $qty);
                        $lineQty                    = -$qty; // -
                    }

                    if ($stock->weight_kg_available <= 0.0001) {
                        $stock->weight_kg_available = 0.0;
                        $stock->status              = 'issued';
                    } else {
                        $stock->status = 'available';
                    }

                    $stock->save();

                    $line = new StoreStockAdjustmentLine();
                    $line->store_stock_adjustment_id = $adjustment->id;
                    $line->store_stock_item_id       = $stock->id;
                    $line->item_id                   = $stock->item_id;
                    $line->brand                     = $stock->brand;
                    $line->uom_id                    = $stock->item?->uom_id;
                    $line->project_id                = $stock->project_id ?? $adjustment->project_id;
                    $line->quantity                  = $lineQty; // + for increase, - for decrease
                    $line->remarks                   = $lineData['remarks'] ?? null;
                    $line->save();
                }
            }

            DB::commit();

            return redirect()
                ->route('store-stock-adjustments.show', $adjustment)
                ->with('success', 'Stock adjustment saved successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors([
                    'general' => 'Failed to save stock adjustment: ' . $e->getMessage(),
                ]);
        }
    }

    /**
     * Post a stock adjustment to Accounts (creates a voucher).
     */
    public function postToAccounts(
        StoreStockAdjustment $storeStockAdjustment,
        StoreStockAdjustmentPostingService $postingService
    ): RedirectResponse {
        try {
            $voucher = $postingService->post($storeStockAdjustment);

            if ($voucher) {
                return redirect()
                    ->route('store-stock-adjustments.show', $storeStockAdjustment)
                    ->with('success', 'Stock adjustment posted to accounts as voucher ' . $voucher->voucher_no . '.');
            }

            return redirect()
                ->route('store-stock-adjustments.show', $storeStockAdjustment)
                ->with('success', 'No accounting entry required for this stock adjustment (client-supplied / opening).');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('store-stock-adjustments.show', $storeStockAdjustment)
                ->with('error', 'Failed to post stock adjustment to accounts: ' . $e->getMessage());
        }
    }

    public function show(StoreStockAdjustment $storeStockAdjustment): View
    {
        $storeStockAdjustment->load([
            'project',
            'createdBy',
            'lines.item.uom',
            'lines.stockItem.item',
            'lines.stockItem.project',
        ]);

        return view('store_stock_adjustments.show', [
            'adjustment' => $storeStockAdjustment,
        ]);
    }


    /**
     * Edit opening stock adjustment (only allowed before posting to accounts).
     */
    public function edit(StoreStockAdjustment $storeStockAdjustment): View
    {
        $storeStockAdjustment->load(['lines.stockItem.item', 'project', 'createdBy']);

        // Allow editing ONLY for Opening adjustments that are not posted to accounts.
        // If we want to support increase/decrease, we'd need more complex logic to reverse impacts.
        if (($storeStockAdjustment->adjustment_type ?? '') !== 'opening') {
            abort(403, 'Only Opening stock adjustments can be edited.');
        }
        if (($storeStockAdjustment->accounting_status ?? 'pending') === 'posted') {
            abort(403, 'This stock adjustment is already posted to accounts and cannot be edited.');
        }

        $projects    = Project::orderBy('code')->get();
        $contractors = Party::where('is_contractor', true)->orderBy('name')->get();
        $items       = Item::orderBy('name')->get();
        $uoms        = Uom::orderBy('name',)->get();

        // Build item meta for Brand dropdown (id, name, brands)
        $itemMetaJson = $items->mapWithKeys(function (Item $i) {
            return [$i->id => [
                'id'     => $i->id,
                'name'   => ($i->code ? ($i->code . ' - ') : '') . $i->name,
                'uom_id' => $i->uom_id,
                'brands' => $this->normalizeBrands($i->brands),
            ]];
        })->toJson();

        // Only non-raw stock items for adjustment (no plates/sections)
        $stockItems = StoreStockItem::with(['item.uom', 'project'])
            ->whereNotIn('material_category', ['steel_plate', 'steel_section'])
            ->whereNotIn('status', ['blocked_qc'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        // Build UOM meta for decimal places
        $uomMetaJson = $uoms->mapWithKeys(function (Uom $u) {
            return [$u->id => [
                'id'             => $u->id,
                'decimal_places' => $u->decimal_places ?? 3,
            ]];
        })->toJson();

        return view('store_stock_adjustments.edit', [
            'adjustment'   => $storeStockAdjustment,
            'projects'     => $projects,
            'contractors'  => $contractors,
            'items'        => $items,
            'uoms'         => $uoms,
            'itemMetaJson' => $itemMetaJson,
            'uomMetaJson'  => $uomMetaJson,
            'stockItems'   => $stockItems,
        ]);
    }

    public function update(Request $request, StoreStockAdjustment $storeStockAdjustment): RedirectResponse
    {
        $storeStockAdjustment->load(['lines.stockItem']);

        if (($storeStockAdjustment->adjustment_type ?? '') !== 'opening') {
            return back()->withErrors(['general' => 'Only Opening stock adjustments can be edited for now.']);
        }
        if (($storeStockAdjustment->accounting_status ?? 'pending') === 'posted') {
            return back()->withErrors(['general' => 'This stock adjustment is already posted to accounts and cannot be edited.']);
        }

        $data = $request->validate([
            'adjustment_date'               => ['required', 'date'],
            'project_id'                    => ['nullable'],
            'reason'                        => ['nullable', 'string', 'max:255'],
            'remarks'                       => ['nullable', 'string'],
            'opening_lines'                 => ['required_if:adjustment_type,opening', 'array'],
            'opening_lines.*.line_id'       => ['nullable', 'integer'],
            'opening_lines.*.stock_item_id' => ['nullable', 'integer'],
            'opening_lines.*.item_id'       => ['required', 'integer', 'exists:items,id'],
            'opening_lines.*.uom_id'        => ['required', 'integer', 'exists:uoms,id'],
            'opening_lines.*.brand'         => ['nullable', 'string', 'max:100'],
            'opening_lines.*.quantity'      => ['required', 'numeric', 'min:0.001'],
            'opening_lines.*.unit_rate'     => ['nullable', 'numeric', 'min:0'],
            'opening_lines.*.remarks'       => ['nullable', 'string', 'max:255'],
        ]);

        DB::beginTransaction();

        try {
            $storeStockAdjustment->adjustment_date = $data['adjustment_date'];
            
            $projectId = $request->input('project_id');
            if ($projectId === 'none' || $projectId === '0' || empty($projectId)) {
                $storeStockAdjustment->project_id = null;
            } else {
                $storeStockAdjustment->project_id = $projectId;
            }

            $storeStockAdjustment->reason          = $data['reason'] ?? null;
            $storeStockAdjustment->remarks         = $data['remarks'] ?? null;
            $storeStockAdjustment->save();

            $hasValuedOpening = false;
            $inputLineIds = collect($data['opening_lines'] ?? [])->pluck('line_id')->filter()->toArray();

            // 1. Delete lines that are no longer present
            foreach ($storeStockAdjustment->lines as $existingLine) {
                if (!in_array($existingLine->id, $inputLineIds)) {
                    $stock = $existingLine->stockItem;
                    if ($stock) {
                        // Check if issued
                        $issued = (float) ($stock->weight_kg_total - $stock->weight_kg_available);
                        if ($issued > 0.0001) {
                            throw new \RuntimeException("Cannot delete line #{$existingLine->id} as stock has already been issued ({$issued}).");
                        }
                        $stock->delete();
                    }
                    $existingLine->delete();
                }
            }

            // 2. Update existing lines + create new ones
            foreach ($data['opening_lines'] ?? [] as $row) {
                $lineId = $row['line_id'] ?? null;
                $qty = (float) ($row['quantity'] ?? 0);
                $brand = isset($row['brand']) && trim((string) $row['brand']) !== '' ? trim((string) $row['brand']) : null;
                $unitRate = (float) ($row['unit_rate'] ?? 0);
                $unitRate = $unitRate > 0 ? $unitRate : null;

                if ($unitRate !== null) {
                    $hasValuedOpening = true;
                }

                if ($lineId) {
                    $line = $storeStockAdjustment->lines()->findOrFail($lineId);
                    $stock = $line->stockItem;

                    if ($stock) {
                        // Guard: item cannot be changed for existing opening lines
                        if ((int)$row['item_id'] !== (int)$stock->item_id) {
                            throw new \RuntimeException('Item cannot be changed for existing opening lines.');
                        }

                        $issued = (float) ($stock->weight_kg_total - $stock->weight_kg_available);
                        if ($qty < $issued - 0.0001) {
                            throw new \RuntimeException("Cannot reduce quantity below issued amount ({$issued}) for line #{$lineId}.");
                        }

                        $stock->weight_kg_total     = $qty;
                        $stock->weight_kg_available = max(0.0, $qty - $issued);
                        $stock->brand               = $brand;
                        $stock->opening_unit_rate   = $unitRate;
                        $stock->opening_rate_uom_id = $unitRate !== null ? (int) $row['uom_id'] : null;
                        $stock->remarks             = $row['remarks'] ?? null;
                        $stock->save();
                    }

                    $line->uom_id     = (int) $row['uom_id'];
                    $line->brand      = $brand;
                    $line->quantity   = $qty;
                    $line->remarks    = $row['remarks'] ?? null;
                    $line->save();
                } else {
                    // New line
                    $stock = StoreStockItem::create([
                        'item_id'                  => (int) $row['item_id'],
                        'brand'                    => $brand,
                        'project_id'               => $storeStockAdjustment->project_id,
                        'is_client_material'       => false,
                        'material_category'        => 'consumable',
                        'weight_kg_total'          => $qty,
                        'weight_kg_available'      => $qty,
                        'source_type'              => 'opening',
                        'source_reference'         => $storeStockAdjustment->reference_number,
                        'opening_unit_rate'        => $unitRate,
                        'opening_rate_uom_id'      => $unitRate !== null ? (int) $row['uom_id'] : null,
                        'status'                   => 'available',
                        'remarks'                  => $row['remarks'] ?? null,
                    ]);

                    $line = new StoreStockAdjustmentLine();
                    $line->store_stock_adjustment_id = $storeStockAdjustment->id;
                    $line->store_stock_item_id       = $stock->id;
                    $line->item_id                   = $stock->item_id;
                    $line->uom_id                    = (int) $row['uom_id'];
                    $line->project_id                = $storeStockAdjustment->project_id;
                    $line->brand                     = $brand;
                    $line->quantity                  = $qty;
                    $line->remarks                   = $row['remarks'] ?? null;
                    $line->save();
                }
            }

            // Update accounting status
            if ($hasValuedOpening) {
                $storeStockAdjustment->accounting_status    = 'pending';
                $storeStockAdjustment->accounting_posted_by = null;
                $storeStockAdjustment->accounting_posted_at = null;
            } else {
                $storeStockAdjustment->accounting_status    = 'not_required';
                $storeStockAdjustment->accounting_posted_by = $request->user()?->id;
                $storeStockAdjustment->accounting_posted_at = now();
            }

            $storeStockAdjustment->save();

            DB::commit();

            return redirect()
                ->route('store-stock-adjustments.show', $storeStockAdjustment)
                ->with('success', 'Stock adjustment updated.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['general' => $e->getMessage()]);
        }
    }


    /**
     * items.brands may be casted array OR legacy JSON string in DB.
     */
    private function normalizeBrands($brands): array
    {
        if (is_array($brands)) {
            return array_values(array_filter(array_map('trim', $brands)));
        }

        if (is_string($brands) && trim($brands) !== '') {
            $decoded = json_decode($brands, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('trim', $decoded)));
            }

            // Fallback: comma-separated string
            return array_values(array_filter(array_map('trim', explode(',', $brands))));
        }

        return [];
    }

}
