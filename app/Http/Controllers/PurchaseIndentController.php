<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Item;
use App\Models\Project;
use App\Models\PurchaseIndent;
use App\Models\PurchaseIndentItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRfq;
use App\Models\PurchaseRfqItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseIndentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:purchase.indent.view')->only(['index', 'show']);
        $this->middleware('permission:purchase.indent.create')->only(['create', 'store']);
        $this->middleware('permission:purchase.indent.update')->only(['edit', 'update']);
        $this->middleware('permission:purchase.indent.approve')->only(['approve', 'reject']);
        $this->middleware('permission:purchase.indent.delete')->only(['cancel']);
    }

    // public function index(Request $request): View
    // {
    //     $query = PurchaseIndent::query()
    //         ->with(['project', 'department'])
    //         ->orderByDesc('id');

    //     if ($status = trim((string) $request->input('status', ''))) {
    //         $query->where('status', $status);
    //     }

    //     if ($proc = trim((string) $request->input('procurement_status', ''))) {
    //         $query->where('procurement_status', $proc);
    //     }

    //     if ($p = (int) $request->input('project_id')) {
    //         $query->where('project_id', $p);
    //     }

    //     if ($q = trim((string) $request->input('q', ''))) {
    //         $query->where('code', 'like', '%' . $q . '%');
    //     }

    //     $indents = $query->paginate(25)->withQueryString();
    //     $projects = Project::query()
    //         ->orderBy('code')
    //         ->orderBy('name')
    //         ->get(['id', 'code', 'name']);

    //     $statusOptions = [
    //         'draft' => 'Draft',
    //         'approved' => 'Approved',
    //         'rejected' => 'Rejected',
    //         'cancelled' => 'Cancelled',
    //     ];

    //     $procurementOptions = [
    //         'open' => 'Open',
    //         'rfq_created' => 'RFQ Created',
    //         'partially_ordered' => 'Partially Ordered',
    //         'ordered' => 'Ordered',
    //         'closed' => 'Closed',
    //         'cancelled' => 'Cancelled',
    //     ];

    //     return view('purchase_indents.index', compact('indents', 'projects', 'statusOptions', 'procurementOptions'));
    // }


        public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $query = PurchaseIndent::query()
            ->with(['project', 'department'])
            ->orderByDesc('id');

        // Filters
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($proc = $request->input('procurement_status')) {
            $query->where('procurement_status', $proc);
        }

        if ($project = $request->input('project_id')) {
            $query->where('project_id', $project);
        }

        if ($q = $request->input('q')) {
            $query->where('code', 'like', "%{$q}%");
        }

        $indents = $query->paginate(25)->withQueryString();

        // AJAX response
        if ($request->ajax()) {

            return response()->json([
                'table' => view('purchase_indents.partials.table', compact('indents'))->render(),
                'summary' => view('purchase_indents.partials.summary', compact('indents'))->render(),
            ]);
        }

        $projects = Project::orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $statusOptions = [
            'draft' => 'Draft',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ];

        $procurementOptions = [
            'open' => 'Open',
            'rfq_created' => 'RFQ Created',
            'partially_ordered' => 'Partially Ordered',
            'ordered' => 'Ordered',
            'closed' => 'Closed',
            'cancelled' => 'Cancelled',
        ];

        return view('purchase_indents.index', compact(
            'indents',
            'projects',
            'statusOptions',
            'procurementOptions'
        ));
    }

    public function create(): View
    {
        $projects = Project::orderBy('code')->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        $items = Item::query()
            ->with(['uom', 'type', 'category'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $itemMetaJson = $this->buildItemMetaJson($items);

        $indent = new PurchaseIndent();

        return view('purchase_indents.create', compact('projects', 'departments', 'itemMetaJson', 'indent'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id'       => ['nullable', 'integer', 'exists:projects,id'],
            'department_id'    => ['required', 'integer', 'exists:departments,id'],
            'required_by_date' => ['required', 'date'],
            'remarks'          => ['nullable', 'string', 'max:2000'],

            'items'                 => ['required', 'array', 'min:1'],
            'items.*.item_id'       => ['required', 'integer', 'exists:items,id'],
            'items.*.brand'         => ['nullable', 'string', 'max:100'],
            'items.*.grade'         => ['nullable', 'string', 'max:100'],
            'items.*.thickness_mm'  => ['nullable', 'numeric', 'min:0'],
            'items.*.length_mm'     => ['nullable', 'numeric', 'min:0'],
            'items.*.width_mm'      => ['nullable', 'numeric', 'min:0'],
            'items.*.qty_pcs'       => ['nullable', 'numeric', 'min:0'],
            'items.*.order_qty'     => ['nullable', 'numeric', 'min:0.001'],
            'items.*.uom_id'        => ['nullable', 'integer'],
            'items.*.description'   => ['nullable', 'string', 'max:500'],
            'items.*.remarks'       => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::beginTransaction();

            $indent = new PurchaseIndent();
            $indent->code             = $this->generateCode();
            $indent->project_id       = $validated['project_id'] ?? null;
            $indent->department_id    = $validated['department_id'];
            $indent->created_by       = auth()->id();
            $indent->approved_by      = null;
            $indent->required_by_date = $validated['required_by_date'];
            $indent->status           = 'draft';
            $indent->remarks          = $validated['remarks'] ?? null;
            $indent->save();

            foreach ($validated['items'] as $index => $itemData) {
                $item = Item::findOrFail($itemData['item_id']);

                [$weightPerPiece, $suggestedTotal] = $this->computeWeightsFromMeta(
                    item: $item,
                    thicknessMm: $itemData['thickness_mm'] ?? null,
                    widthMm: $itemData['width_mm'] ?? null,
                    lengthMm: $itemData['length_mm'] ?? null,
                    qtyPcs: $itemData['qty_pcs'] ?? null
                );

                $finalOrderQty = $itemData['order_qty'] ?? null;
                if ($finalOrderQty === null && $suggestedTotal !== null && $suggestedTotal > 0) {
                    $finalOrderQty = $suggestedTotal;
                }

                if ($finalOrderQty === null || (float) $finalOrderQty <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ['Order Qty is required (or provide dimensions + qty for auto weight). Item: ' . ($item->code ?? $item->name)],
                    ]);
                }

                $piItem = new PurchaseIndentItem();
                $piItem->purchase_indent_id  = $indent->id;
                $piItem->line_no             = $index + 1;
                $piItem->origin_type         = 'DIRECT';
                $piItem->origin_id           = null;
                $piItem->item_id             = $item->id;

                $piItem->brand               = $itemData['brand'] ?? null;
                $piItem->length_mm           = $itemData['length_mm'] ?? null;
                $piItem->width_mm            = $itemData['width_mm'] ?? null;
                $piItem->thickness_mm        = $itemData['thickness_mm'] ?? null;

                $piItem->density_kg_per_m3   = $item->density ?? null;
                $piItem->weight_per_meter_kg = $item->weight_per_meter ?? null;
                $piItem->weight_per_piece_kg = $weightPerPiece;

                $piItem->qty_pcs             = $itemData['qty_pcs'] ?? null;
                $piItem->order_qty           = $finalOrderQty;

                $piItem->uom_id              = !empty($itemData['uom_id']) ? (int) $itemData['uom_id'] : (int) $item->uom_id;
                $piItem->grade               = $itemData['grade'] ?? ($item->grade ?? null);
                $piItem->description         = $itemData['description'] ?? $item->name;
                $piItem->remarks             = $itemData['remarks'] ?? null;
                $piItem->save();
            }

            DB::commit();

            return redirect()->route('purchase-indents.show', $indent)
                ->with('success', 'Purchase Indent created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Indent create failed', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Failed to create indent: ' . $e->getMessage());
        }
    }

    public function show(PurchaseIndent $indent): View
    {
        $indent->load(['project', 'department', 'items.item.uom', 'items.item.type', 'items.item.category']);
        return view('purchase_indents.show', compact('indent'));
    }

    public function edit(PurchaseIndent $indent): View|RedirectResponse
    {
        if (in_array($indent->status, ['approved', 'rejected'], true)) {
            return redirect()->route('purchase-indents.show', $indent)
                ->with('error', 'Approved/Rejected indents cannot be edited.');
        }

        $projects = Project::orderBy('code')->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        $items = Item::query()
            ->with(['uom', 'type', 'category'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // ✅ FIX: build meta via safe normalize (string/array)
        $itemMetaJson = $this->buildItemMetaJson($items);

        $indent->load(['items']);

        return view('purchase_indents.edit', compact('indent', 'projects', 'departments', 'itemMetaJson'));
    }

    public function update(Request $request, PurchaseIndent $indent): RedirectResponse
    {
        if (in_array($indent->status, ['approved', 'rejected'], true)) {
            return redirect()->route('purchase-indents.show', $indent)
                ->with('error', 'Approved/Rejected indents cannot be edited.');
        }

        $validated = $request->validate([
            'project_id'       => ['nullable', 'integer', 'exists:projects,id'],
            'department_id'    => ['required', 'integer', 'exists:departments,id'],
            'required_by_date' => ['required', 'date'],
            'remarks'          => ['nullable', 'string', 'max:2000'],

            'items'                 => ['required', 'array', 'min:1'],
            'items.*.id'            => ['nullable', 'integer', 'exists:purchase_indent_items,id'],
            'items.*.item_id'       => ['required', 'integer', 'exists:items,id'],
            'items.*.brand'         => ['nullable', 'string', 'max:100'],
            'items.*.grade'         => ['nullable', 'string', 'max:100'],
            'items.*.thickness_mm'  => ['nullable', 'numeric', 'min:0'],
            'items.*.length_mm'     => ['nullable', 'numeric', 'min:0'],
            'items.*.width_mm'      => ['nullable', 'numeric', 'min:0'],
            'items.*.qty_pcs'       => ['nullable', 'numeric', 'min:0'],
            'items.*.order_qty'     => ['nullable', 'numeric', 'min:0.001'],
            'items.*.uom_id'        => ['nullable', 'integer'],
            'items.*.description'   => ['nullable', 'string', 'max:500'],
            'items.*.remarks'       => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::beginTransaction();

            $indent->project_id       = $validated['project_id'] ?? null;
            $indent->department_id    = $validated['department_id'];
            $indent->required_by_date = $validated['required_by_date'];
            $indent->remarks          = $validated['remarks'] ?? null;
            $indent->save();

            $existing = $indent->items()->get()->keyBy('id');
            $keptIds = [];

            foreach ($validated['items'] as $index => $itemData) {
                $item = Item::findOrFail($itemData['item_id']);

                [$weightPerPiece, $suggestedTotal] = $this->computeWeightsFromMeta(
                    item: $item,
                    thicknessMm: $itemData['thickness_mm'] ?? null,
                    widthMm: $itemData['width_mm'] ?? null,
                    lengthMm: $itemData['length_mm'] ?? null,
                    qtyPcs: $itemData['qty_pcs'] ?? null
                );

                $finalOrderQty = $itemData['order_qty'] ?? null;
                if ($finalOrderQty === null && $suggestedTotal !== null && $suggestedTotal > 0) {
                    $finalOrderQty = $suggestedTotal;
                }
                if ($finalOrderQty === null || (float) $finalOrderQty <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ['Order Qty is required (or provide dimensions + qty for auto weight). Item: ' . ($item->code ?? $item->name)],
                    ]);
                }

                $piItem = null;

                if (!empty($itemData['id'])) {
                    $piItem = $existing->get((int) $itemData['id']);
                    if (!$piItem) {
                        throw ValidationException::withMessages([
                            'items' => ['Invalid indent line id: ' . (int) $itemData['id']],
                        ]);
                    }
                } else {
                    $piItem = new PurchaseIndentItem();
                    $piItem->purchase_indent_id = $indent->id;
                    $piItem->origin_type = 'DIRECT';
                }

                $piItem->line_no             = $index + 1;
                $piItem->item_id             = $item->id;

                $piItem->brand               = $itemData['brand'] ?? null;
                $piItem->length_mm           = $itemData['length_mm'] ?? null;
                $piItem->width_mm            = $itemData['width_mm'] ?? null;
                $piItem->thickness_mm        = $itemData['thickness_mm'] ?? null;

                $piItem->density_kg_per_m3   = $item->density ?? null;
                $piItem->weight_per_meter_kg = $item->weight_per_meter ?? null;
                $piItem->weight_per_piece_kg = $weightPerPiece;

                $piItem->qty_pcs             = $itemData['qty_pcs'] ?? null;
                $piItem->order_qty           = $finalOrderQty;

                $piItem->uom_id              = !empty($itemData['uom_id']) ? (int) $itemData['uom_id'] : (int) $item->uom_id;
                $piItem->grade               = $itemData['grade'] ?? ($item->grade ?? null);
                $piItem->description         = $itemData['description'] ?? $item->name;
                $piItem->remarks             = $itemData['remarks'] ?? null;

                $piItem->save();
                $keptIds[] = $piItem->id;
            }

            // Soft-delete removed lines only if not referenced
            $toRemove = $existing->keys()->diff($keptIds);
            foreach ($toRemove as $removeId) {
                $usedInRfq = PurchaseRfqItem::where('purchase_indent_item_id', $removeId)->exists();
                $usedInPo  = PurchaseOrderItem::where('purchase_indent_item_id', $removeId)->exists();

                if ($usedInRfq || $usedInPo) {
                    throw ValidationException::withMessages([
                        'items' => "Cannot remove indent line #{$removeId} because it is already used in RFQ/PO.",
                    ]);
                }

                $existing->get($removeId)?->delete();
            }

            DB::commit();

            return redirect()->route('purchase-indents.show', $indent)
                ->with('success', 'Purchase Indent updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Indent update failed', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Failed to update indent: ' . $e->getMessage());
        }
    }

    public function approve(PurchaseIndent $indent): RedirectResponse
    {
        if ($indent->status === 'approved') {
            return redirect()->route('purchase-indents.show', $indent)
                ->with('warning', 'Indent is already approved.');
        }

        if ($indent->items()->count() === 0) {
            return redirect()->route('purchase-indents.show', $indent)
                ->with('error', 'Cannot approve indent with no items.');
        }

        $indent->status = 'approved';
        $indent->approved_by = auth()->id();
        $indent->save();

        return redirect()->route('purchase-indents.show', $indent)
            ->with('success', 'Purchase Indent approved successfully.');
    }

    public function reject(PurchaseIndent $indent): RedirectResponse
    {
        if ($indent->status === 'rejected') {
            return redirect()->route('purchase-indents.show', $indent)
                ->with('warning', 'Indent is already rejected.');
        }

        $indent->status = 'rejected';
        $indent->approved_by = auth()->id();
        $indent->save();

        return redirect()->route('purchase-indents.show', $indent)
            ->with('success', 'Purchase Indent rejected.');
    }

    public function cancel(Request $request, PurchaseIndent $indent): RedirectResponse
    {
        if (in_array($indent->status, ['cancelled', 'closed'], true)) {
            return redirect()->route('purchase-indents.show', $indent)
                ->with('error', 'Indent is already cancelled/closed.');
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $activePos = PurchaseOrder::where('purchase_indent_id', $indent->id)
            ->whereNotIn('status', ['cancelled'])
            ->pluck('code');

        if ($activePos->isNotEmpty()) {
            return redirect()->route('purchase-indents.show', $indent)
                ->with('error', 'Cancel linked Purchase Order(s) first: ' . $activePos->implode(', '));
        }

        $activeRfqs = PurchaseRfq::where('purchase_indent_id', $indent->id)
            ->whereNotIn('status', ['cancelled'])
            ->pluck('code');

        if ($activeRfqs->isNotEmpty()) {
            return redirect()->route('purchase-indents.show', $indent)
                ->with('error', 'Cancel linked RFQ(s) first: ' . $activeRfqs->implode(', '));
        }

        $indent->status = 'cancelled';
        $indent->cancelled_at = now();
        $indent->cancelled_by = auth()->id();
        $indent->cancel_reason = $request->input('reason');
        $indent->save();

        return redirect()->route('purchase-indents.show', $indent)
            ->with('success', 'Purchase Indent cancelled.');
    }

    // ----------------------------
    // Helpers
    // ----------------------------

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
        }

        return [];
    }

    private function buildItemMetaJson($items): string
    {
        $meta = $items->map(function (Item $i) {
            return [
                'id'              => $i->id,
                'name'            => ($i->code ? ($i->code . ' - ') : '') . $i->name,
                'uom_id'           => $i->uom_id,
                'uom_code'         => $i->uom?->code,
                'material_type'    => $i->type?->code,
                'density'          => $i->density,
                'weight_per_meter' => $i->weight_per_meter,
                'grade'            => $i->grade,
                'brands'           => $this->normalizeBrands($i->brands),
            ];
        })->values();

        return $meta->toJson();
    }

    private function generateCode(): string
    {
        $year = date('y');
        $prefix = "IND-{$year}-";
        $lastIndent = PurchaseIndent::where('code', 'LIKE', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();

        $newNumber = $lastIndent ? ((int) substr((string) $lastIndent->code, -4) + 1) : 1;

        return $prefix . str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
    }

    private function computeWeightsFromMeta(
        Item $item,
        ?float $thicknessMm,
        ?float $widthMm,
        ?float $lengthMm,
        $qtyPcs
    ): array {
        $qty = $qtyPcs === null ? null : (float) $qtyPcs;

        $wpm = $item->weight_per_meter !== null ? (float) $item->weight_per_meter : null;
        if ($wpm !== null && $wpm > 0 && $lengthMm !== null && (float) $lengthMm > 0) {
            $lengthM = ((float) $lengthMm) / 1000.0;
            $perPiece = $wpm * $lengthM;
            $total = ($qty !== null && $qty > 0) ? ($perPiece * $qty) : $perPiece;
            return [round($perPiece, 4), round($total, 3)];
        }

        $density = $item->density !== null ? (float) $item->density : null;
        if ($density !== null && $density > 0 && $thicknessMm && $widthMm && $lengthMm) {
            $tM = ((float) $thicknessMm) / 1000.0;
            $wM = ((float) $widthMm) / 1000.0;
            $lM = ((float) $lengthMm) / 1000.0;
            $volumeM3 = $tM * $wM * $lM;

            if ($volumeM3 > 0) {
                $perPiece = $volumeM3 * $density;
                $total = ($qty !== null && $qty > 0) ? ($perPiece * $qty) : $perPiece;
                return [round($perPiece, 4), round($total, 3)];
            }
        }

        return [null, null];
    }
}
