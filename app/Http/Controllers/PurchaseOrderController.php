<?php

namespace App\Http\Controllers;

use App\Mail\PurchaseOrderMail;
use App\Models\Company;
use App\Models\Party;
use App\Models\PartyBranch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRfq;
use App\Models\PurchaseRfqVendorQuote;
use App\Models\StandardTerm;
use App\Services\PurchaseIndentProcurementService;
use App\Services\MailService;
use App\Support\GstHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:purchase.po.view')->only(['index', 'show', 'print', 'pdf']);
        $this->middleware('permission:purchase.po.view|store.material_receipt.create')->only(['itemsForGrn']);
        $this->middleware('permission:purchase.po.create')->only(['storeFromRfq']);
        $this->middleware('permission:purchase.po.approve')->only(['approve']);
        $this->middleware('permission:purchase.po.send')->only(['sendEmail']);
        $this->middleware('permission:purchase.po.update')->only(['edit', 'update']);
        $this->middleware('permission:purchase.po.delete')->only(['cancel']);
    }

   public function index(Request $request): View|\Illuminate\Http\JsonResponse
{
    $query = PurchaseOrder::query()
        ->with(['vendor', 'vendorBranch', 'project'])
        ->orderByDesc('id');

   if ($poNumber = trim((string) $request->get('po_number', ''))) {
    $query->where('code', 'like', '%' . $poNumber . '%');
}

if ($vendorId = (int) $request->get('vendor_id', 0)) {
    $query->where('vendor_party_id', $vendorId);
}


    if ($status = trim((string) $request->get('status', ''))) {
        $query->where('status', $status);
    }

    if ($vendorId = (int) $request->get('vendor_id', 0)) {
        $query->where('vendor_party_id', $vendorId);
    }

    if ($projectId = (int) $request->get('project_id', 0)) {
        $query->where('project_id', $projectId);
    }

    $orders = $query->paginate(25);

    if ($request->ajax()) {
        return response()->json([
            'html' => view('purchase_orders.partials.table', compact('orders'))->render()
        ]);
    }

    $vendors = Party::where('is_supplier', true)->orderBy('name')->get(['id','name']);
    $projects = \App\Models\Project::orderBy('code')->orderBy('name')->get(['id','code','name']);

    return view('purchase_orders.index', compact('orders','vendors','projects'));
}


    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'project',
            'department',
            'vendor',
            'vendorBranch',
            'vendor.branches',
            'rfq',
            'indent',
            'items.item',
            'items.uom',
        ]);

        return view('purchase_orders.show', [
            'order' => $purchaseOrder,
        ]);
    }

    /**
     * Edit PO (draft only).
     */
    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'project',
            'department',
            'vendor',
            'vendorBranch',
            'vendor.branches',
            'rfq',
            'indent',
            'items.item',
            'items.uom',
        ]);

        // Standard Terms templates (module: purchase, sub_module: po)
        $terms = collect();
        if (Schema::hasTable('standard_terms') && class_exists(StandardTerm::class)) {
            $terms = StandardTerm::query()
                ->where('module', 'purchase')
                ->where('sub_module', 'po')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        return view('purchase_orders.edit', [
            'order' => $purchaseOrder,
            'terms' => $terms,
        ]);
    }

    /**
     * Update PO (draft only).
     * Supports:
     * - remarks
     * - T&C template (standard_term_id) + editable terms_text
     * - GRN tolerance per line (grn_tolerance_percent)
     * - Recompute commercial totals & GST split on save
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (($purchaseOrder->status ?? 'draft') !== 'draft') {
            return redirect()
                ->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Only Draft Purchase Orders can be edited.');
        }

        $standardTermRule = ['nullable', 'integer'];
        if (Schema::hasTable('standard_terms')) {
            $standardTermRule[] = Rule::exists('standard_terms', 'id');
        }

        $vendorBranchRule = ['nullable', 'integer'];
        if (Schema::hasTable('party_branches')) {
            $vendorBranchRule[] = Rule::exists('party_branches', 'id')
                ->where(fn ($q) => $q->where('party_id', (int) $purchaseOrder->vendor_party_id));
        }

        $validated = $request->validate([
            'po_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],

            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'delivery_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'freight_terms' => ['nullable', 'string', 'max:255'],

            // Vendor GSTIN / Branch
            'vendor_branch_id' => $vendorBranchRule,

            // Terms template module
            'standard_term_id' => $standardTermRule,
            'terms_text' => ['nullable', 'string'],

            'remarks' => ['nullable', 'string', 'max:5000'],

            'items' => ['required', 'array'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'items.*.grn_tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
        ]);

        $purchaseOrder->load(['vendor', 'vendorBranch', 'items']);

        $company = Company::where('is_default', true)->first();
        $vendor = $purchaseOrder->vendor;

        $vendorBranchId = $validated['vendor_branch_id'] ?? ($purchaseOrder->vendor_branch_id ?? null);
        $vendorBranch = null;
        if (!empty($vendorBranchId) && Schema::hasTable('party_branches')) {
            $vendorBranch = PartyBranch::query()
                ->where('party_id', (int) $purchaseOrder->vendor_party_id)
                ->where('id', (int) $vendorBranchId)
                ->first();
        }

        $gstType = $this->resolveGstTypeForVendor($company, $vendor, $vendorBranch);

        DB::transaction(function () use ($purchaseOrder, $validated, $gstType) {
            // Header update
            $orderData = [
                'po_date' => $validated['po_date'] ?? $purchaseOrder->po_date,
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? $purchaseOrder->expected_delivery_date,
                'payment_terms_days' => $validated['payment_terms_days'] ?? $purchaseOrder->payment_terms_days,
                'delivery_terms_days' => $validated['delivery_terms_days'] ?? $purchaseOrder->delivery_terms_days,
                'freight_terms' => $validated['freight_terms'] ?? $purchaseOrder->freight_terms,
                'remarks' => $validated['remarks'] ?? $purchaseOrder->remarks,
                'vendor_branch_id' => $validated['vendor_branch_id'] ?? null,
            ];

            // Standard terms module fields (only if these columns exist)
            if (Schema::hasColumn('purchase_orders', 'standard_term_id')) {
                $orderData['standard_term_id'] = $validated['standard_term_id'] ?? null;
            }
            if (Schema::hasColumn('purchase_orders', 'terms_text')) {
                $orderData['terms_text'] = $validated['terms_text'] ?? null;
            }

            $this->applyOrderData($purchaseOrder, $orderData);
            $purchaseOrder->save();

            $total = 0.0;

            /** @var array<string,array<string,mixed>> $itemsInput */
            $itemsInput = $validated['items'] ?? [];

            foreach ($purchaseOrder->items as $line) {
                $row = $itemsInput[(string) $line->id] ?? null;
                if (!is_array($row)) {
                    continue;
                }

                $qty = (float) ($row['quantity'] ?? $line->quantity ?? 0);
                $rate = (float) ($row['rate'] ?? $line->rate ?? 0);
                $discPct = (float) ($row['discount_percent'] ?? $line->discount_percent ?? 0);
                $taxPct = (float) ($row['tax_percent'] ?? $line->tax_percent ?? 0);
                $tolPct = (float) ($row['grn_tolerance_percent'] ?? ($line->grn_tolerance_percent ?? 0));

                if ($tolPct < 0) {
                    $tolPct = 0;
                }

                $gross = $qty * $rate;
                $discAmt = $discPct > 0 ? ($gross * $discPct / 100) : 0;
                $amount = round($gross - $discAmt, 2); // basic / amount
                $taxAmt = round($amount * $taxPct / 100, 2);

                [$gstAmounts, $gstPercents] = $this->gstSplit($taxAmt, $taxPct, $gstType);

                $net = round($amount + $taxAmt, 2);

                $lineData = [
                    'quantity' => $qty,
                    'rate' => $rate,
                    'discount_percent' => $discPct,
                    'tax_percent' => $taxPct,

                    // Common alternate column names
                    'tax_rate' => $taxPct,
                    'discount_amount' => round($discAmt, 2),
                    'tax_amount' => $taxAmt,

                    'amount' => $amount,
                    'basic_amount' => $amount,
                    'net_amount' => $net,
                    'total_amount' => $net,

                    // GST split
                    'gst_type' => $gstType,
                    'cgst_percent' => $gstPercents['cgst'] ?? null,
                    'sgst_percent' => $gstPercents['sgst'] ?? null,
                    'igst_percent' => $gstPercents['igst'] ?? null,
                    'cgst_amount' => $gstAmounts['cgst'] ?? 0,
                    'sgst_amount' => $gstAmounts['sgst'] ?? 0,
                    'igst_amount' => $gstAmounts['igst'] ?? 0,

                    // GRN tolerance
                    'grn_tolerance_percent' => $tolPct,
                ];

                $this->applyOrderItemData($line, $lineData);
                $line->save();

                $total += $net;
            }

            $this->applyOrderData($purchaseOrder, [
                'total_amount' => round($total, 2),
            ]);
            $purchaseOrder->save();

            // If PO is linked to indent, recalc procurement totals
            if (!empty($purchaseOrder->purchase_indent_id)) {
                app(PurchaseIndentProcurementService::class)
                    ->recalcIndent((int) $purchaseOrder->purchase_indent_id);
            }
        });

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase Order updated successfully.');
    }

    /**
     * Generate PO(s) from RFQ L1 selections (supports multi-vendor award).
     */
    public function storeFromRfq(Request $request, PurchaseRfq $purchaseRfq): RedirectResponse
    {
        $purchaseRfq->load(['items.vendorQuotes', 'vendors', 'project', 'department']);

        // Group RFQ items by selected vendor
        $groups = [];
        foreach ($purchaseRfq->items as $rfqItem) {
            $vendorId = (int) ($rfqItem->selected_vendor_id ?? 0);
            if (!$vendorId) {
                continue;
            }

            $groups[$vendorId] ??= [
                'rfqVendor' => $purchaseRfq->vendors->firstWhere('id', $vendorId),
                'items'     => [],
            ];

            $groups[$vendorId]['items'][] = $rfqItem;
        }

        if (empty($groups)) {
            return back()
                ->withInput()
                ->with('error', 'No L1 vendor selected on any RFQ line. Please select L1 vendors before generating PO.');
        }

        // If the form passes a subset of vendors, honour that
        $selectedVendorIds = collect($request->input('vendor_ids', array_keys($groups)))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($selectedVendorIds)) {
            $groups = array_intersect_key($groups, array_flip($selectedVendorIds));
        }

        if (empty($groups)) {
            return back()
                ->withInput()
                ->with('error', 'No valid vendors selected for PO generation.');
        }

        // Prevent duplicate PO for the same RFQ + vendor
        $targetVendorPartyIds = collect($groups)
            ->map(function ($group) {
                $v = $group['rfqVendor'];
                return $v->vendor_party_id ?? $v->vendor_id ?? null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($targetVendorPartyIds)) {
            $existingVendors = PurchaseOrder::where('purchase_rfq_id', $purchaseRfq->id)
                ->whereIn('vendor_party_id', $targetVendorPartyIds)
                ->pluck('vendor_party_id')
                ->all();

            if (!empty($existingVendors)) {
                return back()
                    ->withInput()
                    ->with('error', 'Purchase Order already exists for one or more selected vendors for this RFQ.');
            }
        }

        $createdOrders = [];
        $company = Company::where('is_default', true)->first();

        // Default PO standard terms (optional module)
        $defaultStdTerm = $this->getDefaultPoStandardTerm();

        DB::transaction(function () use ($purchaseRfq, $groups, $company, $defaultStdTerm, &$createdOrders) {
            foreach ($groups as $rfqVendorId => $group) {
                $rfqVendor = $group['rfqVendor'];
                $items     = $group['items'];

                if (empty($items)) {
                    continue;
                }

                $vendorPartyId = $rfqVendor->vendor_party_id ?? $rfqVendor->vendor_id ?? null;
                $vendorParty = null;
                if (!empty($vendorPartyId) && class_exists(Party::class)) {
                    $vendorParty = Party::find($vendorPartyId);
                }

                $vendorBranch = null;
                $vendorBranchId = null;
                if ($vendorParty && Schema::hasTable('party_branches')) {
                    $vendorParty->loadMissing('branches');
                    $vendorBranch = $vendorParty->branches->firstWhere('is_primary', true) ?? $vendorParty->branches->first();
                    $vendorBranchId = $vendorBranch?->id;
                }

                $gstType = $this->resolveGstTypeForVendor($company, $vendorParty, $vendorBranch);

                $order = new PurchaseOrder();

                $orderData = [
                    'code'               => PurchaseOrder::generateNextCode(),
                    'project_id'         => $purchaseRfq->project_id,
                    'department_id'      => $purchaseRfq->department_id,
                    'vendor_party_id'    => $vendorPartyId,
                    'vendor_branch_id'   => $vendorBranchId,
                    'purchase_rfq_id'    => $purchaseRfq->id,
                    'purchase_indent_id' => $purchaseRfq->purchase_indent_id,
                    'po_date'            => now(),
                    'expected_delivery_date' => $purchaseRfq->due_date,
                    'payment_terms_days' => $rfqVendor->payment_terms_days ?? $purchaseRfq->payment_terms_days,
                    'delivery_terms_days'=> $rfqVendor->delivery_terms_days ?? $purchaseRfq->delivery_terms_days,
                    'freight_terms'      => $rfqVendor->freight_terms ?? $purchaseRfq->freight_terms,
                    'status'             => 'draft',
                    'remarks'            => $purchaseRfq->remarks,
                    'created_by'         => auth()->id(),
                    'total_amount'       => 0,
                ];

                // Attach default Standard Terms template to PO so print/pdf uses dynamic terms (no hardcoded fallback)
                if ($defaultStdTerm) {
                    if (Schema::hasColumn('purchase_orders', 'standard_term_id')) {
                        $orderData['standard_term_id'] = $defaultStdTerm->id;
                    }
                    if (Schema::hasColumn('purchase_orders', 'terms_text')) {
                        $orderData['terms_text'] = $defaultStdTerm->content;
                    }
                }

                $this->applyOrderData($order, $orderData);
                $order->save();

                $lineNo      = 1;
                $totalAmount = 0.0;

                foreach ($items as $rfqItem) {
                    // Prefer selected quote (if stored); else pick active quote for vendor
                    $quote = null;

                    if (!empty($rfqItem->selected_quote_id)) {
                        $quote = PurchaseRfqVendorQuote::where('id', $rfqItem->selected_quote_id)
                            ->where('purchase_rfq_item_id', $rfqItem->id)
                            ->first();
                    }

                    if (!$quote) {
                        $quote = PurchaseRfqVendorQuote::where('purchase_rfq_item_id', $rfqItem->id)
                            ->where('purchase_rfq_vendor_id', $rfqVendorId)
                            ->where('is_active', true)
                            ->orderByDesc('revision_no')
                            ->first();
                    }

                    if (!$quote) {
                        continue;
                    }

                    $qty = (float) ($rfqItem->quantity ?? 0);
                    $rate = (float) ($quote->rate ?? 0);
                    $discPct = (float) ($quote->discount_percent ?? 0);
                    $taxPct  = (float) ($quote->tax_percent ?? 0);

                    $gross = $qty * $rate;
                    $discAmt = $discPct > 0 ? ($gross * $discPct / 100) : 0;
                    $amount = round($gross - $discAmt, 2);
                    $taxAmount = round($amount * $taxPct / 100, 2);

                    [$gstAmounts, $gstPercents] = $this->gstSplit($taxAmount, $taxPct, $gstType);

                    $lineTotal = round($amount + $taxAmount, 2);

                    $poItem = new PurchaseOrderItem();

                    $poItemData = [
                        'purchase_order_id'       => $order->id,
                        'line_no'                 => $lineNo++,
                        'item_id'                 => $rfqItem->item_id,

                        // Brand requirement (carried from Indent → RFQ → PO)
                        'brand'                  => $rfqItem->brand ?? null,

                        // Traceability
                        'purchase_rfq_item_id'    => $rfqItem->id,
                        'purchase_rfq_vendor_id'  => $rfqVendorId,
                        'purchase_indent_item_id' => $rfqItem->purchase_indent_item_id,

                        // Geometry / qty
                        'grade'                   => $rfqItem->grade ?? null,
                        'thickness_mm'            => $rfqItem->thickness_mm ?? null,
                        'width_mm'                => $rfqItem->width_mm ?? null,
                        'length_mm'               => $rfqItem->length_mm ?? null,
                        'section_profile'         => $rfqItem->section_profile ?? null,
                        'weight_per_meter_kg'     => $rfqItem->weight_per_meter_kg ?? null,

                        'qty_pcs'                 => $rfqItem->qty_pcs ?? null,
                        'quantity'                => $qty,
                        'uom_id'                  => $rfqItem->uom_id ?? null,
                        'description'             => $rfqItem->description ?? null,

                        // Commercials
                        'rate'                    => $rate,
                        'discount_percent'        => $discPct,
                        'discount_amount'         => round($discAmt, 2),
                        'tax_percent'             => $taxPct,
                        'tax_rate'                => $taxPct,
                        'tax_amount'              => $taxAmount,

                        'amount'                  => $amount,
                        'basic_amount'            => $amount,
                        'net_amount'              => $lineTotal,
                        'total_amount'            => $lineTotal,

                        // GST split
                        'gst_type'                => $gstType,
                        'cgst_percent'            => $gstPercents['cgst'] ?? null,
                        'sgst_percent'            => $gstPercents['sgst'] ?? null,
                        'igst_percent'            => $gstPercents['igst'] ?? null,
                        'cgst_amount'             => $gstAmounts['cgst'] ?? 0,
                        'sgst_amount'             => $gstAmounts['sgst'] ?? 0,
                        'igst_amount'             => $gstAmounts['igst'] ?? 0,

                        // Default tolerance = 0; user can update in PO Edit
                        'grn_tolerance_percent'   => 0,
                    ];

                    $this->applyOrderItemData($poItem, $poItemData);
                    $poItem->save();

                    $totalAmount += $lineTotal;
                }

                $this->applyOrderData($order, [
                    'total_amount' => round($totalAmount, 2),
                ]);
                $order->save();

                $createdOrders[] = $order;
            }

            // Mark RFQ as PO generated
            $purchaseRfq->status = 'po_generated';
            $purchaseRfq->save();

            // Phase 2: update indent procurement status
            if (!empty($purchaseRfq->purchase_indent_id)) {
                app(PurchaseIndentProcurementService::class)
                    ->recalcIndent((int) $purchaseRfq->purchase_indent_id);
            }
        });

        if (count($createdOrders) === 1) {
            return redirect()
                ->route('purchase-orders.show', $createdOrders[0])
                ->with('success', 'Purchase Order generated successfully.');
        }

        // Multi-vendor award
        return redirect()
            ->route('purchase-rfqs.show', $purchaseRfq)
            ->with('success', 'Purchase Orders generated successfully for selected vendors.');
    }

    public function approve(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Only Draft Purchase Orders can be approved.');
        }

        // Use schema-safe assignment (approved_at column may not exist in some DBs)
        $this->applyOrderData($purchaseOrder, [
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $purchaseOrder->save();

        // Phase 2: If PO is linked to indent, recalc procurement totals
        if ($purchaseOrder->purchase_indent_id) {
            app(PurchaseIndentProcurementService::class)
                ->recalcIndent((int) $purchaseOrder->purchase_indent_id);
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase Order approved successfully.');
    }

    public function sendEmail(PurchaseOrder $purchaseOrder): RedirectResponse
{
    $purchaseOrder->load([
        'vendor.contacts',
        'vendor.primaryContact',
        'vendorBranch',
        'items.item',
        'items.uom',
        'project',
        'department',
    ]);

    $vendor = $purchaseOrder->vendor;

    if (! $vendor) {
        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('error', 'Vendor not found for this Purchase Order.');
    }

    // -----------------------------------------------------------------
    // Resolve best recipient email (contacts -> vendor primary_email)
    // Guard: sometimes contact email is filled with a name by mistake.
    // -----------------------------------------------------------------
    $primaryContact = $vendor->primaryContact()->first();

    /** @var array<int, array{email:?string,name:?string}> $candidates */
    $candidates = [];

    if ($primaryContact) {
        $candidates[] = ['email' => $primaryContact->email ?? null, 'name' => $primaryContact->name ?? null];
    }

    // Other contacts (if loaded)
    if ($vendor->relationLoaded('contacts') && $vendor->contacts) {
        foreach ($vendor->contacts as $c) {
            $candidates[] = ['email' => $c->email ?? null, 'name' => $c->name ?? null];
        }
    } else {
        // Fallback fetch first contact
        $first = $vendor->contacts()->first();
        if ($first) {
            $candidates[] = ['email' => $first->email ?? null, 'name' => $first->name ?? null];
        }
    }

    // Vendor header emails
    $candidates[] = ['email' => $vendor->primary_email ?? null, 'name' => $vendor->name ?? null];
    // Some installs also use `email` field
    $candidates[] = ['email' => $vendor->email ?? null, 'name' => $vendor->name ?? null];

    $email  = null;
    $toName = $vendor->name ?? null;

    foreach ($candidates as $cand) {
        $raw = $cand['email'] ?? null;
        $parsed = $this->extractEmailAddress($raw);

        if ($parsed && filter_var($parsed, FILTER_VALIDATE_EMAIL)) {
            $email = $parsed;
            $toName = $cand['name'] ?: $toName;
            break;
        }
    }

    if (! $email) {
        // Provide a friendlier error than the Symfony RFC 2822 exception.
        $debugList = collect($candidates)
            ->pluck('email')
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->take(5)
            ->implode(', ');

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('error', 'No valid vendor email found. Please update Vendor Contact Email / Primary Email. ' . ($debugList ? ('(Found: ' . $debugList . ')') : ''));
    }

    // Generate A4 PDF using the same view used by Download/Print
    $pdfBinary = \PDF::loadView('purchase_orders.pdf', [
        'order' => $purchaseOrder,
    ])->setPaper('a4', 'portrait')->output();

    // Create a temporary file for attachment (MailService expects file paths)
    $tmpBase = tempnam(sys_get_temp_dir(), 'po_');
    $pdfPath = $tmpBase . '.pdf';
    file_put_contents($pdfPath, $pdfBinary);

    // Data for mail template placeholders: {{po_code}}, {{vendor_name}}, etc.
    $dataForTemplate = [
    // PO
    'po_code'   => (string) ($purchaseOrder->code ?? ''),
    'po_no'     => (string) ($purchaseOrder->code ?? ''),
    'po_number' => (string) ($purchaseOrder->code ?? ''),
    'po_date'   => (string) (optional($purchaseOrder->po_date)->format('d-m-Y') ?? ''),
    'expected_delivery_date' => (string) (optional($purchaseOrder->expected_delivery_date)->format('d-m-Y') ?? ''),

    // Vendor
    'vendor_name'    => (string) ($vendor->name ?? ''),
    'supplier_name'  => (string) ($vendor->name ?? ''),
    'supplier_email' => (string) ($email ?? ''),

    // Context
    'project_code'    => (string) (optional($purchaseOrder->project)->code ?? ''),
    'project_name'    => (string) (optional($purchaseOrder->project)->name ?? ''),
    'department_name' => (string) (optional($purchaseOrder->department)->name ?? ''),

    // Terms summary
    'payment_terms_days'  => (string) ($purchaseOrder->payment_terms_days ?? ''),
    'delivery_terms_days' => (string) ($purchaseOrder->delivery_terms_days ?? ''),
    'freight_terms'       => (string) ($purchaseOrder->freight_terms ?? ''),

    // Amounts
    'total_amount' => number_format((float) ($purchaseOrder->total_amount ?? 0), 2),
    'grand_total'  => number_format((float) ($purchaseOrder->total_amount ?? 0), 2),

    'company_name' => (string) (config('app.name') ?? ''),
];

    try {
        // Use Mail Profile + Mail Template module (same approach used in RFQ / templates module)
        /** @var MailService $mailService */
        $mailService = app(MailService::class);

        if (! method_exists($mailService, 'sendTemplateWithAttachments')) {
            throw new \RuntimeException('MailService::sendTemplateWithAttachments not found. Please deploy latest MailService.');
        }

        $company = Company::where('is_default', true)->first();

        $mailService->sendTemplateWithAttachments(
            templateCode: 'purchase_po',
            toEmail:      $email,
            toName:       $toName ?: null,
            data:         $dataForTemplate,
            usage:        'purchaseRfq', // mail profile usage code (same as RFQ)
            companyId:    $company?->id,
            departmentId: $purchaseOrder->department_id,
            attachments: [
                [
                    'path' => $pdfPath,
                    'name' => ($purchaseOrder->code ?: ('PO-' . $purchaseOrder->id)) . '.pdf',
                    'mime' => 'application/pdf',
                ],
            ],
        );
    } catch (\Throwable $e) {
        Log::error('Failed to send PO email', [
            'purchase_order_id' => $purchaseOrder->id,
            'email'             => $email,
            'error'             => $e->getMessage(),
        ]);

        @unlink($pdfPath);
        @unlink($tmpBase);

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('error', 'Failed to send Purchase Order email. ' . $e->getMessage());
    }

    @unlink($pdfPath);
    @unlink($tmpBase);

    return redirect()
        ->route('purchase-orders.show', $purchaseOrder)
        ->with('success', 'Purchase Order emailed to vendor (' . $email . ').');
}

    public function print(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'project',
            'department',
            'vendor',
            'vendorBranch',
            'vendor.branches',
            'rfq',
            'indent',
            'items.item',
            'items.uom',
        ]);

        return view('purchase_orders.print', [
            'order' => $purchaseOrder,
        ]);
    }

    /**
     * Optional PDF download endpoint (if you add a route).
     */
    public function pdf(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'project',
            'department',
            'vendor',
            'vendorBranch',
            'vendor.branches',
            'rfq',
            'indent',
            'items.item',
            'items.uom',
        ]);

        $pdf = \PDF::loadView('purchase_orders.pdf', [
            'order' => $purchaseOrder,
        ])->setPaper('a4', 'portrait');

        return $pdf->download(($purchaseOrder->code ?: ('PO-' . $purchaseOrder->id)) . '.pdf');
    }

    /**
     * JSON items for GRN.
     * Includes:
     * - pending quantity
     * - received quantity
     * - tolerance percent & max allowed quantity
     */
    public function itemsForGrn(PurchaseOrder $purchaseOrder)
    {
        // Used by GRN (Material Receipt) create form to auto-load PO lines.
        // IMPORTANT: material_receipt_lines table stores qty_pcs + received_weight_kg (not "quantity").
        $purchaseOrder->load(['items.item', 'items.uom', 'items.indentItem']);

        $poItemIds = $purchaseOrder->items->pluck('id')->all();
        if (empty($poItemIds)) {
            return response()->json(['success' => true, 'items' => []]);
        }

        // Aggregate received totals per PO item from GRN lines
        $receivedAgg = DB::table('material_receipt_lines as mrl')
            ->selectRaw('mrl.purchase_order_item_id,
                        SUM(COALESCE(mrl.qty_pcs, 0)) as received_pcs,
                        SUM(COALESCE(mrl.received_weight_kg, 0)) as received_weight')
            ->whereNotNull('mrl.purchase_order_item_id')
            ->whereIn('mrl.purchase_order_item_id', $poItemIds)
            ->groupBy('mrl.purchase_order_item_id')
            ->get()
            ->keyBy('purchase_order_item_id');

        $items = $purchaseOrder->items->map(function (PurchaseOrderItem $line) use ($receivedAgg) {
            $poPcs = (float) ($line->qty_pcs ?? 0);
            $poQty = (float) ($line->quantity ?? 0);

            $recvRow = $receivedAgg->get($line->id);
            $receivedPcs = (float) ($recvRow->received_pcs ?? 0);
            $receivedWt  = (float) ($recvRow->received_weight ?? 0);

            $tolPct = (float) ($line->grn_tolerance_percent ?? 0);

            $maxAllowedPcs = $poPcs > 0 ? ($poPcs * (1 + ($tolPct / 100))) : 0;
            $maxAllowedQty = $poQty > 0 ? ($poQty * (1 + ($tolPct / 100))) : 0;

            $pendingPcs = $maxAllowedPcs > 0 ? max(0, $maxAllowedPcs - $receivedPcs) : 0;
            $pendingQty = $maxAllowedQty > 0 ? max(0, $maxAllowedQty - $receivedWt) : 0;

            // Density (for plate weight calc): prefer indent item density, else item master density
            $density = null;
            if ($line->relationLoaded('indentItem') && $line->indentItem) {
                $density = $line->indentItem->density_kg_per_m3 ?? null;
            }
            if ($density === null && $line->relationLoaded('item') && $line->item) {
                $density = $line->item->density ?? null;
            }

            return [
                'id'                  => $line->id,
                'item_id'             => $line->item_id,
                'item_name'           => optional($line->item)->name,

                // Brand preference: PO line brand, else indent item brand (legacy POs)
                'brand'               => $line->brand ?? optional($line->indentItem)->brand ?? null,

                // Might be null (PO table may not store this)
                'material_category'   => $line->material_category ?? null,

                'grade'               => $line->grade ?? null,
                'thickness_mm'        => $line->thickness_mm ?? null,
                'width_mm'            => $line->width_mm ?? null,
                'length_mm'           => $line->length_mm ?? null,
                'section_profile'     => $line->section_profile ?? null,

                // IMPORTANT keys expected by GRN create.blade.php JS
                'qty_pcs'             => $poPcs,
                'quantity'            => $poQty,

                'uom_id'              => $line->uom_id ?? optional($line->item)->uom_id,

                // Optional for weight calculation in UI
                'density_kg_per_m3'   => $density,
                'density'             => $density,

                // Receiving stats (for UI / validation helpers)
                'received_qty_pcs'    => round($receivedPcs, 3),
                'received_weight_kg'  => round($receivedWt, 3),
                'grn_tolerance_percent' => round($tolPct, 2),

                'max_allowed_qty_pcs' => round($maxAllowedPcs, 3),
                'max_allowed_weight_kg' => round($maxAllowedQty, 3),
                'qty_pending_pcs'     => round($pendingPcs, 3),
                'qty_pending_weight_kg' => round($pendingQty, 3),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'items'   => $items,
        ]);
    }



    /**
     * Cancel PO — blocks if GRN or Purchase Bill exists.
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status === 'cancelled') {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Purchase Order is already cancelled.');
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        // Block if GRN exists
        $hasGrn = DB::table('material_receipts')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->exists();

        if ($hasGrn) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Cannot cancel PO because GRN exists. Cancel/Reverse GRN first.');
        }

        // Block if Purchase Bill exists
        $hasBill = DB::table('purchase_bills')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->exists();

        if ($hasBill) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Cannot cancel PO because Purchase Bill exists. Cancel/Reverse Bill first.');
        }

        DB::transaction(function () use ($purchaseOrder, $request) {
            // Use schema-safe assignment (some DBs may not have cancelled_* columns yet)
            $this->applyOrderData($purchaseOrder, [
                'status'        => 'cancelled',
                'cancelled_at'  => now(),
                'cancelled_by'  => auth()->id(),
                'cancel_reason' => $request->input('reason'),
            ]);

            $purchaseOrder->save();

            // If this PO was generated from an RFQ, and there are no other active POs for that RFQ,
            // set RFQ back to 'sent' so it can be re-processed.
            if ($purchaseOrder->purchase_rfq_id) {
                $rfq = PurchaseRfq::find($purchaseOrder->purchase_rfq_id);
                if ($rfq) {
                    $hasActivePo = PurchaseOrder::where('purchase_rfq_id', $rfq->id)
                        ->whereNotIn('status', ['cancelled'])
                        ->exists();

                    if (!$hasActivePo && $rfq->status === 'po_generated') {
                        $rfq->status = 'sent';
                        $rfq->save();
                    }
                }
            }

            // Recalculate indent procurement totals
            if ($purchaseOrder->purchase_indent_id) {
                app(PurchaseIndentProcurementService::class)
                    ->recalcIndent((int) $purchaseOrder->purchase_indent_id);
            }
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase Order cancelled.');
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Keep controller compatible with slightly different DB schemas.
     */
    protected function filterByExistingColumns(string $table, array $data): array
    {
        try {
            if (!Schema::hasTable($table)) {
                return $data;
            }

            $columns = Schema::getColumnListing($table);

            return array_filter(
                $data,
                fn ($value, $key) => in_array($key, $columns, true),
                ARRAY_FILTER_USE_BOTH
            );
        } catch (\Throwable $e) {
            // If Schema introspection fails in some environment, don't block flow.
            return $data;
        }
    }

    protected function applyOrderData(PurchaseOrder $order, array $data): void
    {
        $data = $this->filterByExistingColumns('purchase_orders', $data);

        foreach ($data as $key => $value) {
            $order->{$key} = $value;
        }
    }

    protected function applyOrderItemData(PurchaseOrderItem $item, array $data): void
    {
        $data = $this->filterByExistingColumns('purchase_order_items', $data);

        foreach ($data as $key => $value) {
            $item->{$key} = $value;
        }
    }

    /**
     * Determine GST type by comparing company vs supplier GST state.
     * - Same state => cgst_sgst
     * - Different => igst
     */
    protected function resolveGstTypeForVendor(?Company $company, ?Party $vendor, ?PartyBranch $vendorBranch = null): string
    {
        // Default / fallback
        $default = 'cgst_sgst';

        if (! $company) {
            return $default;
        }

        // Prefer branch GST context if provided; fallback to party
        $companyStateCode = $this->gstStateCodeFromGstin($company->gst_number ?? null);

        $vendorStateCode = $vendorBranch?->gst_state_code ?: $this->gstStateCodeFromGstin($vendorBranch?->gstin);
        if (! $vendorStateCode) {
            $vendorStateCode = $vendor?->gst_state_code ?: $this->gstStateCodeFromGstin($vendor?->gstin);
        }

        if ($companyStateCode && $vendorStateCode) {
            return $companyStateCode === $vendorStateCode ? 'cgst_sgst' : 'igst';
        }

        // Fallback to state names
        $cState = strtolower(trim((string) ($company->state ?? '')));
        $vState = strtolower(trim((string) ($vendorBranch?->state ?: ($vendor?->state ?? ''))));

        if ($cState !== '' && $vState !== '') {
            return $cState === $vState ? 'cgst_sgst' : 'igst';
        }

        return $default;
    }



    protected function gstStateCodeFromGstin(?string $gstin): ?string
    {
        $gstin = strtoupper(trim((string) $gstin));
        if ($gstin === '') {
            return null;
        }

        // GSTIN starts with 2-digit state code
        $code = substr($gstin, 0, 2);
        if (preg_match('/^\d{2}$/', $code)) {
            return $code;
        }

        return null;
    }

    /**
     * Split GST amount into cgst/sgst/igst based on gstType.
     * Returns: [amounts, percents]
     */
    protected function gstSplit(float $taxAmount, float $taxPercent, string $gstType): array
    {
        $taxAmount = round($taxAmount, 2);
        $taxPercent = (float) $taxPercent;

        if ($taxAmount <= 0 || $taxPercent <= 0) {
            return [
                ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0],
                ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0],
            ];
        }

        if ($gstType === 'igst') {
            return [
                ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => $taxAmount],
                ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => round($taxPercent, 2)],
            ];
        }

        // Intra-state: prefer helper for rounding consistency
        try {
            $gst = GstHelper::split($taxAmount);
            $cgst = (float) ($gst['cgst'] ?? 0);
            $sgst = (float) ($gst['sgst'] ?? 0);

            // Percent split as half
            $half = round($taxPercent / 2, 2);

            return [
                ['cgst' => $cgst, 'sgst' => $sgst, 'igst' => 0.0],
                ['cgst' => $half, 'sgst' => $half, 'igst' => 0.0],
            ];
        } catch (\Throwable $e) {
            // Fallback split
            $cgst = round($taxAmount / 2, 2);
            $sgst = round($taxAmount - $cgst, 2);
            $half = round($taxPercent / 2, 2);

            return [
                ['cgst' => $cgst, 'sgst' => $sgst, 'igst' => 0.0],
                ['cgst' => $half, 'sgst' => $half, 'igst' => 0.0],
            ];
        }
    }

    /**
     * Extract a usable email address from a stored string.
     * Supports:
     * - "name <email@domain.com>"
     * - "email@domain.com"
     * - "email@domain.com, other@domain.com" (returns first valid)
     */
    protected function extractEmailAddress(?string $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Allow comma/semicolon separated list; pick first valid.
        $parts = preg_split('/[;,]+/', $raw) ?: [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') continue;

            // If in "Name <email>" format, extract inside brackets.
            if (preg_match('/<\s*([^>]+)\s*>/', $part, $m)) {
                $candidate = trim((string) ($m[1] ?? ''));
            } else {
                $candidate = $part;
            }

            // Strip surrounding quotes
            $candidate = trim($candidate, " \t\n\r\0\x0B\"'");

            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Get default PO StandardTerm (module purchase, sub_module po) if module exists.
     */
    protected function getDefaultPoStandardTerm(): ?object
    {
        if (!Schema::hasTable('standard_terms') || !class_exists(StandardTerm::class)) {
            return null;
        }

        return StandardTerm::query()
            ->where('module', 'purchase')
            ->where('sub_module', 'po')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();
    }
}


