<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Item;
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptLine;
use App\Models\MaterialVendorReturn;
use App\Models\MaterialVendorReturnLine;
use App\Models\Party;
use App\Models\Project;
use App\Models\StoreStockItem;
use App\Models\Uom;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Services\Accounting\VoucherNumberService;
use App\Services\DocumentNumberService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaterialReceiptController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:store.material_receipt.view')
            ->only(['index', 'show', 'downloadAttachment']);

        $this->middleware('permission:store.material_receipt.create')
            ->only(['create', 'store']);

        $this->middleware('permission:store.material_receipt.update')
            ->only(['uploadHeaderAttachment', 'uploadLineAttachment', 'updateStatus']);

        $this->middleware('permission:store.material_receipt.delete')
            ->only(['deleteAttachment', 'destroy', 'createReturn', 'storeReturn']);
    }

    public function index(): View
    {
        $receipts = MaterialReceipt::with(['supplier', 'client', 'project'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('material_receipts.index', compact('receipts'));
    }

    public function create(): View
    {
        // Masters
        $suppliers = Party::where('is_supplier', true)->orderBy('name')->get();
        $clients   = Party::where('is_client', true)->orderBy('name')->get();

        $projects  = Project::orderBy('code')->get();
        $items     = Item::orderBy('name')->get();
        $uoms      = Uom::orderBy('name')->get();

        // Fixed categories
        $materialCategories = [
            'steel_plate'   => 'Steel Plate',
            'steel_section' => 'Steel Section',
            'consumable'    => 'Consumable',
            'bought_out'    => 'Bought-out Item',
        ];

        // Default GRN category per item (auto-select in UI using material taxonomy)
        $itemDefaultCategoryMap = [];

        try {
            $typeCodeById = DB::table('material_types')->pluck('code', 'id')->toArray();
            $categoryCodeById = DB::table('material_categories')->pluck('code', 'id')->toArray();

            foreach ($items as $it) {
                $typeCode = $typeCodeById[$it->material_type_id] ?? null;
                $catCode  = $categoryCodeById[$it->material_category_id] ?? null;

                $itemDefaultCategoryMap[$it->id] = $this->inferGrnCategoryFromTaxonomy($typeCode, $catCode);
            }
        } catch (\Throwable $e) {
            // If taxonomy tables are not available for some reason, keep map empty and let user pick manually.
            $itemDefaultCategoryMap = [];
        }


        // Project => client map
        $projectClientMap = $projects->mapWithKeys(function (Project $project) {
            return [$project->id => $project->client_party_id];
        });

        /**
         * 1) Get a pool of APPROVED POs with items.
         */
        $basePurchaseOrders = PurchaseOrder::with(['vendor', 'project', 'items'])
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->limit(200) // base pool before filtering
            ->get();

        // Collect all PO item IDs from this pool
        $poItemIds = $basePurchaseOrders
            ->flatMap(function (PurchaseOrder $po) {
                return $po->items;
            })
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        // Map: purchase_order_item_id => sums of already received (QC-PASSED only)
        $receivedAgg = collect();

        if (! empty($poItemIds)) {
            $receivedAgg = MaterialReceiptLine::query()
                ->join('material_receipts', 'material_receipt_lines.material_receipt_id', '=', 'material_receipts.id')
                ->where('material_receipts.status', 'qc_passed')
                ->whereIn('material_receipt_lines.purchase_order_item_id', $poItemIds)
                ->selectRaw(
                    'material_receipt_lines.purchase_order_item_id as purchase_order_item_id, ' .
                    'SUM(COALESCE(material_receipt_lines.qty_pcs, 0))            AS total_qty_pcs, ' .
                    'SUM(COALESCE(material_receipt_lines.received_weight_kg, 0)) AS total_weight_kg'
                )
                ->groupBy('material_receipt_lines.purchase_order_item_id')
                ->get()
                ->keyBy('purchase_order_item_id');
        }

        /**
         * 2) Filter POs: keep only those where at least ONE line
         *    still has pending quantity within allowed + tolerance.
         *
         *    Logic matches the guard in store():
         *    - For steel_plate / steel_section → compare pcs (qty_pcs)
         *    - For others → compare weight/quantity
         *    - Respect grn_tolerance_percent on PO item if present
         */
        $purchaseOrders = $basePurchaseOrders
            ->filter(function (PurchaseOrder $po) use ($receivedAgg) {
                foreach ($po->items as $item) {
                    $row = $receivedAgg->get($item->id);

                    $tolerancePct = (float) ($item->grn_tolerance_percent ?? 0);
                    if ($tolerancePct < 0) {
                        $tolerancePct = 0;
                    }

                    /**
                     * IMPORTANT:
                     * purchase_order_items table does not store material_category.
                     * So, for PO pending checks we infer "piece-controlled" lines
                     * when qty_pcs is set (> 0).
                     */
                    $poQtyPcs = (float) ($item->qty_pcs ?? 0);

                    if ($poQtyPcs > 0) {
                        // Piece-controlled (plates/sections etc.)
                        $poQty       = $poQtyPcs;
                        $alreadyRecv = $row ? (float) ($row->total_qty_pcs ?? 0) : 0.0;
                    } else {
                        // Quantity-controlled (consumables/bought-out etc.)
                        $poQty       = (float) ($item->quantity ?? 0);
                        $alreadyRecv = $row ? (float) ($row->total_weight_kg ?? 0) : 0.0;
                    }

                    // If PO line has no meaningful quantity, keep PO visible
                    if ($poQty <= 0) {
                        return true;
                    }

                    $maxAllowed = $poQty * (1 + ($tolerancePct / 100));
                    $pending    = $maxAllowed - $alreadyRecv;

                    if ($pending > 0.0001) {
                        // At least one line still has room to receive
                        return true;
                    }
                }

                // All lines fully received (within tolerance) → hide this PO
                return false;
            })
            ->values()
            ->take(100); // final dropdown size

        return view('material_receipts.create', compact(
            'suppliers',
            'clients',
            'projects',
            'items',
            'uoms',
            'materialCategories',
            'itemDefaultCategoryMap',
            'projectClientMap',
            'purchaseOrders'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'receipt_date'        => ['required', 'date'],
            'is_client_material'  => ['nullable', 'boolean'],
            'purchase_order_id'   => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'po_number'           => ['nullable', 'string', 'max:50'],
            'supplier_id'         => ['nullable', 'integer', 'exists:parties,id'],
            'project_id'          => ['nullable', 'integer', 'exists:projects,id'],
            'client_party_id'     => ['nullable', 'integer', 'exists:parties,id'],
            'invoice_number'      => ['nullable', 'string', 'max:100'],
            'invoice_date'        => ['required', 'date'],
            'challan_number'      => ['nullable', 'string', 'max:100'],
            'vehicle_number'      => ['nullable', 'string', 'max:100'],
            'remarks'             => ['nullable', 'string'],

            'lines'                         => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_item_id'=> ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'lines.*.item_id'               => ['required', 'integer', 'exists:items,id'],
            'lines.*.brand'                => ['nullable', 'string', 'max:100'],
            'lines.*.material_category'     => ['required', 'string', 'max:50'],
            'lines.*.thickness_mm'          => ['nullable', 'numeric', 'min:0'],
            'lines.*.width_mm'              => ['nullable', 'numeric', 'min:0'],
            'lines.*.length_mm'             => ['nullable', 'numeric', 'min:0'],
            'lines.*.section_profile'       => ['nullable', 'string', 'max:100'],
            'lines.*.grade'                 => ['nullable', 'string', 'max:100'],
            'lines.*.qty_pcs'               => ['required', 'integer', 'min:1'],
            'lines.*.received_weight_kg'    => ['nullable', 'numeric', 'min:0'],
            'lines.*.uom_id'                => ['required', 'integer', 'exists:uoms,id'],
            'lines.*.remarks'               => ['nullable', 'string'],
        ]);

        $isClientMaterial = (bool) ($data['is_client_material'] ?? false);

        $project = null;
        if (! empty($data['project_id'])) {
            $project = Project::find($data['project_id']);
        }

        // Auto-fill client from project if not explicitly set
        if ($project && $project->client_party_id && empty($data['client_party_id'])) {
            $data['client_party_id'] = $project->client_party_id;
            $request->merge(['client_party_id' => $project->client_party_id]);
        }

        // Purchase Order linkage (only for own material)
        $purchaseOrder = null;
        if (! empty($data['purchase_order_id'])) {
            $purchaseOrder = PurchaseOrder::with(['project', 'vendor'])
                ->find($data['purchase_order_id']);

            if (! $purchaseOrder) {
                return back()
                    ->withInput()
                    ->withErrors(['purchase_order_id' => 'Selected purchase order not found.']);
            }

            if ($isClientMaterial) {
                return back()
                    ->withInput()
                    ->withErrors(['purchase_order_id' => 'Purchase order can only be linked for own material receipts.']);
            }

            // Force project & supplier from PO (to keep chain clean)
            if ($purchaseOrder->project_id) {
                $data['project_id'] = $purchaseOrder->project_id;
                $request->merge(['project_id' => $purchaseOrder->project_id]);
                $project = $purchaseOrder->project; // keep $project in sync
            }

            if ($purchaseOrder->vendor_party_id) {
                $data['supplier_id'] = $purchaseOrder->vendor_party_id;
                $request->merge(['supplier_id' => $purchaseOrder->vendor_party_id]);
            }

            // Always override po_number with PO code
            $data['po_number'] = $purchaseOrder->code;
            $request->merge(['po_number' => $purchaseOrder->code]);
        }

        // Guard: do not allow GRN to receive more than the Purchase Order quantity per line
        // including optional tolerance % defined on the PO item (grn_tolerance_percent).
        if (! empty($data['purchase_order_id']) && isset($purchaseOrder)) {
            // Map PO items by id
            $poItems = $purchaseOrder->items()->get()->keyBy('id');

            // Collect PO item IDs present in this GRN
            $poItemIds = collect($data['lines'])
                ->pluck('purchase_order_item_id')
                ->filter()
                ->unique()
                ->all();

            if (! empty($poItemIds)) {
                $receivedAgg = MaterialReceiptLine::query()
                    ->whereIn('purchase_order_item_id', $poItemIds)
                    ->select(
                        'purchase_order_item_id',
                        DB::raw('SUM(qty_pcs) as total_qty_pcs'),
                        DB::raw('SUM(received_weight_kg) as total_weight_kg')
                    )
                    ->groupBy('purchase_order_item_id')
                    ->get()
                    ->keyBy('purchase_order_item_id');

                foreach ($data['lines'] as $index => $lineData) {
                    $poItemId = $lineData['purchase_order_item_id'] ?? null;
                    if (! $poItemId) {
                        continue;
                    }

                    if (! isset($poItems[$poItemId])) {
                        return back()
                            ->withInput()
                            ->withErrors([
                                'lines.' . $index . '.purchase_order_item_id' => 'Selected purchase order line not found.',
                            ]);
                    }

                    $poItem = $poItems[$poItemId];
                    $row    = $receivedAgg->get($poItemId);

                    $category = $lineData['material_category'] ?? $poItem->material_category ?? null;

                    if (in_array($category, ['steel_plate', 'steel_section'], true)) {
                        // Raw material: compare by pieces
                        $poQty       = (float) ($poItem->qty_pcs ?? 0);
                        $alreadyRecv = $row ? (float) ($row->total_qty_pcs ?? 0) : 0.0;
                        $thisRecv    = (float) ($lineData['qty_pcs'] ?? 0);
                    } else {
                        // Non-raw (consumables, bought-out, etc.): compare by GRN quantity field
                        $poQty       = (float) ($poItem->quantity ?? 0);
                        $alreadyRecv = $row ? (float) ($row->total_weight_kg ?? 0) : 0.0;
                        $thisRecv    = (float) ($lineData['received_weight_kg'] ?? 0);
                    }

                    if ($poQty <= 0) {
                        // If PO quantity is zero or not set, skip the guard (no basis)
                        continue;
                    }

                    // Optional tolerance from PO item (percentage, e.g. 2 = 2%)
                    $tolerancePct = (float) ($poItem->grn_tolerance_percent ?? 0);
                    if ($tolerancePct < 0) {
                        $tolerancePct = 0;
                    }

                    $maxAllowed = $poQty * (1 + ($tolerancePct / 100));
                    $totalAfter = $alreadyRecv + $thisRecv;

                    if ($totalAfter > $maxAllowed + 0.0001) {
                        $lineKey = 'lines.' . $index . '.received_weight_kg';

                        // For raw material we attach error to qty_pcs
                        if (in_array($category, ['steel_plate', 'steel_section'], true)) {
                            $lineKey = 'lines.' . $index . '.qty_pcs';
                        }

                        $msg = 'GRN quantity exceeds allowed limit for PO line #' . ($poItem->line_no ?? $poItemId) . '.';

                        if ($tolerancePct > 0) {
                            $msg .= ' (Max ' . round($poQty, 3)
                                . ' + ' . $tolerancePct . '% tolerance = '
                                . round($maxAllowed, 3) . ')';
                        }

                        return back()
                            ->withInput()
                            ->withErrors([
                                $lineKey => $msg,
                            ]);
                    }
                }
            }
        }

        // Either invoice number or challan number is required
        if (empty($data['invoice_number']) && empty($data['challan_number'])) {
            return back()
                ->withInput()
                ->withErrors(['invoice_number' => 'Invoice number or challan number is required.']);
        }

        // Client vs Own material guards
        if ($isClientMaterial && empty($data['client_party_id'])) {
            return back()
                ->withInput()
                ->withErrors(['client_party_id' => 'Client is required for client material.']);
        }

        if (! $isClientMaterial && empty($data['supplier_id'])) {
            return back()
                ->withInput()
                ->withErrors(['supplier_id' => 'Supplier is required for own material.']);
        }

        DB::beginTransaction();

        try {
            $receipt = new MaterialReceipt();
            $receipt->receipt_date       = $data['receipt_date'];
            $receipt->is_client_material = $isClientMaterial;
            $receipt->purchase_order_id  = $data['purchase_order_id'] ?? null;
            $receipt->po_number          = $data['po_number'] ?? null;
            $receipt->supplier_id        = $data['supplier_id'] ?? null;
            $receipt->project_id         = $data['project_id'] ?? null;
            $receipt->client_party_id    = $data['client_party_id'] ?? null;
            $receipt->invoice_number     = $data['invoice_number'] ?? null;
            $receipt->invoice_date       = $data['invoice_date'] ?? null;
            $receipt->challan_number     = $data['challan_number'] ?? null;
            $receipt->vehicle_number     = $data['vehicle_number'] ?? null;
            $receipt->status             = 'draft';
            $receipt->created_by         = $request->user()?->id;
            $receipt->remarks            = $data['remarks'] ?? null;
            $receipt->save();

            // Generate receipt number via central service (same pattern GRN-YY-XXXX)
    		$receipt->receipt_number = app(\App\Services\DocumentNumberService::class)
    	    ->materialReceipt($receipt);
   			 $receipt->save();
            // Resolve brand fallback from PO/Indent/RFQ for lines where brand is not provided in request.
            // This keeps brand consistent across Indent → RFQ → PO → GRN → Store stock even if UI does not post brand.
            $brandByPoItemId = [];
            $poItemIdsForBrand = collect($data['lines'])
                ->pluck('purchase_order_item_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! empty($poItemIdsForBrand)) {
                $poRows = DB::table('purchase_order_items')
                    ->whereIn('id', $poItemIdsForBrand)
                    ->select('id', 'brand', 'purchase_indent_item_id', 'purchase_rfq_item_id')
                    ->get();

                $indentIds = $poRows->pluck('purchase_indent_item_id')->filter()->unique()->values()->all();
                $rfqIds    = $poRows->pluck('purchase_rfq_item_id')->filter()->unique()->values()->all();

                $indentBrandMap = [];
                if (! empty($indentIds)) {
                    $indentBrandMap = DB::table('purchase_indent_items')
                        ->whereIn('id', $indentIds)
                        ->pluck('brand', 'id')
                        ->toArray();
                }

                $rfqBrandMap = [];
                if (! empty($rfqIds)) {
                    $rfqBrandMap = DB::table('purchase_rfq_items')
                        ->whereIn('id', $rfqIds)
                        ->pluck('brand', 'id')
                        ->toArray();
                }

                foreach ($poRows as $r) {
                    $b = $r->brand ?? null;

                    if ((! $b || trim((string) $b) === '') && ! empty($r->purchase_indent_item_id)) {
                        $b = $indentBrandMap[$r->purchase_indent_item_id] ?? null;
                    }

                    if ((! $b || trim((string) $b) === '') && ! empty($r->purchase_rfq_item_id)) {
                        $b = $rfqBrandMap[$r->purchase_rfq_item_id] ?? null;
                    }

                    if (! is_null($b)) {
                        $b = trim((string) $b);
                        if ($b === '') {
                            $b = null;
                        } elseif (strlen($b) > 100) {
                            $b = substr($b, 0, 100);
                        }
                    }

                    $brandByPoItemId[(int) $r->id] = $b;
                }
            }




            foreach ($data['lines'] as $index => $lineData) {
                $poItemId = $lineData['purchase_order_item_id'] ?? null;

                $brandResolved = $lineData['brand'] ?? null;
                if (! is_null($brandResolved)) {
                    $brandResolved = trim((string) $brandResolved);
                    if ($brandResolved === '') {
                        $brandResolved = null;
                    } elseif (strlen($brandResolved) > 100) {
                        $brandResolved = substr($brandResolved, 0, 100);
                    }
                }

                if (($brandResolved === null || $brandResolved === '') && $poItemId) {
                    $brandResolved = $brandByPoItemId[(int) $poItemId] ?? null;
                }

                $line = $receipt->lines()->create([
                    'item_id'               => $lineData['item_id'],
                    'brand'                => $brandResolved,
                    'material_category'     => $lineData['material_category'],
                    'thickness_mm'          => $lineData['thickness_mm'] ?? null,
                    'width_mm'              => $lineData['width_mm'] ?? null,
                    'length_mm'             => $lineData['length_mm'] ?? null,
                    'section_profile'       => $lineData['section_profile'] ?? null,
                    'grade'                 => $lineData['grade'] ?? null,
                    'qty_pcs'               => $lineData['qty_pcs'],
                    'received_weight_kg'    => $lineData['received_weight_kg'] ?? null,
                    'uom_id'                => $lineData['uom_id'],
                    'status'                => 'pending',
                    'remarks'               => $lineData['remarks'] ?? null,
                    'purchase_order_item_id'=> $lineData['purchase_order_item_id'] ?? null,
                ]);

                $qtyPcs = max(1, (int) $lineData['qty_pcs']);

                $totalWeight = $lineData['received_weight_kg'] ?? null;
                if ($totalWeight === '') {
                    $totalWeight = null;
                }
                $totalWeight = ! is_null($totalWeight) ? (float) $totalWeight : null;

                // Steel Plate -> create one stock row per piece (for Plate No / Heat No traceability)
                // Steel Section -> create ONE combined stock row (avoid huge record counts; TC can be linked once)
                if ($line->material_category === 'steel_section') {
                    StoreStockItem::create([
                        'material_receipt_line_id' => $line->id,
                        'item_id'                  => $line->item_id,
                        'brand'                    => $line->brand,
                        'project_id'               => $receipt->project_id,
                        'client_party_id'          => $receipt->client_party_id,
                        'is_client_material'       => $isClientMaterial,
                        'material_category'        => $line->material_category,
                        'thickness_mm'             => $line->thickness_mm,
                        'width_mm'                 => $line->width_mm,
                        'length_mm'                => $line->length_mm,
                        'section_profile'          => $line->section_profile,
                        'grade'                    => $line->grade,
                        'qty_pcs_total'            => $qtyPcs,
                        'qty_pcs_available'        => 0, // QC hold until qc_passed
                        'weight_kg_total'          => $totalWeight,
                        'weight_kg_available'      => 0, // QC hold until qc_passed
                        'source_type'              => $isClientMaterial ? 'client_grn' : 'purchase_grn',
                        'source_reference'         => $receipt->receipt_number . '-L' . ($index + 1),
                        'status'                   => 'blocked_qc',
                    ]);
                } else {
                    $perPieceWeight = null;

                    if (! is_null($totalWeight) && $qtyPcs > 0) {
                        $perPieceWeight = (float) $totalWeight / $qtyPcs;
                    }

                    for ($i = 0; $i < $qtyPcs; $i++) {
                        StoreStockItem::create([
                            'material_receipt_line_id' => $line->id,
                            'item_id'                  => $line->item_id,
                            'brand'                    => $line->brand,
                            'project_id'               => $receipt->project_id,
                            'client_party_id'          => $receipt->client_party_id,
                            'is_client_material'       => $isClientMaterial,
                            'material_category'        => $line->material_category,
                            'thickness_mm'             => $line->thickness_mm,
                            'width_mm'                 => $line->width_mm,
                            'length_mm'                => $line->length_mm,
                            'section_profile'          => $line->section_profile,
                            'grade'                    => $line->grade,
                            'qty_pcs_total'            => 1,
                            'qty_pcs_available'        => 0, // QC hold until qc_passed
                            'weight_kg_total'          => $perPieceWeight,
                            'weight_kg_available'      => 0, // QC hold until qc_passed
                            'source_type'              => $isClientMaterial ? 'client_grn' : 'purchase_grn',
                            'source_reference'         => $receipt->receipt_number . '-L' . ($index + 1),
                            'status'                   => 'blocked_qc',
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()
                ->route('material-receipts.show', $receipt)
                ->with('success', 'GRN created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['general' => 'Failed to save GRN: ' . $e->getMessage()]);
        }
    }

    public function show(MaterialReceipt $materialReceipt): View
    {
        $materialReceipt->load([
            'supplier',
            'client',
            'project',
            'purchaseOrder',
            'lines.item.uom',
            'lines.attachments',
            'attachments',
            'vendorReturns.toParty',
            'vendorReturns.lines.item.uom',
            'vendorReturns.lines.stockItem',
            'vendorReturns.voucher',
        ]);

        return view('material_receipts.show', [
            'receipt' => $materialReceipt,
        ]);
    }

    public function uploadHeaderAttachment(Request $request, MaterialReceipt $materialReceipt): RedirectResponse
    {
        $data = $request->validate([
            'file'     => ['required', 'file', 'max:10240'], // 10 MB
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        $file = $request->file('file');
        $path = $file->store('attachments', 'public');

        $materialReceipt->attachments()->create([
            'category'      => $data['category'] ?? null,
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getClientMimeType(),
            'size'          => $file->getSize(),
            'uploaded_by'   => $request->user()?->id,
        ]);

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function uploadLineAttachment(Request $request, MaterialReceiptLine $materialReceiptLine): RedirectResponse
    {
        $data = $request->validate([
            'file'     => ['required', 'file', 'max:10240'],
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        $file = $request->file('file');
        $path = $file->store('attachments', 'public');

        $materialReceiptLine->attachments()->create([
            'category'      => $data['category'] ?? null,
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getClientMimeType(),
            'size'          => $file->getSize(),
            'uploaded_by'   => $request->user()?->id,
        ]);

        return back()->with('success', 'Line document uploaded successfully.');
    }

    public function downloadAttachment(Attachment $attachment)
    {
        $disk = 'public';

        if (! Storage::disk($disk)->exists($attachment->path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk($disk)->download($attachment->path, $attachment->original_name);
    }

    public function deleteAttachment(Attachment $attachment): RedirectResponse
    {
        $disk = 'public';

        if ($attachment->path && Storage::disk($disk)->exists($attachment->path)) {
            Storage::disk($disk)->delete($attachment->path);
        }

        $attachment->delete();

        return back()->with('success', 'Attachment deleted successfully.');
    }

	public function updateStatus(Request $request, MaterialReceipt $materialReceipt): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,qc_pending,qc_passed,qc_rejected'],
        ]);

        $newStatus = $validated['status'];
        $oldStatus = $materialReceipt->status;

        // No-op guard: if status is unchanged, allow a SAFE stock re-sync.
        // This is useful when fixing legacy data after code changes.
        if ($newStatus === $oldStatus) {
            DB::beginTransaction();

            try {
                $this->syncStockAvailabilityForReceipt($materialReceipt, $newStatus, $oldStatus);
                DB::commit();

                return back()->with('success', 'GRN stock has been re-synced for status ' . strtoupper($newStatus) . '.');
            } catch (\Throwable $e) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->withErrors(['status' => 'Failed to re-sync GRN stock: ' . $e->getMessage()]);
            }
        }

        DB::beginTransaction();

        try {
            $materialReceipt->status = $newStatus;

            // When QC is passed, record who did it and when
            if ($newStatus === 'qc_passed') {
                $materialReceipt->qc_by = $request->user()?->id;
                $materialReceipt->qc_at = now();
            }

            $materialReceipt->save();

            // Sync stock availability with GRN QC status
            $this->syncStockAvailabilityForReceipt($materialReceipt, $newStatus, $oldStatus);

            // If GRN is linked to a PO, recalc running totals on PO items
            if ($materialReceipt->purchase_order_id) {
                $this->recalculatePurchaseOrderReceivedTotals((int) $materialReceipt->purchase_order_id);
                // Also sync received totals back to Purchase Indent lines (auto-close lines)
                $this->recalculateIndentReceivedTotals((int) $materialReceipt->purchase_order_id, $request->user()?->id);
            }

            DB::commit();

            return back()->with('success', 'GRN status updated to ' . strtoupper($newStatus) . '.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['status' => 'Failed to update GRN status: ' . $e->getMessage()]);
        }
    }


    /**
     * Keep StoreStockItem availability in sync with GRN QC status.
     *
     * Current policy:
     * - Stock rows are created at GRN save time, but with available qty/weight = 0 (QC hold).
     * - When GRN becomes qc_passed -> available = totals.
     * - When GRN is not qc_passed -> available = 0.
     *
     * IMPORTANT: If someone tries to revert a qc_passed GRN back to draft/qc_pending/qc_rejected,
     * we block it if any stock from that GRN has already been issued/consumed.
     */
    protected function syncStockAvailabilityForReceipt(MaterialReceipt $receipt, string $newStatus, string $oldStatus): void
    {
        $lineIds = $receipt->lines()->pluck('id')->all();
        if (empty($lineIds)) {
            return;
        }

        // Lock stock rows to avoid race with store issue/return
        $stocks = StoreStockItem::query()
            ->whereIn('material_receipt_line_id', $lineIds)
            ->lockForUpdate()
            ->get();

        if ($stocks->isEmpty()) {
            return;
        }// Guard: do not allow reverting QC PASSED if any stock has already moved out (issue/consume/reserve/etc.)
if ($oldStatus === 'qc_passed' && $newStatus !== 'qc_passed') {
    $stockIds = $stocks->pluck('id')->all();

    // Extra safety: if any Store Issue lines exist against these stock rows, do not allow revert.
    $hasIssueLines = false;
    if (! empty($stockIds)) {
        $hasIssueLines = DB::table('store_issue_lines')
            ->whereIn('store_stock_item_id', $stockIds)
            ->exists();
    }

    foreach ($stocks as $s) {
        $pcsTotal  = (float) ($s->qty_pcs_total ?? 0);
        $pcsAvail  = (float) ($s->qty_pcs_available ?? 0);
        $wtTotal   = (float) ($s->weight_kg_total ?? 0);
        $wtAvail   = (float) ($s->weight_kg_available ?? 0);

        $pcsIssued = $pcsTotal > 0 ? ($pcsTotal - $pcsAvail) : 0.0;
        $wtIssued  = $wtTotal > 0 ? ($wtTotal - $wtAvail) : 0.0;

        // If status indicates the stock has moved, or quantities indicate it has moved, block revert.
        $movedByStatus = in_array((string) $s->status, ['reserved', 'issued', 'consumed', 'scrap'], true);
        $movedByQty    = ($pcsIssued > 0.0001) || ($wtIssued > 0.0001);

        if ($hasIssueLines || $movedByStatus || $movedByQty) {
            throw new \RuntimeException('Cannot change GRN status from QC PASSED because stock has already been issued/consumed/reserved.');
        }
    }
}

$makeAvailable = ($newStatus === 'qc_passed');

        foreach ($stocks as $s) {
            $pcsTotal = (float) ($s->qty_pcs_total ?? 0);
            $wtTotal  = (float) ($s->weight_kg_total ?? 0);

            if ($makeAvailable) {
                // Do not reactivate stock that has been vendor-returned / scrapped / already moved.
                if (in_array((string) $s->status, ['returned_vendor', 'scrap', 'consumed', 'issued', 'reserved'], true)) {
                    // Keep as-is (typically availability already 0 or partially moved).
                } else {
                    // Only unlock if it is currently QC-blocked (or effectively zero available).
                    $pcsAvail = (float) ($s->qty_pcs_available ?? 0);
                    $wtAvail  = (float) ($s->weight_kg_available ?? 0);
                    $isQcBlocked = (($s->status ?? null) === 'blocked_qc') || (($pcsAvail <= 0.0001) && ($wtAvail <= 0.0001));

                    if ($isQcBlocked) {
                        $s->qty_pcs_available   = $pcsTotal;
                        $s->weight_kg_available = $wtTotal;
                    }
                }
            } else {
                $s->qty_pcs_available   = 0;
                $s->weight_kg_available = 0;
            }

            // Explicitly manage status so QC-hold stock cannot leak into operational screens.
            if ($makeAvailable) {
                // When QC is passed, stock becomes operationally available.
                if (! $s->status || ($s->status === 'blocked_qc')) {
                    $s->status = 'available';
                }
            } else {
                // When QC is not passed, block stock (unless it is already in a terminal/moved state).
                if (! $s->status || ($s->status === 'available')) {
                    $s->status = 'blocked_qc';
                }
            }

            $s->save();
        }
    }

    /**
     * Infer store GRN "material_category" from Item material taxonomy (material_types/material_categories).
     *
     * Mapping:
     * - material_types.code = CONSUMABLE -> consumable
     * - material_types.code = RAW + material_categories.code = PM -> steel_plate
     * - material_types.code = RAW + material_categories.code = SEC -> steel_section
     * - else -> bought_out
     */
    protected function inferGrnCategoryFromTaxonomy(?string $materialTypeCode, ?string $materialCategoryCode): string
    {
        $type = strtoupper(trim((string) $materialTypeCode));
        $cat  = strtoupper(trim((string) $materialCategoryCode));

        if ($type === 'CONSUMABLE') {
            return 'consumable';
        }

        if ($type === 'RAW') {
            if (in_array($cat, ['PM', 'PLATE', 'PLATES'], true)) {
                return 'steel_plate';
            }

            if (in_array($cat, ['SEC', 'SECTION', 'SECTIONS'], true)) {
                return 'steel_section';
            }

            // Default RAW mapping when category is unknown.
            return 'steel_section';
        }

        return 'bought_out';
    }


		 /**
     * Recalculate PO item running totals for all QC-passed GRNs of a purchase order.
     *
     * It sets:
     *   - purchase_order_items.qty_pcs_received
     *   - purchase_order_items.quantity_received
     *
     * based on material_receipt_lines.qty_pcs and material_receipt_lines.received_weight_kg
     * across all MaterialReceipt rows for that PO where status = 'qc_passed'.
     */
    protected function recalculatePurchaseOrderReceivedTotals(int $purchaseOrderId): void
    {
        // Get all PO items upfront, keyed by id, so we can zero out those with no receipts
        $poItems = PurchaseOrderItem::where('purchase_order_id', $purchaseOrderId)
            ->get()
            ->keyBy('id');

        if ($poItems->isEmpty()) {
            return;
        }

        // Aggregate all QC-passed receipt lines for this PO, by PO item id
        $totals = MaterialReceiptLine::query()
            ->selectRaw(
                'purchase_order_item_id, ' .
                'SUM(COALESCE(qty_pcs, 0))               as total_qty_pcs, ' .
                'SUM(COALESCE(received_weight_kg, 0))    as total_quantity'
            )
            ->join('material_receipts', 'material_receipt_lines.material_receipt_id', '=', 'material_receipts.id')
            ->where('material_receipts.purchase_order_id', $purchaseOrderId)
            ->where('material_receipts.status', 'qc_passed')
            ->whereNotNull('material_receipt_lines.purchase_order_item_id')
            ->groupBy('purchase_order_item_id')
            ->get()
            ->keyBy('purchase_order_item_id');

        // Apply aggregates (or zeros) to each PO item
        foreach ($poItems as $itemId => $poItem) {
            $row = $totals->get($itemId);

            $poItem->qty_pcs_received  = $row ? (float) $row->total_qty_pcs : 0.0;
            $poItem->quantity_received = $row ? (float) $row->total_quantity : 0.0;
            $poItem->save();
        }
    }

    /**
     * Recalculate indent-level received totals + auto-close indent lines based on QC-passed GRNs.
     *
     * Notes:
     * - Only GRNs with status = 'qc_passed' are counted (same as PO received totals).
     * - Received quantity is taken as:
     *      - SUM(received_weight_kg) if > 0, else SUM(qty_pcs)
     *   because some materials are weight-based, some are piece-based.
     */
    protected function recalculateIndentReceivedTotals(int $purchaseOrderId, ?int $userId = null): void
    {
        // Identify indent lines affected by this PO
        $indentItemIds = \App\Models\PurchaseOrderItem::query()
            ->where('purchase_order_id', $purchaseOrderId)
            ->whereNotNull('purchase_indent_item_id')
            ->pluck('purchase_indent_item_id')
            ->unique()
            ->values()
            ->all();

        if (empty($indentItemIds)) {
            return;
        }

        // Aggregate QC-passed GRN lines across ALL GRNs for these indent lines (not just latest GRN),
        // so the totals stay correct if multiple receipts happen.
        $rows = \App\Models\MaterialReceiptLine::query()
            ->selectRaw(
                'poi.purchase_indent_item_id as indent_item_id, ' .
                'SUM(COALESCE(material_receipt_lines.qty_pcs, 0))            as total_pcs, ' .
                'SUM(COALESCE(material_receipt_lines.received_weight_kg, 0)) as total_weight'
            )
            ->join('material_receipts', 'material_receipt_lines.material_receipt_id', '=', 'material_receipts.id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'material_receipt_lines.purchase_order_item_id')
            ->whereIn('poi.purchase_indent_item_id', $indentItemIds)
            ->where('material_receipts.status', 'qc_passed')
            ->groupBy('poi.purchase_indent_item_id')
            ->get()
            ->keyBy('indent_item_id');

        $indentItems = \App\Models\PurchaseIndentItem::query()
            ->whereIn('id', $indentItemIds)
            ->get();

        $indentIds = [];

        foreach ($indentItems as $indentItem) {
            $indentIds[] = $indentItem->purchase_indent_id;

            $agg = $rows->get($indentItem->id);

            $totalWeight = $agg ? (float) $agg->total_weight : 0.0;
            $totalPcs    = $agg ? (float) $agg->total_pcs : 0.0;

            $received = $totalWeight > 0.000001 ? $totalWeight : $totalPcs;

            // Persist running total
            $indentItem->received_qty_total = $received;

            // Auto-close line when received >= required (with tiny tolerance)
            $required = (float) ($indentItem->order_qty ?? 0);
            $isClosed = $required > 0
                ? (($received - $required) >= -0.0001)
                : ($received > 0);

            $indentItem->receipt_status = $received > 0
                ? ($isClosed ? 'received' : 'partially_received')
                : null;

            // Flip close fields appropriately (supports reversing QC status too)
            if ($isClosed) {
                if ((int) ($indentItem->is_closed ?? 0) !== 1) {
                    $indentItem->is_closed = 1;
                    $indentItem->closed_at = now();
                    if ($userId) {
                        $indentItem->closed_by = $userId;
                    }
                }
            } else {
                if ((int) ($indentItem->is_closed ?? 0) === 1) {
                    $indentItem->is_closed = 0;
                    $indentItem->closed_at = null;
                    $indentItem->closed_by = null;
                }
            }

            $indentItem->save();
        }

        $indentIds = array_values(array_unique(array_filter($indentIds)));

        if (empty($indentIds)) {
            return;
        }

        // Update indent header procurement_status based on line closure/receipts
        $indents = \App\Models\PurchaseIndent::with('items')
            ->whereIn('id', $indentIds)
            ->get();

        foreach ($indents as $indent) {
            // Never touch cancelled indents
            if ($indent->procurement_status === 'cancelled') {
                continue;
            }

            $items = $indent->items ?? collect();

            if ($items->isEmpty()) {
                continue;
            }

            $allClosed   = $items->every(function ($it) {
                return (int) ($it->is_closed ?? 0) === 1;
            });

            $anyReceived = $items->some(function ($it) {
                return (float) ($it->received_qty_total ?? 0) > 0;
            });

            if ($allClosed) {
                $indent->procurement_status = 'closed';
            } elseif ($anyReceived) {
                // Keep ordered/partially_ordered semantics but show receipt progress
                $indent->procurement_status = 'partially_received';
            }

            $indent->save();
        }
    }


    /**
     * Cancel/Delete GRN (only allowed BEFORE QC PASSED and only if stock has not moved).
     *
     * NOTE: We hard-delete the GRN and its dependent rows because:
     * - status set does not include "cancelled" in current schema/workflow
     * - pre-QC GRNs should be reversible without leaving unusable inventory records
     */
		public function destroy(MaterialReceipt $materialReceipt): RedirectResponse
		{
    // Keep your existing rule: QC PASSED cannot be deleted
    if ($materialReceipt->status === 'qc_passed') {
        return back()->with('error', 'QC PASSED GRN cannot be deleted. Use vendor return process instead.');
    }

    DB::beginTransaction();

    try {
        $materialReceipt->load(['lines.attachments', 'attachments']);

        $lineIds = $materialReceipt->lines->pluck('id')->all();

        // Guard: do not allow delete if any linked stock has actually moved/been used
        if (! empty($lineIds)) {
            $stocks = StoreStockItem::query()
                ->whereIn('material_receipt_line_id', $lineIds)
                ->lockForUpdate()
                ->get();

            if ($stocks->isNotEmpty()) {
                $stockIds = $stocks->pluck('id')->all();

                // ------------------------------------------------------------------
                // IMPORTANT FIX:
                // Do NOT use (total - available) as "moved" here because for QC-hold
                // GRNs you intentionally set available = 0 even when nothing moved.
                // ------------------------------------------------------------------

                // 1) If stock status itself shows movement/usage, block delete
                $movedByStatus = $stocks->contains(function ($s) {
                    return in_array((string) $s->status, [
                        'reserved',
                        'issued',
                        'consumed',
                        'scrap',
                        'returned_vendor',
                    ], true);
                });

                // 2) If any movement/reference tables link to this stock, block delete
                $hasIssueLines = DB::table('store_issue_lines')
                    ->whereIn('store_stock_item_id', $stockIds)
                    ->exists();

                $hasReturnLines = DB::table('store_return_lines')
                    ->where(function ($q) use ($stockIds) {
                        $q->whereIn('store_stock_item_id', $stockIds)
                          ->orWhereIn('scrap_stock_item_id', $stockIds);
                    })
                    ->exists();

                $hasGatePassLines = DB::table('gate_pass_lines')
                    ->whereIn('store_stock_item_id', $stockIds)
                    ->exists();

                $hasStockAdjustments = DB::table('store_stock_adjustment_lines')
                    ->whereIn('store_stock_item_id', $stockIds)
                    ->exists();

                // Production traceability consumes mother stock without store_issue_lines
                $hasProductionPieces = DB::table('production_pieces')
                    ->whereIn('mother_stock_item_id', $stockIds)
                    ->exists();

                $hasProductionRemnants = DB::table('production_remnants')
                    ->where(function ($q) use ($stockIds) {
                        $q->whereIn('mother_stock_item_id', $stockIds)
                          ->orWhereIn('remnant_stock_item_id', $stockIds);
                    })
                    ->exists();

                if (
                    $movedByStatus ||
                    $hasIssueLines ||
                    $hasReturnLines ||
                    $hasGatePassLines ||
                    $hasStockAdjustments ||
                    $hasProductionPieces ||
                    $hasProductionRemnants
                ) {
                    throw new \RuntimeException('Cannot delete GRN because linked stock has already moved/issued.');
                }

                // Delete stock rows (safe because nothing moved and GRN is not QC passed)
                StoreStockItem::whereIn('id', $stockIds)->delete();
            }
        }

        // Delete attachments (files + db) - KEEP AS IS
        $disk = 'public';

        foreach ($materialReceipt->attachments as $att) {
            if ($att->path && Storage::disk($disk)->exists($att->path)) {
                Storage::disk($disk)->delete($att->path);
            }
            $att->delete();
        }

        foreach ($materialReceipt->lines as $line) {
            foreach ($line->attachments as $att) {
                if ($att->path && Storage::disk($disk)->exists($att->path)) {
                    Storage::disk($disk)->delete($att->path);
                }
                $att->delete();
            }
        }

        // Delete lines + receipt - KEEP AS IS
        MaterialReceiptLine::where('material_receipt_id', $materialReceipt->id)->delete();
        $materialReceipt->delete();

        DB::commit();

        return redirect()
            ->route('material-receipts.index')
            ->with('success', 'GRN deleted successfully.');
    } catch (\Throwable $e) {
        DB::rollBack();
        return back()->with('error', 'Failed to delete GRN: ' . $e->getMessage());
    }
	}


    /**
     * Vendor Return screen for a QC PASSED GRN.
     *
     * This is a "correction style" return:
     * - We reduce GRN line qty_pcs / received_weight_kg by the returned quantities
     * - We mark corresponding StoreStockItem rows as returned_vendor (availability stays 0)
     * - We then recalc PO received totals + Indent received totals (because QC-passed GRNs drive those totals)
     *
     * IMPORTANT: Vendor return is allowed only if the GRN stock has not moved/issued.
     */
    public function createReturn(MaterialReceipt $materialReceipt): View
    {
        $materialReceipt->load(['supplier', 'client', 'project', 'lines.item.uom', 'vendorReturns.lines']);

        $lineIds = $materialReceipt->lines->pluck('id')->all();

        $stocksByLine = collect();
        if (! empty($lineIds)) {
            $stocksByLine = StoreStockItem::query()
                ->whereIn('material_receipt_line_id', $lineIds)
                ->orderBy('id')
                ->get()
                ->groupBy('material_receipt_line_id');
        }

        return view('material_receipts.return', [
            'receipt' => $materialReceipt,
            'stocksByLine' => $stocksByLine,
        ]);
    }


    public function storeReturn(Request $request, MaterialReceipt $materialReceipt): RedirectResponse
    {
        if ($materialReceipt->status !== 'qc_passed') {
            return back()->with('error', 'Vendor return is allowed only for QC passed GRN.');
        }

        $data = $request->validate([
            'return_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'integer', 'exists:material_receipt_lines,id'],

            // Optional: user can either enter return_qty_pcs OR select specific stock_ids (preferred for plates).
            'lines.*.return_qty_pcs' => ['nullable', 'integer', 'min:0'],
            'lines.*.return_weight_kg' => ['nullable', 'numeric', 'min:0'],

            'lines.*.stock_ids' => ['nullable', 'array'],
            'lines.*.stock_ids.*' => ['integer', 'exists:store_stock_items,id'],
        ]);

        DB::beginTransaction();

        try {
            $materialReceipt->load([
                'supplier',
                'client',
                'project',
                'purchaseOrder',
                'lines',
            ]);

            $linesById = $materialReceipt->lines->keyBy('id');

            $lineIds = $materialReceipt->lines->pluck('id')->all();

            // Lock stock rows for these GRN lines
            $stocksByLine = StoreStockItem::query()
                ->whereIn('material_receipt_line_id', $lineIds)
                ->lockForUpdate()
                ->orderBy('id')
                ->get()
                ->groupBy('material_receipt_line_id');

            $allStockIds = $stocksByLine->flatten()->pluck('id')->all();

            // Safety: block vendor return if any GRN stock has been issued or returned (i.e., moved).
            $hasIssueLines = !empty($allStockIds) && DB::table('store_issue_lines')->whereIn('store_stock_item_id', $allStockIds)->exists();
            $hasReturnLines = !empty($allStockIds) && DB::table('store_return_lines')->whereIn('store_stock_item_id', $allStockIds)->exists();

            if ($hasIssueLines || $hasReturnLines) {
                DB::rollBack();
                return back()->with('error', 'Cannot vendor return: Stock already moved (issued/returned).');
            }

            $toPartyId = $materialReceipt->is_client_material
                ? $materialReceipt->client_party_id
                : $materialReceipt->supplier_id;

            // Create Vendor Return document (header)
            $vendorReturn = MaterialVendorReturn::create([
                'material_receipt_id' => $materialReceipt->id,
                'vendor_return_number' => null, // generated after create
                'return_date' => $data['return_date'],
                'to_party_id' => $toPartyId,
                'project_id' => $materialReceipt->project_id,
                'reason' => $data['reason'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $vendorReturn->update([
                'vendor_return_number' => app(DocumentNumberService::class)->materialVendorReturn($vendorReturn),
            ]);

            $returnMetrics = []; // line_id => ['material_category' => '', 'return_pcs' => x, 'return_wt' => y]

            foreach ($data['lines'] as $row) {
                $lineId = (int) ($row['line_id'] ?? 0);
                /** @var MaterialReceiptLine|null $line */
                $line = $linesById->get($lineId);

                if (!$line) {
                    continue;
                }

                $stocks = $stocksByLine->get($lineId, collect());

                // Only stocks that are still available are returnable (for plates/consumables).
                $availableStocks = $stocks->where('status', 'available')->values();

                // Steel sections are maintained as a single combined stock row -> partial return supported.
                if ($line->material_category === 'steel_section') {
                    $stock = $stocks->first();
                    if (!$stock) {
                        continue;
                    }

                    $returnPcs = (int) ($row['return_qty_pcs'] ?? 0);
                    if ($returnPcs <= 0) {
                        continue;
                    }

                    $origLinePcs = (int) ($line->qty_pcs ?? 0);
                    if ($origLinePcs <= 0) {
                        continue;
                    }

                    // Weight: if user didn't specify, pro-rate by pcs ratio.
                    $ratio = $returnPcs / $origLinePcs;
                    $returnWt = isset($row['return_weight_kg']) && $row['return_weight_kg'] !== '' && $row['return_weight_kg'] !== null
                        ? (float) $row['return_weight_kg']
                        : (float) $stock->weight_kg_total * $ratio;

                    if ($returnPcs > (int) $stock->qty_pcs_available) {
                        throw new \RuntimeException("Return pcs exceeds available for line {$lineId}.");
                    }
                    if ($returnWt > (float) $stock->weight_kg_available + 0.0001) {
                        throw new \RuntimeException("Return weight exceeds available for line {$lineId}.");
                    }

                    // Update stock totals/available
                    $stock->qty_pcs_total = max(0, (int) $stock->qty_pcs_total - $returnPcs);
                    $stock->qty_pcs_available = max(0, (int) $stock->qty_pcs_available - $returnPcs);

                    $stock->weight_kg_total = max(0, (float) $stock->weight_kg_total - $returnWt);
                    $stock->weight_kg_available = max(0, (float) $stock->weight_kg_available - $returnWt);

                    if ((int) $stock->qty_pcs_total === 0 || (float) $stock->weight_kg_total === 0.0) {
                        $stock->status = 'returned_vendor';
                    }

                    $stock->save();

                    // Update GRN line (reduce net received)
                    $origLineWt = (float) ($line->received_weight_kg ?? 0);
                    $origLineAmt = (float) ($line->total_amount ?? 0);

                    $line->qty_pcs = max(0, $origLinePcs - $returnPcs);
                    $line->received_weight_kg = max(0, $origLineWt - $returnWt);
                    $line->total_amount = max(0, $origLineAmt - ($origLineAmt * $ratio));
                    $line->save();

                    // Document line
                    MaterialVendorReturnLine::create([
                        'material_vendor_return_id' => $vendorReturn->id,
                        'material_receipt_line_id' => $lineId,
                        'store_stock_item_id' => $stock->id,
                        'item_id' => $line->item_id,
                        'material_category' => $line->material_category,
                        'returned_qty_pcs' => $returnPcs,
                        'returned_weight_kg' => $returnWt,
                    ]);

                    $returnMetrics[$lineId] = $returnMetrics[$lineId] ?? [
                        'material_category' => $line->material_category,
                        'return_pcs' => 0,
                        'return_wt' => 0,
                    ];
                    $returnMetrics[$lineId]['return_pcs'] += $returnPcs;
                    $returnMetrics[$lineId]['return_wt'] += $returnWt;

                    continue;
                }

                // Plates / other categories - piece-wise returns
                $selectedStockIds = collect($row['stock_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();

                if ($selectedStockIds->isNotEmpty()) {
                    $stocksToReturn = $availableStocks->whereIn('id', $selectedStockIds)->values();

                    if ($stocksToReturn->count() !== $selectedStockIds->count()) {
                        throw new \RuntimeException("One or more selected stock rows are not returnable for line {$lineId}.");
                    }
                } else {
                    $returnPcs = (int) ($row['return_qty_pcs'] ?? 0);
                    if ($returnPcs <= 0) {
                        continue;
                    }

                    $stocksToReturn = $availableStocks->take($returnPcs)->values();

                    if ($stocksToReturn->count() !== $returnPcs) {
                        throw new \RuntimeException("Insufficient available stock to return {$returnPcs} pcs for line {$lineId}.");
                    }
                }

                $returnPcs = $stocksToReturn->count();
                if ($returnPcs <= 0) {
                    continue;
                }

                $returnWt = (float) $stocksToReturn->sum(function ($s) {
                    return (float) ($s->weight_kg_total ?? 0);
                });

                // Mark stocks returned + create doc lines
                foreach ($stocksToReturn as $s) {
                    $s->status = 'returned_vendor';
                    $s->qty_pcs_available = 0;
                    $s->weight_kg_available = 0;
                    $s->save();

                    MaterialVendorReturnLine::create([
                        'material_vendor_return_id' => $vendorReturn->id,
                        'material_receipt_line_id' => $lineId,
                        'store_stock_item_id' => $s->id,
                        'item_id' => $line->item_id,
                        'material_category' => $line->material_category,
                        'returned_qty_pcs' => 1,
                        'returned_weight_kg' => (float) ($s->weight_kg_total ?? 0),
                    ]);
                }

                // Update GRN line (reduce net received)
                $origLinePcs = (int) ($line->qty_pcs ?? 0);
                $origLineWt = (float) ($line->received_weight_kg ?? 0);
                $origLineAmt = (float) ($line->total_amount ?? 0);

                $ratio = ($origLinePcs > 0) ? ($returnPcs / $origLinePcs) : 0;

                $line->qty_pcs = max(0, $origLinePcs - $returnPcs);
                $line->received_weight_kg = max(0, $origLineWt - $returnWt);
                $line->total_amount = max(0, $origLineAmt - ($origLineAmt * $ratio));
                $line->save();

                $returnMetrics[$lineId] = $returnMetrics[$lineId] ?? [
                    'material_category' => $line->material_category,
                    'return_pcs' => 0,
                    'return_wt' => 0,
                ];
                $returnMetrics[$lineId]['return_pcs'] += $returnPcs;
                $returnMetrics[$lineId]['return_wt'] += $returnWt;
            }

            if (empty($returnMetrics)) {
                DB::rollBack();
                return back()->with('error', 'No return quantities were specified.');
            }

            /**
             * Re-sync Purchase Order received totals based on updated GRN lines
             */
            if ($materialReceipt->purchase_order_id) {
                $poId = $materialReceipt->purchase_order_id;

                $poLines = DB::table('purchase_order_lines')
                    ->where('purchase_order_id', $poId)
                    ->get(['id']);

                foreach ($poLines as $poLine) {
                    $totalReceived = DB::table('material_receipt_lines')
                        ->join('material_receipts', 'material_receipts.id', '=', 'material_receipt_lines.material_receipt_id')
                        ->where('material_receipts.purchase_order_id', $poId)
                        ->where('material_receipt_lines.purchase_order_line_id', $poLine->id)
                        ->sum('material_receipt_lines.qty_pcs');

                    DB::table('purchase_order_lines')
                        ->where('id', $poLine->id)
                        ->update(['received_qty_pcs' => $totalReceived]);
                }
            }

            /**
             * Re-sync Indent received totals (if PO linked to an indent)
             */
            if ($materialReceipt->purchaseOrder && $materialReceipt->purchaseOrder->indent_id) {
                $indentId = $materialReceipt->purchaseOrder->indent_id;

                $indentLines = DB::table('indent_lines')
                    ->where('indent_id', $indentId)
                    ->get(['id']);

                foreach ($indentLines as $indentLine) {
                    $totalReceived = DB::table('material_receipt_lines')
                        ->join('material_receipts', 'material_receipts.id', '=', 'material_receipt_lines.material_receipt_id')
                        ->join('purchase_orders', 'purchase_orders.id', '=', 'material_receipts.purchase_order_id')
                        ->where('purchase_orders.indent_id', $indentId)
                        ->where('material_receipt_lines.indent_line_id', $indentLine->id)
                        ->sum('material_receipt_lines.qty_pcs');

                    DB::table('indent_lines')
                        ->where('id', $indentLine->id)
                        ->update(['received_qty_pcs' => $totalReceived]);
                }
            }

            /**
             * Accounting reversal (Own material only): post a journal voucher that reverses inventory for returned amount
             * - Only if purchase bills are POSTED.
             * - Prevent over-reversal across multiple vendor returns.
             */
            if (! $materialReceipt->is_client_material && !empty($returnMetrics)) {
                $companyId = auth()->user()->company_id;
                $supplierId = $materialReceipt->supplier_id;

                if ($supplierId) {
                    $rawCategories = ['steel_plate', 'steel_section'];
                    $lineIdsForPosting = array_keys($returnMetrics);

                    // Block posting if any draft purchase bill exists for these GRN lines
                    $hasDraftBill = DB::table('purchase_bill_lines as pbl')
                        ->join('purchase_bills as pb', 'pb.id', '=', 'pbl.purchase_bill_id')
                        ->whereIn('pbl.material_receipt_line_id', $lineIdsForPosting)
                        ->where('pb.status', 'draft')
                        ->exists();

                    if ($hasDraftBill) {
                        throw new \RuntimeException('Cannot vendor return: Draft Purchase Bill exists for returned items. Please post or cancel it first.');
                    }

                    $billAggRows = DB::table('purchase_bill_lines as pbl')
                        ->join('purchase_bills as pb', 'pb.id', '=', 'pbl.purchase_bill_id')
                        ->whereIn('pbl.material_receipt_line_id', $lineIdsForPosting)
                        ->where('pb.status', 'posted')
                        ->whereNotNull('pb.voucher_id')
                        ->groupBy('pbl.material_receipt_line_id')
                        ->selectRaw('pbl.material_receipt_line_id, SUM(pbl.qty) as billed_qty, SUM(pbl.basic_amount) as billed_basic')
                        ->get()
                        ->keyBy('material_receipt_line_id');

                    // Already reversed (only for returns which created a voucher)
                    $alreadyReversed = DB::table('material_vendor_return_lines as mvl')
                        ->join('material_vendor_returns as mvr', 'mvr.id', '=', 'mvl.material_vendor_return_id')
                        ->where('mvr.material_receipt_id', $materialReceipt->id)
                        ->whereNotNull('mvr.voucher_id')
                        ->groupBy('mvl.material_receipt_line_id')
                        ->selectRaw('mvl.material_receipt_line_id, SUM(COALESCE(mvl.returned_qty_pcs,0)) as sum_pcs, SUM(COALESCE(mvl.returned_weight_kg,0)) as sum_wt')
                        ->get()
                        ->keyBy('material_receipt_line_id');

                    $returnBasicByBucket = [
                        'raw' => 0.0,
                        'consumables' => 0.0,
                    ];

                    foreach ($returnMetrics as $lineId => $meta) {
                        $bill = $billAggRows->get($lineId);
                        if (!$bill) {
                            // No posted bill line -> no voucher impact for this GRN line.
                            continue;
                        }

                        $billedQty = (float) ($bill->billed_qty ?? 0);
                        $billedBasic = (float) ($bill->billed_basic ?? 0);

                        if ($billedQty <= 0 || $billedBasic <= 0) {
                            continue;
                        }

                        $cat = $meta['material_category'] ?? null;
                        $isRaw = $cat && in_array($cat, $rawCategories, true);

                        $returnComparable = $isRaw ? (float) ($meta['return_pcs'] ?? 0) : (float) ($meta['return_wt'] ?? 0);

                        if ($returnComparable <= 0) {
                            continue;
                        }

                        $alreadyRow = $alreadyReversed->get($lineId);
                        $alreadyComparable = $alreadyRow
                            ? ($isRaw ? (float) ($alreadyRow->sum_pcs ?? 0) : (float) ($alreadyRow->sum_wt ?? 0))
                            : 0.0;

                        $remaining = $billedQty - $alreadyComparable;

                        if ($remaining <= 0) {
                            throw new \RuntimeException("Cannot vendor return: Already reversed full billed quantity for GRN line {$lineId}.");
                        }

                        if ($returnComparable > $remaining + 0.0001) {
                            throw new \RuntimeException("Cannot vendor return: Return qty exceeds remaining billed qty for GRN line {$lineId}.");
                        }

                        $unitRate = $billedBasic / $billedQty;
                        $returnBasic = $returnComparable * $unitRate;

                        if ($isRaw) {
                            $returnBasicByBucket['raw'] += $returnBasic;
                        } else {
                            $returnBasicByBucket['consumables'] += $returnBasic;
                        }
                    }

                    $totalReturnBasic = $returnBasicByBucket['raw'] + $returnBasicByBucket['consumables'];

                    if ($totalReturnBasic > 0.0001) {
                        $supplier = Party::findOrFail($supplierId);

                        $supplierAccount = Account::where('accountable_type', Party::class)
                            ->where('accountable_id', $supplierId)
                            ->first();

                        if (!$supplierAccount) {
                            $supplierAccount = Account::create([
                                'company_id' => $companyId,
                                'account_name' => $supplier->name,
                                'account_type' => 'liability',
                                'accountable_type' => Party::class,
                                'accountable_id' => $supplierId,
                                'created_by' => auth()->id(),
                            ]);
                        }

                        $inventoryRawAccountId = config('accounts.inventory_raw_material_account_id');
                        $inventoryConsumablesAccountId = config('accounts.inventory_consumables_account_id');

                        $voucher = Voucher::create([
                            'company_id' => $companyId,
                            'voucher_date' => $data['return_date'],
                            'voucher_type' => 'journal',
                            'status' => 'posted',
                            'reference' => 'VRET:' . $vendorReturn->vendor_return_number,
                            'narration' => 'Vendor return ' . $vendorReturn->vendor_return_number . ' against GRN ' . $materialReceipt->receipt_number,
                            'created_by' => auth()->id(),
                        ]);

                        $voucherNumberService = new VoucherNumberService();
                        $voucherNo = $voucherNumberService->generateVoucherNumber($voucher);
                        $voucher->update(['voucher_no' => $voucherNo]);

                        // Credit inventory accounts
                        if ($returnBasicByBucket['raw'] > 0.0001) {
                            VoucherLine::create([
                                'voucher_id' => $voucher->id,
                                'account_id' => $inventoryRawAccountId,
                                'entry_type' => 'credit',
                                'amount' => $returnBasicByBucket['raw'],
                                'narration' => 'Vendor return (Raw Materials): ' . $vendorReturn->vendor_return_number,
                                'reference_type' => MaterialVendorReturn::class,
                                'reference_id' => $vendorReturn->id,
                                'created_by' => auth()->id(),
                            ]);
                        }

                        if ($returnBasicByBucket['consumables'] > 0.0001) {
                            VoucherLine::create([
                                'voucher_id' => $voucher->id,
                                'account_id' => $inventoryConsumablesAccountId,
                                'entry_type' => 'credit',
                                'amount' => $returnBasicByBucket['consumables'],
                                'narration' => 'Vendor return (Consumables): ' . $vendorReturn->vendor_return_number,
                                'reference_type' => MaterialVendorReturn::class,
                                'reference_id' => $vendorReturn->id,
                                'created_by' => auth()->id(),
                            ]);
                        }

                        // Debit supplier (reduce payable)
                        VoucherLine::create([
                            'voucher_id' => $voucher->id,
                            'account_id' => $supplierAccount->id,
                            'entry_type' => 'debit',
                            'amount' => $totalReturnBasic,
                            'narration' => 'Vendor return: ' . $vendorReturn->vendor_return_number,
                            'reference_type' => MaterialVendorReturn::class,
                            'reference_id' => $vendorReturn->id,
                            'created_by' => auth()->id(),
                        ]);

                        $vendorReturn->update(['voucher_id' => $voucher->id]);

                        if (class_exists(\App\Models\ActivityLog::class)) {
                            \App\Models\ActivityLog::logCustom(
                                auth()->id(),
                                $voucher,
                                'vendor_return_voucher_created',
                                'Vendor return voucher created',
                                [
                                    'material_receipt_id' => $materialReceipt->id,
                                    'vendor_return_id' => $vendorReturn->id,
                                    'voucher_id' => $voucher->id,
                                    'voucher_no' => $voucher->voucher_no,
                                    'amount' => $totalReturnBasic,
                                ]
                            );
                        }
                    }
                }
            }

            DB::commit();

            return redirect()
                ->route('material-receipts.show', $materialReceipt)
                ->with('success', 'Vendor return saved: ' . ($vendorReturn->vendor_return_number ?? ('#' . $vendorReturn->id)));
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Vendor return failed: ' . $e->getMessage());
        }
    }

}
