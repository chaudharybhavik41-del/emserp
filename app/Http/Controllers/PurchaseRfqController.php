<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Item;
use App\Models\MailProfile;
use App\Models\MailTemplate;
use App\Models\Party;
use App\Models\Project;
use App\Models\PurchaseIndent;
use App\Models\PurchaseRfq;
use App\Models\PurchaseRfqActivity;
use App\Models\PurchaseRfqItem;
use App\Models\PurchaseRfqVendorQuote;
use App\Models\PurchaseOrder;
use App\Models\Uom;
use App\Services\PurchaseIndentProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PDF;

class PurchaseRfqController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:purchase.rfq.view')->only(['index', 'show']);
        $this->middleware('permission:purchase.rfq.create')->only(['create', 'store']);
        $this->middleware('permission:purchase.rfq.update')->only(['edit', 'update', 'editQuotes', 'updateQuotes']);
        $this->middleware('permission:purchase.rfq.send')->only(['sendEmails', 'sendRevisionEmails']);
        $this->middleware('permission:purchase.rfq.delete')->only(['destroy', 'cancel']);
    }

    // public function index(Request $request): View
    // {
    //     $q = trim((string) $request->get('q', ''));
    //     $status = trim((string) $request->get('status', ''));
    //     $projectId = (int) $request->get('project_id', 0);

    //     $query = PurchaseRfq::query()
    //         ->with(['project', 'department', 'indent'])
    //         ->orderByDesc('id');

    //     if ($q !== '') {
    //         $query->where(function ($qq) use ($q) {
    //             $qq->where('code', 'like', '%' . $q . '%')
    //                 ->orWhere('remarks', 'like', '%' . $q . '%');
    //         });
    //     }

    //     if ($status !== '') {
    //         $query->where('status', $status);
    //     }

    //     if ($projectId > 0) {
    //         $query->where('project_id', $projectId);
    //     }

    //     $rfqs = $query->paginate(25)->withQueryString();
    //     $projects = Project::query()
    //         ->orderBy('code')
    //         ->orderBy('name')
    //         ->get(['id', 'code', 'name']);

    //     $statusOptions = [
    //         'draft'        => 'Draft',
    //         'sent'         => 'Sent',
    //         'po_generated' => 'PO Generated',
    //         'closed'       => 'Closed',
    //         'cancelled'    => 'Cancelled',
    //     ];

    //     return view('purchase_rfqs.index', compact('rfqs', 'q', 'status', 'statusOptions', 'projects', 'projectId'));
    // }
public function index(Request $request)
{
    $query = PurchaseRfq::query()
        ->with(['project'])
        ->orderByDesc('id');

    // 🔍 RFQ No filter
    if ($request->filled('code')) {
        $query->where('code', 'like', '%' . $request->code . '%');
    }

    // 🔍 Remarks filter
    if ($request->filled('remarks')) {
        $query->where('remarks', 'like', '%' . $request->remarks . '%');
    }

    // 🔍 Status filter
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    // 🔍 Project filter
    if ($request->filled('project_id')) {
        $query->where('project_id', $request->project_id);
    }

    $rfqs = $query->paginate(25);

    if ($request->ajax()) {
        return view('purchase_rfqs.partials.table', compact('rfqs'))->render();
    }

    $projects = Project::orderBy('code')->get(['id','code','name']);

    $statusOptions = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'po_generated' => 'PO Generated',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ];

    return view('purchase_rfqs.index',
        compact('rfqs','projects','statusOptions')
    );
}


    public function create(Request $request): View
    {
        $indentId = $request->get('purchase_indent_id');

        $preloadedIndent = null;
        $preloadedItems  = collect();

        $indents = PurchaseIndent::query()
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('procurement_status')
                    ->orWhereIn('procurement_status', ['open', 'rfq_created', 'partially_ordered']);
            })
            ->with('project')
            ->orderByDesc('id')
            ->get();

        // If indent preselected, preload its items
        if (!empty($indentId)) {
            $preloadedIndent = $indents->firstWhere('id', (int) $indentId);
            if ($preloadedIndent) {
                $preloadedIndent->load('items.item.uom');
                $preloadedItems = $preloadedIndent->items;
            }
        }

        $projects = Project::orderBy('code')->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        $items = Item::with('uom')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $uoms = Uom::orderBy('code')->get();

        $vendors = Party::where('is_supplier', 1)
            ->where('is_active', 1)
            ->with('contacts')
            ->orderBy('name')
            ->get();

        /**
         * ✅ Phase 2 Bug-1 fix:
         * items.brands is TEXT JSON in DB, so $item->brands can be a JSON string.
         * Normalize to array before sending to JS.
         */
        $itemMeta = $items->map(function (Item $item) {
            return [
                'id'       => $item->id,
                'name'     => $item->name,
                'code'     => $item->code ?? '',
                'grade'    => $item->grade ?? '',
                'uom_id'   => $item->uom_id,
                'uom_name' => optional($item->uom)->name,
                'brands'   => $this->normalizeBrands($item->brands),
            ];
        });

        return view('purchase_rfqs.create', [
            'indents'          => $indents,
            'preloadedIndent'  => $preloadedIndent,
            'preloadedItems'   => $preloadedItems,
            'projects'         => $projects,
            'departments'      => $departments,
            'items'            => $items,
            'uoms'             => $uoms,
            'vendors'          => $vendors,
            'itemMetaJson'     => $itemMeta->toJson(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id'          => ['nullable', 'integer', 'exists:projects,id'],
            'department_id'       => [
                'nullable', // ✅ FIX: allow empty when indent selected
                Rule::requiredIf(fn () => empty($request->input('purchase_indent_id'))),
                'integer',
                'exists:departments,id'
            ],
            'purchase_indent_id'  => ['nullable', 'integer', 'exists:purchase_indents,id'],

            'rfq_date'            => ['required', 'date'],
            'due_date'            => ['nullable', 'date', 'after_or_equal:rfq_date'],
            'payment_terms_days'  => ['nullable', 'integer', 'min:0'],
            'delivery_terms_days' => ['nullable', 'integer', 'min:0'],
            'freight_terms'       => ['nullable', 'string', 'max:255'],
            'remarks'             => ['nullable', 'string', 'max:1000'],

            'items'                   => ['required', 'array', 'min:1'],
            'items.*.item_id'         => ['required', 'integer', 'exists:items,id'],
            'items.*.uom_id'          => ['nullable', 'integer', 'exists:uoms,id'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.001'],
            'items.*.allocated_indent_qty' => ['nullable', 'numeric', 'min:0.001'],
            'items.*.purchase_indent_item_id' => ['nullable', 'integer'],
            'items.*.grade'           => ['nullable', 'string', 'max:100'],
            'items.*.brand'           => ['nullable', 'string', 'max:100'],
            'items.*.length_mm'       => ['nullable', 'numeric', 'min:0'],
            'items.*.width_mm'        => ['nullable', 'numeric', 'min:0'],
            'items.*.thickness_mm'    => ['nullable', 'numeric', 'min:0'],
            'items.*.qty_pcs'         => ['nullable', 'numeric', 'min:0'],

            'vendors'                 => ['required', 'array', 'min:1'],
            'vendors.*.party_id'      => ['required', 'integer', 'exists:parties,id'],
            'vendors.*.email'         => ['nullable', 'email', 'max:255'],
            'vendors.*.contact_name'  => ['nullable', 'string', 'max:150'],
            'vendors.*.contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        // ✅ Phase 2 Bug-2 fix: validate brand vs item.brands (if configured)
        $this->validateLineBrands($validated['items'] ?? []);

        DB::beginTransaction();

        try {
            $indent = null;

            if (!empty($validated['purchase_indent_id'])) {
                $indent = PurchaseIndent::with('items')
                    ->where('status', 'approved')
                    ->findOrFail((int) $validated['purchase_indent_id']);
            }

            $rfq = new PurchaseRfq();
            $rfq->code               = $this->generateRfqCode();
            $rfq->project_id         = $validated['project_id'] ?? ($indent?->project_id);
            $rfq->department_id      = $validated['department_id'] ?? ($indent?->department_id);
            $rfq->purchase_indent_id = $indent?->id;
            $rfq->rfq_date           = Carbon::parse($validated['rfq_date'])->toDateString();
            $rfq->due_date           = !empty($validated['due_date']) ? Carbon::parse($validated['due_date'])->toDateString() : null;
            $rfq->payment_terms_days = $validated['payment_terms_days'] ?? null;
            $rfq->delivery_terms_days = $validated['delivery_terms_days'] ?? null;
            $rfq->freight_terms      = $validated['freight_terms'] ?? null;
            $rfq->remarks            = $validated['remarks'] ?? null;
            $rfq->status             = 'draft';
            $rfq->created_by         = auth()->id();
            $rfq->save();

            // Build indent remaining map (if indent linked)
            $remainingByIndentItem = [];
            if ($indent) {
                $indentItemIds = $indent->items->pluck('id')->all();

                $rfqAlloc = PurchaseRfqItem::query()
                    ->whereIn('purchase_indent_item_id', $indentItemIds)
                    ->whereHas('rfq', fn ($q) => $q->whereNotIn('status', ['cancelled', 'closed', 'po_generated']))
                    ->selectRaw('purchase_indent_item_id, COALESCE(SUM(quantity),0) as qty')
                    ->groupBy('purchase_indent_item_id')
                    ->pluck('qty', 'purchase_indent_item_id')
                    ->toArray();

                $poQty = DB::table('purchase_order_items as poi')
                    ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                    ->whereIn('poi.purchase_indent_item_id', $indentItemIds)
                    ->whereNotIn('po.status', ['cancelled'])
                    ->selectRaw('poi.purchase_indent_item_id, COALESCE(SUM(poi.quantity),0) as qty')
                    ->groupBy('poi.purchase_indent_item_id')
                    ->pluck('qty', 'poi.purchase_indent_item_id')
                    ->toArray();

                foreach ($indent->items as $line) {
                    $requested = (float) ($line->order_qty ?? 0);
                    $allocated = (float) ($rfqAlloc[$line->id] ?? 0);
                    $ordered   = (float) ($poQty[$line->id] ?? 0);

                    $rem = $requested - $allocated - $ordered;
                    if ($rem < 0) $rem = 0;

                    $remainingByIndentItem[$line->id] = $rem;
                }
            }

            foreach ($validated['items'] as $idx => $row) {
                $item = Item::findOrFail((int) $row['item_id']);

                $line = new PurchaseRfqItem();
                $line->purchase_rfq_id = $rfq->id;
                $line->line_no = $idx + 1;
                $line->item_id = $item->id;

                if ($indent) {
                    $indentItemId = (int) ($row['purchase_indent_item_id'] ?? 0);
                    if (!$indentItemId) {
                        throw ValidationException::withMessages([
                            "items.$idx.purchase_indent_item_id" => 'Indent line is required when RFQ is linked to an indent.',
                        ]);
                    }

                    $belongs = $indent->items->firstWhere('id', $indentItemId);
                    if (!$belongs) {
                        throw ValidationException::withMessages([
                            "items.$idx.purchase_indent_item_id" => 'Selected indent line does not belong to selected indent.',
                        ]);
                    }

                    $allocQty = (float) ($row['allocated_indent_qty'] ?? $row['quantity']);
                    $remaining = (float) ($remainingByIndentItem[$indentItemId] ?? 0);

                    if ($allocQty <= 0) {
                        throw ValidationException::withMessages([
                            "items.$idx.allocated_indent_qty" => 'Allocated quantity must be > 0.',
                        ]);
                    }

                    if ($allocQty - $remaining > 0.0001) {
                        throw ValidationException::withMessages([
                            "items.$idx.allocated_indent_qty" => 'Allocated quantity exceeds remaining indent quantity.',
                        ]);
                    }

                    $line->purchase_indent_item_id = $indentItemId;
                    $line->allocated_indent_qty = $allocQty;
                    $line->quantity = $allocQty;
                } else {
                    $line->purchase_indent_item_id = null;
                    $line->allocated_indent_qty = null;
                    $line->quantity = (float) $row['quantity'];
                }

                $line->uom_id = $row['uom_id'] ?? $item->uom_id;
                $line->grade  = $row['grade'] ?? ($item->grade ?? null);
                $line->brand  = $row['brand'] ?? null;

                $line->length_mm    = $row['length_mm'] ?? null;
                $line->width_mm     = $row['width_mm'] ?? null;
                $line->thickness_mm = $row['thickness_mm'] ?? null;
                $line->qty_pcs      = $row['qty_pcs'] ?? null;

                $line->save();
            }

            foreach ($validated['vendors'] as $vIdx => $vRow) {
                $partyId = (int) $vRow['party_id'];
                $party = Party::findOrFail($partyId);

                $rfq->vendors()->create([
                    'vendor_party_id' => $party->id,
                    'email'           => $vRow['email'] ?? null,
                    'contact_name'    => $vRow['contact_name'] ?? null,
                    'contact_phone'   => $vRow['contact_phone'] ?? null,
                    'status'          => 'invited',
                ]);
            }

            if ($indent) {
                if (!in_array($indent->procurement_status, ['cancelled', 'closed', 'ordered'], true)) {
                    $indent->procurement_status = 'rfq_created';
                    $indent->save();
                }

                app(PurchaseIndentProcurementService::class)->recalcIndent((int) $indent->id);
            }

            $this->logActivity($rfq->id, 'created', 'RFQ created.');

            DB::commit();

            return redirect()->route('purchase-rfqs.show', $rfq)
                ->with('success', 'RFQ created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('RFQ create failed', ['error' => $e->getMessage()]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create RFQ. ' . $e->getMessage());
        }
    }

    public function show(PurchaseRfq $purchaseRfq): View
    {
        $purchaseRfq->load([
            'project',
            'department',
            'indent',
            'items.item.uom',
            'vendors.vendor',
            'items.vendorQuotes',
        ]);

        // ✅ Activity log (latest first)
        $activities = collect();

        if (class_exists(PurchaseRfqActivity::class) && Schema::hasTable('purchase_rfq_activities')) {
            $q = PurchaseRfqActivity::query()
                ->where('purchase_rfq_id', $purchaseRfq->id)
                ->orderByDesc('id');

            // Prefer user() relation if available
            if (method_exists(PurchaseRfqActivity::class, 'user')) {
                $q->with('user');
            } elseif (method_exists(PurchaseRfqActivity::class, 'creator')) {
                $q->with('creator');
            }

            $activities = $q->limit(200)->get();
        }

        return view('purchase_rfqs.show', [
            'rfq'        => $purchaseRfq,
            'activities' => $activities,
        ]);
    }

    public function edit(PurchaseRfq $purchaseRfq): View
    {
        if ($purchaseRfq->status === 'po_generated') {
            return view('purchase_rfqs.edit', [
                'rfq' => $purchaseRfq,
                'readonly' => true,
                'error' => 'RFQ already converted to PO.',
            ]);
        }

        $purchaseRfq->load(['items', 'vendors']);

        $projects = Project::orderBy('code')->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        $items = Item::with('uom')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $uoms = Uom::orderBy('code')->get();

        $vendors = Party::where('is_supplier', 1)
            ->where('is_active', 1)
            ->with('contacts')
            ->orderBy('name')
            ->get();

        // ✅ Phase 2 Bug-1 fix here too
        $itemMeta = $items->map(function (Item $item) {
            return [
                'id'       => $item->id,
                'name'     => $item->name,
                'code'     => $item->code ?? '',
                'grade'    => $item->grade ?? '',
                'uom_id'   => $item->uom_id,
                'uom_name' => optional($item->uom)->name,
                'brands'   => $this->normalizeBrands($item->brands),
            ];
        });

        return view('purchase_rfqs.edit', [
            'rfq'         => $purchaseRfq,
            'projects'    => $projects,
            'departments' => $departments,
            'items'       => $items,
            'uoms'        => $uoms,
            'vendors'     => $vendors,
            'itemMetaJson'=> $itemMeta->toJson(),
            'readonly'    => false,
        ]);
    }

    public function update(Request $request, PurchaseRfq $purchaseRfq): RedirectResponse
    {
        if ($purchaseRfq->status === 'po_generated') {
            return back()->with('error', 'RFQ already converted to PO.');
        }

        $validated = $request->validate([
            'project_id'          => ['nullable', 'integer', 'exists:projects,id'],
            'department_id'       => ['nullable', 'integer', 'exists:departments,id'],
            'rfq_date'            => ['required', 'date'],
            'due_date'            => ['nullable', 'date', 'after_or_equal:rfq_date'],
            'payment_terms_days'  => ['nullable', 'integer', 'min:0'],
            'delivery_terms_days' => ['nullable', 'integer', 'min:0'],
            'freight_terms'       => ['nullable', 'string', 'max:255'],
            'remarks'             => ['nullable', 'string', 'max:1000'],

            'items'                   => ['required', 'array', 'min:1'],
            'items.*.id'              => ['nullable', 'integer', 'exists:purchase_rfq_items,id'],
            'items.*.item_id'         => ['required', 'integer', 'exists:items,id'],
            'items.*.uom_id'          => ['nullable', 'integer', 'exists:uoms,id'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.001'],
            'items.*.grade'           => ['nullable', 'string', 'max:100'],
            'items.*.brand'           => ['nullable', 'string', 'max:100'],
            'items.*.length_mm'       => ['nullable', 'numeric', 'min:0'],
            'items.*.width_mm'        => ['nullable', 'numeric', 'min:0'],
            'items.*.thickness_mm'    => ['nullable', 'numeric', 'min:0'],
            'items.*.qty_pcs'         => ['nullable', 'numeric', 'min:0'],

            'vendors'                 => ['required', 'array', 'min:1'],
            'vendors.*.id'            => ['nullable', 'integer'],
            'vendors.*.party_id'      => ['required', 'integer', 'exists:parties,id'],
            'vendors.*.email'         => ['nullable', 'email', 'max:255'],
            'vendors.*.contact_name'  => ['nullable', 'string', 'max:150'],
            'vendors.*.contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        // ✅ Phase 2 Bug-2 fix in update as well
        $this->validateLineBrands($validated['items'] ?? []);

        DB::beginTransaction();

        try {
            $purchaseRfq->project_id          = $validated['project_id'] ?? $purchaseRfq->project_id;
            $purchaseRfq->department_id       = $validated['department_id'] ?? $purchaseRfq->department_id;
            $purchaseRfq->rfq_date            = Carbon::parse($validated['rfq_date'])->toDateString();
            $purchaseRfq->due_date            = !empty($validated['due_date']) ? Carbon::parse($validated['due_date'])->toDateString() : null;
            $purchaseRfq->payment_terms_days  = $validated['payment_terms_days'] ?? null;
            $purchaseRfq->delivery_terms_days = $validated['delivery_terms_days'] ?? null;
            $purchaseRfq->freight_terms       = $validated['freight_terms'] ?? null;
            $purchaseRfq->remarks             = $validated['remarks'] ?? null;
            $purchaseRfq->save();

            $purchaseRfq->load(['items', 'vendors']);

            $existingItems = $purchaseRfq->items->keyBy('id');
            $keptItemIds = [];

            foreach ($validated['items'] as $idx => $row) {
                $item = Item::findOrFail((int) $row['item_id']);

                $line = null;
                if (!empty($row['id'])) {
                    $line = $existingItems->get((int) $row['id']);
                }
                if (!$line) {
                    $line = new PurchaseRfqItem();
                    $line->purchase_rfq_id = $purchaseRfq->id;
                }

                $line->line_no = $idx + 1;
                $line->item_id = $item->id;
                $line->uom_id  = $row['uom_id'] ?? $item->uom_id;
                $line->quantity = (float) $row['quantity'];
                $line->grade = $row['grade'] ?? ($item->grade ?? null);
                $line->brand = $row['brand'] ?? null;

                $line->length_mm    = $row['length_mm'] ?? null;
                $line->width_mm     = $row['width_mm'] ?? null;
                $line->thickness_mm = $row['thickness_mm'] ?? null;
                $line->qty_pcs      = $row['qty_pcs'] ?? null;

                $line->save();
                $keptItemIds[] = $line->id;
            }

            // Remove deleted items
            foreach ($existingItems as $id => $oldLine) {
                if (!in_array($id, $keptItemIds, true)) {
                    $oldLine->delete();
                }
            }

            // Vendors update (incremental; preserves vendor IDs so quote history stays linked)
            $existingVendorsByParty = $purchaseRfq->vendors()->withTrashed()->get()->keyBy('vendor_party_id');
            $keepPartyIds = [];

            foreach ($validated['vendors'] as $vRow) {
                $party = Party::findOrFail((int) $vRow['party_id']);
                $partyId = (int) $party->id;

                $keepPartyIds[] = $partyId;

                $rfqVendor = $existingVendorsByParty->get($partyId);

                if ($rfqVendor) {
                    // Restore if previously deleted
                    if (method_exists($rfqVendor, 'trashed') && $rfqVendor->trashed()) {
                        $rfqVendor->restore();
                    }

                    $rfqVendor->email = $vRow['email'] ?? $rfqVendor->email;
                    $rfqVendor->contact_name = $vRow['contact_name'] ?? $rfqVendor->contact_name;
                    $rfqVendor->contact_phone = $vRow['contact_phone'] ?? $rfqVendor->contact_phone;

                    // If vendor was removed earlier, reactivate
                    if (in_array((string) $rfqVendor->status, ['withdrawn', 'cancelled'], true)) {
                        $rfqVendor->status = 'invited';
                    }

                    if (Schema::hasColumn('purchase_rfq_vendors', 'cancelled_at')) {
                        $rfqVendor->cancelled_at = null;
                    }
                    if (Schema::hasColumn('purchase_rfq_vendors', 'cancelled_by')) {
                        $rfqVendor->cancelled_by = null;
                    }
                    if (Schema::hasColumn('purchase_rfq_vendors', 'cancel_reason')) {
                        $rfqVendor->cancel_reason = null;
                    }

                    $rfqVendor->save();
                } else {
                    $purchaseRfq->vendors()->create([
                        'vendor_party_id' => $partyId,
                        'email'           => $vRow['email'] ?? null,
                        'contact_name'    => $vRow['contact_name'] ?? null,
                        'contact_phone'   => $vRow['contact_phone'] ?? null,
                        'status'          => 'invited',
                    ]);
                }
            }

            // Vendors removed from the UI selection:
            // - If vendor already has quotes OR RFQ not in draft -> keep record, mark withdrawn (do NOT delete to preserve history)
            // - Else soft-delete
            foreach ($existingVendorsByParty as $partyId => $rfqVendor) {
                if (in_array((int) $partyId, $keepPartyIds, true)) {
                    continue;
                }

                $hasQuotes = PurchaseRfqVendorQuote::query()
                    ->where('purchase_rfq_vendor_id', $rfqVendor->id)
                    ->exists();

                if ($hasQuotes || $purchaseRfq->status !== 'draft') {
                    $rfqVendor->status = 'withdrawn';

                    if (Schema::hasColumn('purchase_rfq_vendors', 'cancelled_at')) {
                        $rfqVendor->cancelled_at = now();
                    }
                    if (Schema::hasColumn('purchase_rfq_vendors', 'cancelled_by')) {
                        $rfqVendor->cancelled_by = auth()->id();
                    }
                    if (Schema::hasColumn('purchase_rfq_vendors', 'cancel_reason')) {
                        $rfqVendor->cancel_reason = 'Removed from RFQ vendor list';
                    }

                    $rfqVendor->save();
                } else {
                    $rfqVendor->delete();
                }
            }

            $this->logActivity($purchaseRfq->id, 'updated', 'RFQ updated.');

            DB::commit();

            return redirect()->route('purchase-rfqs.show', $purchaseRfq)
                ->with('success', 'RFQ updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('RFQ update failed', ['error' => $e->getMessage()]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update RFQ. ' . $e->getMessage());
        }
    }

    public function editQuotes(PurchaseRfq $purchaseRfq): View
	{
    $purchaseRfq->load(['vendors.vendor', 'items.item.uom', 'items.vendorQuotes']);

    // Build quoteMatrix + vendorTotals + revisionHistory for the view
    $quoteMatrix = [];
    $revisionHistory = [];
    $vendorTotals = [];

    foreach ($purchaseRfq->vendors as $rfqVendor) {
        $vendorTotals[$rfqVendor->id] = ['subtotal' => 0.0, 'tax' => 0.0, 'total' => 0.0];
    }

    foreach ($purchaseRfq->items as $rfqItem) {
        $quoteMatrix[$rfqItem->id] = [];
        $revisionHistory[$rfqItem->id] = [];

        foreach ($purchaseRfq->vendors as $rfqVendor) {
            $revisionHistory[$rfqItem->id][$rfqVendor->id] = [];
        }

        foreach ($rfqItem->vendorQuotes as $q) {
            // History
            $revisionHistory[$rfqItem->id][$q->purchase_rfq_vendor_id][] = $q;

            if ((bool) $q->is_active) {
                $quoteMatrix[$rfqItem->id][$q->purchase_rfq_vendor_id] = [
                    'id'               => $q->id,
                    'rate'             => $q->rate,
                    'discount_percent' => $q->discount_percent,
                    'tax_percent'      => $q->tax_percent,
                    'delivery_days'    => $q->delivery_days,
                    'remarks'          => $q->remarks,
                    'revision_no'      => $q->revision_no,
                ];

                // Vendor totals
                $qty  = (float) ($rfqItem->quantity ?? 0);
                $rate = (float) ($q->rate ?? 0);
                $disc = (float) ($q->discount_percent ?? 0);
                $tax  = (float) ($q->tax_percent ?? 0);

                if ($qty > 0 && $rate > 0) {
                    $basic = $qty * $rate;
                    $basicAfterDisc = $basic - ($basic * ($disc / 100.0));
                    $taxAmt = $basicAfterDisc * ($tax / 100.0);
                    $total = $basicAfterDisc + $taxAmt;

                    $vendorTotals[$q->purchase_rfq_vendor_id]['subtotal'] += $basicAfterDisc;
                    $vendorTotals[$q->purchase_rfq_vendor_id]['tax']      += $taxAmt;
                    $vendorTotals[$q->purchase_rfq_vendor_id]['total']    += $total;
                }
            }
        }
    }

    // Sort revision history descending by revision
    foreach ($revisionHistory as $itemId => $byVendor) {
        foreach ($byVendor as $vendorId => $rows) {
            usort($rows, fn($a, $b) => ((int)($b->revision_no ?? 0)) <=> ((int)($a->revision_no ?? 0)));
            $revisionHistory[$itemId][$vendorId] = $rows;
        }
    }

    return view('purchase_rfqs.quotes', [
        'rfq'             => $purchaseRfq,
        'quoteMatrix'     => $quoteMatrix,
        'vendorTotals'    => $vendorTotals,
        'revisionHistory' => $revisionHistory,
        'viewVersion'     => 'v2',
    ]);
	}


    public function updateQuotes(Request $request, PurchaseRfq $purchaseRfq): RedirectResponse
{
    if ($purchaseRfq->status === 'po_generated') {
        return back()->with('error', 'RFQ already converted to PO.');
    }

    // Load vendor/item names for audit logging (no impact on existing logic)
    $purchaseRfq->load(['vendors.vendor', 'items.item']);

    $data = $request->validate([
        'quotes'       => ['required', 'array'],
        'vendor_terms' => ['nullable', 'array'],
        'l1'           => ['nullable', 'array'], // l1[item_id] = rfq_vendor_id
        'auto_l1'      => ['nullable'],
        'save_only'    => ['nullable'],
    ]);

    DB::beginTransaction();

    try {
        // 1) Save vendor commercial terms (only if those columns exist in rfq vendors table)
        if (!empty($data['vendor_terms']) && is_array($data['vendor_terms'])) {
            $hasPay = Schema::hasColumn('purchase_rfq_vendors', 'payment_terms_days');
            $hasDel = Schema::hasColumn('purchase_rfq_vendors', 'delivery_terms_days');
            $hasFre = Schema::hasColumn('purchase_rfq_vendors', 'freight_terms');

            foreach ($purchaseRfq->vendors as $rfqVendor) {
                $t = $data['vendor_terms'][$rfqVendor->id] ?? null;
                if (!$t) continue;

                if ($hasPay && array_key_exists('payment_terms_days', $t)) {
                    $rfqVendor->payment_terms_days = (int) $t['payment_terms_days'];
                }
                if ($hasDel && array_key_exists('delivery_terms_days', $t)) {
                    $rfqVendor->delivery_terms_days = (int) $t['delivery_terms_days'];
                }
                if ($hasFre && array_key_exists('freight_terms', $t)) {
                    $rfqVendor->freight_terms = trim((string) $t['freight_terms']);
                }

                if ($hasPay || $hasDel || $hasFre) {
                    $rfqVendor->save();
                }
            }
        }

        /**
         * 2) Save quotes with revisions
         *
         * Enhancement:
         * - Create a new revision ONLY when values actually changed.
         * - When an internal user modifies a vendor quote, write an RFQ activity log entry.
         *
         * This prevents revision spam when user only saves L1/terms.
         */
        $createdQuoteIds = []; // [item_id][vendor_id] => quote_id

        $itemIds = $purchaseRfq->items->pluck('id')->all();
        $vendorIds = $purchaseRfq->vendors->pluck('id')->all();

        $activeQuoteMap = []; // [item_id][vendor_id] => PurchaseRfqVendorQuote

        if (!empty($itemIds) && !empty($vendorIds)) {
            $activeQuotes = PurchaseRfqVendorQuote::query()
                ->whereIn('purchase_rfq_item_id', $itemIds)
                ->whereIn('purchase_rfq_vendor_id', $vendorIds)
                ->where('is_active', true)
                ->get();

            foreach ($activeQuotes as $q) {
                $activeQuoteMap[(int) $q->purchase_rfq_item_id][(int) $q->purchase_rfq_vendor_id] = $q;
            }
        }

        $hasRevisedAt = Schema::hasColumn('purchase_rfq_vendor_quotes', 'revised_at');
        $hasRevisedBy = Schema::hasColumn('purchase_rfq_vendor_quotes', 'revised_by');

        $fmt2 = function ($v): string {
            return number_format((float) $v, 2, '.', '');
        };

        foreach ($purchaseRfq->items as $item) {
            $itemQuotes = $data['quotes'][$item->id] ?? [];

            foreach ($purchaseRfq->vendors as $vendorRow) {
                $vendorQuoteData = $itemQuotes[$vendorRow->id] ?? null;
                if (!$vendorQuoteData) continue;

                $rateRaw = $vendorQuoteData['rate'] ?? null;
                if ($rateRaw === null || trim((string) $rateRaw) === '') {
                    continue;
                }

                $rateNew = (float) $rateRaw;
                $discNew = (float) ($vendorQuoteData['discount_percent'] ?? 0);
                $taxNew  = (float) ($vendorQuoteData['tax_percent'] ?? 0);

                $delNew = null;
                if (array_key_exists('delivery_days', $vendorQuoteData) && trim((string) $vendorQuoteData['delivery_days']) !== '') {
                    $delNew = (int) $vendorQuoteData['delivery_days'];
                }

                $remarksNew = null;
                if (array_key_exists('remarks', $vendorQuoteData)) {
                    $r = trim((string) $vendorQuoteData['remarks']);
                    $remarksNew = $r === '' ? null : $r;
                }

                /** @var PurchaseRfqVendorQuote|null $existing */
                $existing = $activeQuoteMap[(int) $item->id][(int) $vendorRow->id] ?? null;

                $changes = [];

                if ($existing) {
                    $oldRate = (float) ($existing->rate ?? 0);
                    if (abs($oldRate - $rateNew) > 0.0001) {
                        $changes['rate'] = ['from' => $fmt2($oldRate), 'to' => $fmt2($rateNew)];
                    }

                    $oldDisc = (float) ($existing->discount_percent ?? 0);
                    if (abs($oldDisc - $discNew) > 0.0001) {
                        $changes['discount_percent'] = ['from' => $fmt2($oldDisc), 'to' => $fmt2($discNew)];
                    }

                    $oldTax = (float) ($existing->tax_percent ?? 0);
                    if (abs($oldTax - $taxNew) > 0.0001) {
                        $changes['tax_percent'] = ['from' => $fmt2($oldTax), 'to' => $fmt2($taxNew)];
                    }

                    $oldDel = $existing->delivery_days !== null ? (int) $existing->delivery_days : null;
                    if ($oldDel !== $delNew) {
                        $changes['delivery_days'] = ['from' => $oldDel, 'to' => $delNew];
                    }

                    $oldRemarks = $existing->remarks !== null ? trim((string) $existing->remarks) : null;
                    if ($oldRemarks === '') $oldRemarks = null;

                    if ($oldRemarks !== $remarksNew) {
                        $changes['remarks'] = ['from' => $oldRemarks, 'to' => $remarksNew];
                    }

                    // If nothing changed, do NOT create a new revision
                    if (empty($changes)) {
                        $createdQuoteIds[$item->id][$vendorRow->id] = $existing->id;
                        continue;
                    }
                }

                // Deactivate old active quote only when creating a new revision
                if ($existing) {
                    $existing->is_active = false;
                    $existing->save();
                }

                // Revision number: existing+1 else max+1
                $revisionNo = 1;
                if ($existing) {
                    $revisionNo = ((int) $existing->revision_no) + 1;
                } else {
                    $maxRev = (int) PurchaseRfqVendorQuote::where('purchase_rfq_item_id', $item->id)
                        ->where('purchase_rfq_vendor_id', $vendorRow->id)
                        ->max('revision_no');
                    $revisionNo = $maxRev + 1;
                    if ($revisionNo < 1) $revisionNo = 1;
                }

                $createPayload = [
                    'purchase_rfq_item_id'   => $item->id,
                    'purchase_rfq_vendor_id' => $vendorRow->id,
                    'revision_no'            => $revisionNo,
                    'is_active'              => true,
                    'rate'                   => $rateNew,
                    'discount_percent'       => $discNew,
                    'tax_percent'            => $taxNew,
                    'delivery_days'          => $delNew,
                    'remarks'                => $remarksNew,
                ];

                // Mark internal edit (vendor portal will keep revised_by null)
                if ($hasRevisedAt) {
                    $createPayload['revised_at'] = now();
                }
                if ($hasRevisedBy) {
                    $createPayload['revised_by'] = auth()->id();
                }

                $new = PurchaseRfqVendorQuote::create($createPayload);

                $createdQuoteIds[$item->id][$vendorRow->id] = $new->id;
                $activeQuoteMap[(int) $item->id][(int) $vendorRow->id] = $new;

                // ✅ Audit log when internal user modifies an existing quote
                if ($existing) {
                    $vendorName = $vendorRow->vendor?->name ?? ('Vendor #' . $vendorRow->id);
                    $itemName   = $item->item?->name ?? ('Item #' . $item->id);

                    $parts = [];
                    if (isset($changes['rate'])) {
                        $parts[] = 'Rate ' . $changes['rate']['from'] . ' → ' . $changes['rate']['to'];
                    }
                    if (isset($changes['discount_percent'])) {
                        $parts[] = 'Disc ' . $changes['discount_percent']['from'] . ' → ' . $changes['discount_percent']['to'];
                    }
                    if (isset($changes['tax_percent'])) {
                        $parts[] = 'Tax ' . $changes['tax_percent']['from'] . ' → ' . $changes['tax_percent']['to'];
                    }
                    if (isset($changes['delivery_days'])) {
                        $from = $changes['delivery_days']['from'] === null ? '-' : (string) $changes['delivery_days']['from'];
                        $to   = $changes['delivery_days']['to'] === null ? '-' : (string) $changes['delivery_days']['to'];
                        $parts[] = 'Delivery ' . $from . ' → ' . $to;
                    }
                    if (isset($changes['remarks'])) {
                        $parts[] = 'Remarks updated';
                    }

                    $msg = 'Quote modified by internal user: Vendor ' . $vendorName . ', Item ' . $itemName . '. ' . implode(', ', $parts) . '.';

                    $this->logActivity($purchaseRfq->id, 'quote_modified_internal', $msg, [
                        'purchase_rfq_item_id'   => (int) $item->id,
                        'purchase_rfq_vendor_id' => (int) $vendorRow->id,
                        'vendor_party_id'        => (int) ($vendorRow->vendor_party_id ?? 0),
                        'old_quote_id'           => (int) $existing->id,
                        'new_quote_id'           => (int) $new->id,
                        'changes'                => $changes,
                        'ip'                     => $request->ip(),
                        'user_agent'             => substr((string) $request->userAgent(), 0, 255),
                    ]);
                }
            }
        }

        // 3) Apply L1 selection (unchanged)
        $auto = $request->has('auto_l1') && (string) $request->input('auto_l1') !== '';
        $manualL1 = $data['l1'] ?? [];

        $hasSelVendor = Schema::hasColumn('purchase_rfq_items', 'selected_vendor_id');
        $hasSelQuote  = Schema::hasColumn('purchase_rfq_items', 'selected_quote_id');

        foreach ($purchaseRfq->items as $rfqItem) {
            $selectedVendorId = null;

            if ($auto) {
                // Auto: choose lowest effective unit total: rate*(1-disc)*(1+tax)
                $bestVendor = null;
                $bestScore  = null;

                foreach ($purchaseRfq->vendors as $vendorRow) {
                    $quoteId = $createdQuoteIds[$rfqItem->id][$vendorRow->id] ?? null;

                    $q = null;
                    if ($quoteId) {
                        $q = PurchaseRfqVendorQuote::find($quoteId);
                    } else {
                        $q = PurchaseRfqVendorQuote::where('purchase_rfq_item_id', $rfqItem->id)
                            ->where('purchase_rfq_vendor_id', $vendorRow->id)
                            ->where('is_active', true)
                            ->latest('id')
                            ->first();
                    }

                    if (!$q) continue;

                    $rate = (float) ($q->rate ?? 0);
                    if ($rate <= 0) continue;

                    $disc = (float) ($q->discount_percent ?? 0);
                    $tax  = (float) ($q->tax_percent ?? 0);

                    $eff = $rate * (1 - $disc / 100.0) * (1 + $tax / 100.0);

                    if ($bestScore === null || $eff < $bestScore) {
                        $bestScore = $eff;
                        $bestVendor = (int) $vendorRow->id;
                    }
                }

                $selectedVendorId = $bestVendor;
            } else {
                // Manual radio: l1[item_id] = rfq_vendor_id
                if (!empty($manualL1[$rfqItem->id])) {
                    $selectedVendorId = (int) $manualL1[$rfqItem->id];
                }
            }

            if ($selectedVendorId && ($hasSelVendor || $hasSelQuote)) {
                $activeQuote = PurchaseRfqVendorQuote::where('purchase_rfq_item_id', $rfqItem->id)
                    ->where('purchase_rfq_vendor_id', $selectedVendorId)
                    ->where('is_active', true)
                    ->latest('id')
                    ->first();

                if ($hasSelVendor) {
                    $rfqItem->selected_vendor_id = $selectedVendorId;
                }
                if ($hasSelQuote) {
                    $rfqItem->selected_quote_id = $activeQuote?->id;
                }

                $rfqItem->save();
            }
        }

        $this->logActivity($purchaseRfq->id, 'quotes_updated', 'Vendor quotes updated.');

        DB::commit();

        return redirect()
            ->route('purchase-rfqs.quotes.edit', $purchaseRfq)
            ->with('success', $auto ? 'Quotes saved and L1 auto-selected.' : 'Quotes updated successfully.');
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('RFQ quote update failed', ['error' => $e->getMessage()]);

        return back()->with('error', 'Failed to update quotes. ' . $e->getMessage());
    }
}


    public function sendEmails(Request $request, PurchaseRfq $purchaseRfq): RedirectResponse
    {
        if ($purchaseRfq->status === 'po_generated') {
            return back()->with('error', 'RFQ already converted to PO.');
        }

        $purchaseRfq->load([
            'project',
            'department',
            'vendors.vendor',
            'items.item.uom',
        ]);

        $resendAll = $request->boolean('resend_all');

        DB::beginTransaction();

        try {
            // Build RFQ PDF once
            $pdf = PDF::loadView('purchase_rfqs.pdf', [
                'rfq' => $purchaseRfq,
            ])->setPaper('a4');

            $pdfBinary = $pdf->output();

            // ✅ Mail template + profile (fallback to legacy view if not found)
            $templateCode = 'purchase_rfq_send';
            $template = null;

            if (class_exists(MailTemplate::class) && Schema::hasTable('mail_templates')) {
                $tq = MailTemplate::query()->where('code', $templateCode);
                if (Schema::hasColumn('mail_templates', 'is_active')) {
                    $tq->where('is_active', 1);
                }
                $template = $tq->first();
            }

            $profile = $this->resolveRfqMailProfile($purchaseRfq, $template);

            // Apply SMTP profile to runtime mail config (if found)
            if ($profile) {
                $this->applyMailProfile($profile);
            }

            $mailer = $profile
                ? Mail::mailer('smtp')
                : Mail::mailer(config('mail.default', 'smtp'));

            $sentCount = 0;

            $hasVendorSentAt = Schema::hasColumn('purchase_rfq_vendors', 'sent_at');

            foreach ($purchaseRfq->vendors as $vendorRow) {
                // Skip withdrawn/cancelled vendors
                if (in_array((string) $vendorRow->status, ['withdrawn', 'cancelled'], true)) {
                    continue;
                }

                // Default behaviour: do NOT resend to vendors already marked sent/responded.
                // Pass resend_all=1 if you want to force resend.
                if (!$resendAll && in_array((string) $vendorRow->status, ['sent', 'responded'], true)) {
                    continue;
                }

                $email = $vendorRow->email ?: ($vendorRow->vendor?->primary_email ?? null);

                if (!$email) {
                    continue;
                }

                $vendorName = $vendorRow->vendor?->name
                    ?? $vendorRow->contact_name
                    ?? 'Vendor';

                $itemsShort = $purchaseRfq->items
                    ->map(fn ($it) => $it->item?->name)
                    ->filter()
                    ->take(3)
                    ->implode(', ');

                if ($purchaseRfq->items->count() > 3 && $itemsShort !== '') {
                    $itemsShort .= '...';
                }

                /**
                 * ✅ Vendor Portal Quote Link (Signed URL)
                 *
                 * This makes quotation entry transparent (vendor fills rates directly).
                 * If the portal route is not deployed yet, we safely skip link generation.
                 */
                $quoteLink = null;
                $quoteLinkExpiresAt = null;
                $quoteLinkExpiresAtStr = '';

                try {
                    // Expiry policy:
                    // - Link expires in 7 days from sending (or earlier if RFQ due date is sooner)
                    $expiresAt = now()->addDays(7);

                    if (!empty($purchaseRfq->due_date)) {
                        $dueExp = Carbon::parse($purchaseRfq->due_date)->endOfDay();
                        if ($dueExp->isFuture() && $dueExp->lt($expiresAt)) {
                            $expiresAt = $dueExp;
                        }
                    }

                    $quoteLinkExpiresAt = $expiresAt;

                    $tzLabel = config('app.timezone', 'UTC');
                    $tzLabel = $tzLabel === 'Asia/Kolkata' ? 'IST' : $tzLabel;

                    $quoteLinkExpiresAtStr = Carbon::parse($expiresAt)
                        ->timezone(config('app.timezone', 'UTC'))
                        ->format('d-m-Y H:i') . ' ' . $tzLabel;

                    $quoteLink = URL::temporarySignedRoute('vendor.rfq.quote', $expiresAt, [
                        'purchase_rfq_vendor' => $vendorRow->id,
                    ]);
                } catch (\Throwable $e) {
                    $quoteLink = null;
                    $quoteLinkExpiresAt = null;
                    $quoteLinkExpiresAtStr = '';
                }

                $vars = [
                    'vendor_name'  => $vendorName,
                    'rfq_code'     => $purchaseRfq->code,
                    'rfq_date'     => $purchaseRfq->rfq_date ? Carbon::parse($purchaseRfq->rfq_date)->format('d-m-Y') : '',
                    'due_date'     => $purchaseRfq->due_date ? Carbon::parse($purchaseRfq->due_date)->format('d-m-Y') : '',
                    'project'      => $purchaseRfq->project?->name ?? 'General / Store',
                    'department'   => $purchaseRfq->department?->name ?? '-',
                    'items_count'  => (string) $purchaseRfq->items->count(),
                    'items_short'  => $itemsShort,
                    'company_name' => config('app.name'),

                    // New placeholder for templates:
                    // Use {{quote_link}} in mail_templates to show this link.
                    'quote_link'   => $quoteLink ?: '',
                    'quote_link_expires_at' => $quoteLinkExpiresAtStr,
                ];

                if ($template) {
                    // Template can be (body_html/body_text) OR legacy single 'body'
                    $subjectTpl = $template->subject ?? ('RFQ ' . $purchaseRfq->code);

                    $bodyHtml = null;
                    $bodyText = null;

                    if (Schema::hasColumn('mail_templates', 'body_html') && !empty($template->body_html)) {
                        $bodyHtml = (string) $template->body_html;
                    }
                    if (Schema::hasColumn('mail_templates', 'body_text') && !empty($template->body_text)) {
                        $bodyText = (string) $template->body_text;
                    }

                    if (!$bodyHtml && Schema::hasColumn('mail_templates', 'body') && !empty($template->body)) {
                        // Treat 'body' as HTML by default
                        $bodyHtml = (string) $template->body;
                    }

                    if (!$bodyText && $bodyHtml) {
                        $bodyText = trim(strip_tags($bodyHtml));
                    }

                    $subject = $this->replaceTemplateVars($subjectTpl, $vars);

                    $bodyHtmlFinal = $bodyHtml ? $this->replaceTemplateVars($bodyHtml, $vars) : null;
                    $bodyTextFinal = $bodyText ? $this->replaceTemplateVars($bodyText, $vars) : '';

                    // If template does not include quote_link, append it automatically
                    if (!empty($quoteLink)) {
                        $expirySuffix = $quoteLinkExpiresAtStr ? " (Valid till: {$quoteLinkExpiresAtStr})" : '';

                        if ($bodyHtmlFinal && strpos($bodyHtmlFinal, $quoteLink) === false) {
                            $bodyHtmlFinal .= '<hr><p><strong>Online Quotation Link' . $expirySuffix . ':</strong><br>'
                                . '<a href="' . e($quoteLink) . '">' . e($quoteLink) . '</a></p>';
                        }
                        if ($bodyTextFinal === '' || strpos($bodyTextFinal, $quoteLink) === false) {
                            $bodyTextFinal = trim($bodyTextFinal . "\n\nOnline Quotation Link{$expirySuffix}: {$quoteLink}\n");
                        }
                    }

                    if ($bodyHtmlFinal) {
                        $mailer->html($bodyHtmlFinal, function ($message) use ($email, $vendorName, $profile, $subject, $pdfBinary, $purchaseRfq) {
                            $message->to($email, $vendorName)->subject($subject);

                            if ($profile && $profile->from_email) {
                                $message->from($profile->from_email, $profile->from_name ?: null);
                            }
                            if ($profile && $profile->reply_to) {
                                $message->replyTo($profile->reply_to);
                            }

                            $message->attachData($pdfBinary, $purchaseRfq->code . '.pdf');
                        });
                    } else {
                        $mailer->raw($bodyTextFinal, function ($message) use ($email, $vendorName, $profile, $subject, $pdfBinary, $purchaseRfq) {
                            $message->to($email, $vendorName)->subject($subject);

                            if ($profile && $profile->from_email) {
                                $message->from($profile->from_email, $profile->from_name ?: null);
                            }
                            if ($profile && $profile->reply_to) {
                                $message->replyTo($profile->reply_to);
                            }

                            $message->attachData($pdfBinary, $purchaseRfq->code . '.pdf');
                        });
                    }
                } else {
                    // Legacy fallback: keep your existing working view-based mail,
                    // but ensure quote_link is always visible.
                    if (view()->exists('emails.purchase_rfq')) {
                        $html = view('emails.purchase_rfq', [
                            'rfq'        => $purchaseRfq,
                            'vendor'     => $vendorRow->vendor,
                            'quote_link' => $quoteLink,
                        ])->render();

                        $expirySuffix = $quoteLinkExpiresAtStr ? " (Valid till: {$quoteLinkExpiresAtStr})" : '';

                        if (!empty($quoteLink) && strpos($html, $quoteLink) === false) {
                            $html .= '<hr><p><strong>Online Quotation Link' . $expirySuffix . ':</strong><br>'
                                . '<a href="' . e($quoteLink) . '">' . e($quoteLink) . '</a></p>';
                        }

                        $mailer->html($html, function ($message) use ($email, $vendorName, $profile, $purchaseRfq, $pdfBinary) {
                            $message->to($email, $vendorName)->subject('RFQ ' . $purchaseRfq->code);

                            if ($profile && $profile->from_email) {
                                $message->from($profile->from_email, $profile->from_name ?: null);
                            }
                            if ($profile && $profile->reply_to) {
                                $message->replyTo($profile->reply_to);
                            }

                            $message->attachData($pdfBinary, $purchaseRfq->code . '.pdf');
                        });
                    } else {
                        $fallback = "Dear {$vendorName},\n\n"
                            . "Please share your best quotation for the attached RFQ {$purchaseRfq->code}.\n";

                        if (!empty($quoteLink)) {
                            $expirySuffix = $quoteLinkExpiresAtStr ? " (Valid till: {$quoteLinkExpiresAtStr})" : '';

                        $fallback .= "\nOnline Quotation Link{$expirySuffix}: {$quoteLink}\n";
                        }

                        $fallback .= "\nRegards,\n" . config('app.name');

                        $mailer->raw($fallback, function ($message) use ($email, $vendorName, $profile, $purchaseRfq, $pdfBinary) {
                            $message->to($email, $vendorName)->subject('RFQ ' . $purchaseRfq->code);

                            if ($profile && $profile->from_email) {
                                $message->from($profile->from_email, $profile->from_name ?: null);
                            }
                            if ($profile && $profile->reply_to) {
                                $message->replyTo($profile->reply_to);
                            }

                            $message->attachData($pdfBinary, $purchaseRfq->code . '.pdf');
                        });
                    }
                }

                $vendorRow->status = 'sent';
                if ($hasVendorSentAt) {
                    $vendorRow->sent_at = now();
                }
                $vendorRow->save();

                $sentCount++;
            }

            if ($purchaseRfq->status === 'draft') {
                $purchaseRfq->status = 'sent';
                $purchaseRfq->save();
            }

            $this->logActivity(
                $purchaseRfq->id,
                'sent',
                'RFQ emails sent to vendors.',
                ['sent_count' => $sentCount]
            );

            DB::commit();

            return back()->with('success', 'RFQ emails sent successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('RFQ email send failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Failed to send RFQ emails. ' . $e->getMessage());
        }
    }



    /**
     * Request revised quotation from selected vendors (sends portal link + competitor best-rate summary).
     *
     * UI suggestion: add checkboxes in Quotes & L1 screen to pick vendors and call this route.
     */
    public function sendRevisionEmails(Request $request, PurchaseRfq $purchaseRfq): RedirectResponse
    {
        if (in_array((string) $purchaseRfq->status, ['po_generated', 'cancelled', 'closed'], true)) {
            return back()->with('error', 'RFQ is closed / cancelled / already converted to PO.');
        }

        $data = $request->validate([
            'vendor_ids'   => ['required', 'array', 'min:1'],
            'vendor_ids.*' => ['integer'],
            'message'      => ['nullable', 'string', 'max:2000'],
        ]);

        $purchaseRfq->load([
            'project',
            'department',
            'vendors.vendor',
            'items.item.uom',
        ]);

        $itemIds = $purchaseRfq->items->pluck('id')->all();
        $selectedVendorIds = array_values(array_map('intval', $data['vendor_ids'] ?? []));

        $vendors = $purchaseRfq->vendors->whereIn('id', $selectedVendorIds);

        if ($vendors->count() === 0) {
            return back()->with('error', 'No valid vendors selected.');
        }

        DB::beginTransaction();

        try {
            // Build RFQ PDF once (attach along with revision mail)
            $pdf = PDF::loadView('purchase_rfqs.pdf', [
                'rfq' => $purchaseRfq,
            ])->setPaper('a4');

            $pdfBinary = $pdf->output();

            // Mail template preferred for revision request
            $template = null;
            $templateCode = 'purchase_rfq_revision_request';

            if (class_exists(MailTemplate::class) && Schema::hasTable('mail_templates')) {
                $tq = MailTemplate::query()->where('code', $templateCode);
                if (Schema::hasColumn('mail_templates', 'is_active')) {
                    $tq->where('is_active', 1);
                }
                $template = $tq->first();

                // fallback to regular RFQ send template if revision template not found
                if (!$template) {
                    $tq2 = MailTemplate::query()->where('code', 'purchase_rfq_send');
                    if (Schema::hasColumn('mail_templates', 'is_active')) {
                        $tq2->where('is_active', 1);
                    }
                    $template = $tq2->first();
                }
            }

            $profile = $this->resolveRfqMailProfile($purchaseRfq, $template);

            if ($profile) {
                $this->applyMailProfile($profile);
            }

            $mailer = $profile
                ? Mail::mailer('smtp')
                : Mail::mailer(config('mail.default', 'smtp'));

            $sentCount = 0;

            foreach ($vendors as $vendorRow) {
                if (in_array((string) $vendorRow->status, ['withdrawn', 'cancelled'], true)) {
                    continue;
                }

                $email = $vendorRow->email ?: ($vendorRow->vendor?->primary_email ?? null);
                if (!$email) {
                    continue;
                }

                $vendorName = $vendorRow->vendor?->name
                    ?? $vendorRow->contact_name
                    ?? 'Vendor';

                // Signed link (allow revision)
                $quoteLink = null;
                $quoteLinkExpiresAtStr = '';

                try {
                    $expiresAt = now()->addDays(7);

                    if (!empty($purchaseRfq->due_date)) {
                        $dueExp = Carbon::parse($purchaseRfq->due_date)->endOfDay();
                        if ($dueExp->isFuture() && $dueExp->lt($expiresAt)) {
                            $expiresAt = $dueExp;
                        }
                    }

                    $tzLabel = config('app.timezone', 'UTC');
                    $tzLabel = $tzLabel === 'Asia/Kolkata' ? 'IST' : $tzLabel;

                    $quoteLinkExpiresAtStr = Carbon::parse($expiresAt)
                        ->timezone(config('app.timezone', 'UTC'))
                        ->format('d-m-Y H:i') . ' ' . $tzLabel;

                    $quoteLink = URL::temporarySignedRoute('vendor.rfq.quote', $expiresAt, [
                        'purchase_rfq_vendor' => $vendorRow->id,
                    ]);
                } catch (\Throwable $e) {
                    $quoteLink = null;
                    $quoteLinkExpiresAtStr = '';
                }

                // Build competitor best-rate summary (do NOT reveal competitor names)
                $myQuotes = PurchaseRfqVendorQuote::query()
                    ->where('purchase_rfq_vendor_id', $vendorRow->id)
                    ->where('is_active', 1)
                    ->whereIn('purchase_rfq_item_id', $itemIds)
                    ->get()
                    ->keyBy('purchase_rfq_item_id');

                $bestOther = PurchaseRfqVendorQuote::query()
                    ->select('purchase_rfq_item_id', DB::raw('MIN(rate) as best_rate'))
                    ->where('is_active', 1)
                    ->whereIn('purchase_rfq_item_id', $itemIds)
                    ->where('purchase_rfq_vendor_id', '!=', $vendorRow->id)
                    ->whereNotNull('rate')
                    ->where('rate', '>', 0)
                    ->groupBy('purchase_rfq_item_id')
                    ->get()
                    ->keyBy('purchase_rfq_item_id');

                $rows = [];
                foreach ($purchaseRfq->items as $it) {
                    $my = $myQuotes->get($it->id);
                    $myRate = $my?->rate !== null ? (float) $my->rate : null;

                    $best = $bestOther->get($it->id);
                    $bestRate = $best?->best_rate !== null ? (float) $best->best_rate : null;

                    // Show only where vendor is not lowest (or not quoted)
                    if ($bestRate !== null) {
                        if ($myRate === null || $myRate <= 0) {
                            $rows[] = [
                                'item' => $it->item?->name ?? 'Item',
                                'qty'  => (float) ($it->quantity ?? 0),
                                'uom'  => $it->uom?->code ?? $it->uom?->name ?? '',
                                'my_rate' => null,
                                'best_rate' => $bestRate,
                            ];
                        } elseif ($myRate > $bestRate) {
                            $rows[] = [
                                'item' => $it->item?->name ?? 'Item',
                                'qty'  => (float) ($it->quantity ?? 0),
                                'uom'  => $it->uom?->code ?? $it->uom?->name ?? '',
                                'my_rate' => $myRate,
                                'best_rate' => $bestRate,
                            ];
                        }
                    }
                }

                $revisionTableHtml = '';
                if (!empty($rows)) {
                    $revisionTableHtml .= '<table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse:collapse;">';
                    $revisionTableHtml .= '<thead><tr style="background:#f2f2f2;">'
                        . '<th align="left">Item</th>'
                        . '<th align="right">Qty</th>'
                        . '<th align="left">UOM</th>'
                        . '<th align="right">Your Rate</th>'
                        . '<th align="right">Best Rate Received</th>'
                        . '</tr></thead><tbody>';

                    foreach ($rows as $r) {
                        $revisionTableHtml .= '<tr>'
                            . '<td>' . e($r['item']) . '</td>'
                            . '<td align="right">' . number_format((float) $r['qty'], 3) . '</td>'
                            . '<td>' . e($r['uom']) . '</td>'
                            . '<td align="right">' . ($r['my_rate'] === null ? '-' : number_format((float) $r['my_rate'], 2)) . '</td>'
                            . '<td align="right">' . number_format((float) $r['best_rate'], 2) . '</td>'
                            . '</tr>';
                    }

                    $revisionTableHtml .= '</tbody></table>';
                } else {
                    $revisionTableHtml = '<p><em>No items found where another vendor has lower active rate (or no competitor quotes yet).</em></p>';
                }

                $vars = [
                    'vendor_name'  => $vendorName,
                    'rfq_code'     => $purchaseRfq->code,
                    'rfq_date'     => $purchaseRfq->rfq_date ? Carbon::parse($purchaseRfq->rfq_date)->format('d-m-Y') : '',
                    'due_date'     => $purchaseRfq->due_date ? Carbon::parse($purchaseRfq->due_date)->format('d-m-Y') : '',
                    'project'      => $purchaseRfq->project?->name ?? 'General / Store',
                    'department'   => $purchaseRfq->department?->name ?? '-',
                    'company_name' => config('app.name'),

                    'payment_terms_days'  => (string) ($purchaseRfq->payment_terms_days ?? ''),
                    'delivery_terms_days' => (string) ($purchaseRfq->delivery_terms_days ?? ''),
                    'freight_terms'       => (string) ($purchaseRfq->freight_terms ?? ''),

                    'quote_link'           => $quoteLink ?: '',
                    'quote_link_expires_at'=> $quoteLinkExpiresAtStr,
                    'revision_table_html'  => $revisionTableHtml,
                    'message'              => (string) ($data['message'] ?? ''),
                ];

                $defaultSubject = 'RFQ ' . $purchaseRfq->code . ' - Request for Revised Quotation';
                $subject = $defaultSubject;
                $bodyHtmlFinal = null;
                $bodyTextFinal = '';

                if ($template) {
                    $subjectTpl = $template->subject ?? $defaultSubject;

                    $bodyHtml = null;
                    $bodyText = null;

                    if (Schema::hasColumn('mail_templates', 'body_html') && !empty($template->body_html)) {
                        $bodyHtml = (string) $template->body_html;
                    }
                    if (Schema::hasColumn('mail_templates', 'body_text') && !empty($template->body_text)) {
                        $bodyText = (string) $template->body_text;
                    }
                    if (!$bodyHtml && Schema::hasColumn('mail_templates', 'body') && !empty($template->body)) {
                        $bodyHtml = (string) $template->body;
                    }
                    if (!$bodyText && $bodyHtml) {
                        $bodyText = trim(strip_tags($bodyHtml));
                    }

                    $subject = $this->replaceTemplateVars($subjectTpl, $vars);
                    $bodyHtmlFinal = $bodyHtml ? $this->replaceTemplateVars($bodyHtml, $vars) : null;
                    $bodyTextFinal = $bodyText ? $this->replaceTemplateVars($bodyText, $vars) : '';

                    // Ensure revision table is visible even if template not updated
                    if ($bodyHtmlFinal && strpos($bodyHtmlFinal, 'revision_table_html') === false && strpos($bodyHtmlFinal, '<table') === false) {
                        $bodyHtmlFinal .= '<hr><p><strong>Items to Review</strong></p>' . $revisionTableHtml;
                    }

                    // Ensure link + expiry is visible even if template not updated
                    if (!empty($quoteLink) && $bodyHtmlFinal && strpos($bodyHtmlFinal, $quoteLink) === false) {
                        $expirySuffix = $quoteLinkExpiresAtStr ? " (Valid till: {$quoteLinkExpiresAtStr})" : '';
                        $bodyHtmlFinal .= '<hr><p><strong>Online Quotation Link' . $expirySuffix . ':</strong><br>'
                            . '<a href="' . e($quoteLink) . '">' . e($quoteLink) . '</a></p>';
                    }

                    if (!empty($quoteLink) && ($bodyTextFinal === '' || strpos($bodyTextFinal, $quoteLink) === false)) {
                        $expirySuffix = $quoteLinkExpiresAtStr ? " (Valid till: {$quoteLinkExpiresAtStr})" : '';
                        $bodyTextFinal = trim($bodyTextFinal . "\n\nOnline Quotation Link{$expirySuffix}: {$quoteLink}\n");
                    }
                } else {
                    // Fallback email content
                    $expirySuffix = $quoteLinkExpiresAtStr ? " (Valid till: {$quoteLinkExpiresAtStr})" : '';

                    $bodyHtmlFinal = '<p>Dear ' . e($vendorName) . ',</p>'
                        . '<p>We request you to submit a <strong>revised quotation</strong> for RFQ <strong>' . e($purchaseRfq->code) . '</strong>.</p>'
                        . (!empty($vars['message']) ? '<p><strong>Note from Purchase:</strong> ' . nl2br(e($vars['message'])) . '</p>' : '')
                        . '<p><strong>Buyer Terms:</strong> Payment ' . e($vars['payment_terms_days']) . ' days, '
                        . 'Delivery ' . e($vars['delivery_terms_days']) . ' days, '
                        . 'Freight ' . e($vars['freight_terms']) . '.</p>'
                        . '<p><strong>Items to Review</strong></p>'
                        . $revisionTableHtml
                        . (!empty($quoteLink) ? '<hr><p><strong>Online Quotation Link' . $expirySuffix . ':</strong><br><a href="' . e($quoteLink) . '">' . e($quoteLink) . '</a></p>' : '')
                        . '<p>Regards,<br>' . e(config('app.name')) . '</p>';

                    $bodyTextFinal = "Dear {$vendorName},\n\n"
                        . "We request you to submit a revised quotation for RFQ {$purchaseRfq->code}.\n"
                        . (!empty($vars['message']) ? "\nNote from Purchase: " . $vars['message'] . "\n" : "")
                        . "\nBuyer Terms: Payment {$vars['payment_terms_days']} days, Delivery {$vars['delivery_terms_days']} days, Freight {$vars['freight_terms']}.\n"
                        . (!empty($quoteLink) ? "\nOnline Quotation Link{$expirySuffix}: {$quoteLink}\n" : "")
                        . "\nRegards,\n" . config('app.name');
                }

                if ($bodyHtmlFinal) {
                    $mailer->html($bodyHtmlFinal, function ($message) use ($email, $vendorName, $profile, $subject, $pdfBinary, $purchaseRfq) {
                        $message->to($email, $vendorName)->subject($subject);

                        if ($profile && $profile->from_email) {
                            $message->from($profile->from_email, $profile->from_name ?: null);
                        }
                        if ($profile && $profile->reply_to) {
                            $message->replyTo($profile->reply_to);
                        }

                        $message->attachData($pdfBinary, $purchaseRfq->code . '.pdf');
                    });
                } else {
                    $mailer->raw($bodyTextFinal, function ($message) use ($email, $vendorName, $profile, $subject, $pdfBinary, $purchaseRfq) {
                        $message->to($email, $vendorName)->subject($subject);

                        if ($profile && $profile->from_email) {
                            $message->from($profile->from_email, $profile->from_name ?: null);
                        }
                        if ($profile && $profile->reply_to) {
                            $message->replyTo($profile->reply_to);
                        }

                        $message->attachData($pdfBinary, $purchaseRfq->code . '.pdf');
                    });
                }

                // Mark vendor row status for tracking
                $vendorRow->status = 'revision_requested';
                if (Schema::hasColumn('purchase_rfq_vendors', 'sent_at')) {
                    $vendorRow->sent_at = now();
                }
                $vendorRow->save();

                $sentCount++;
            }

            $this->logActivity(
                $purchaseRfq->id,
                'revision_requested',
                'Revision request sent to vendors.',
                ['sent_count' => $sentCount]
            );

            DB::commit();

            return back()->with('success', "Revision request sent to {$sentCount} vendor(s).");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('RFQ revision email send failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Failed to send revision request. ' . $e->getMessage());
        }
    }

// ✅ UPDATED: cancel now triggers indent recalc
    public function cancel(Request $request, PurchaseRfq $purchaseRfq): RedirectResponse
    {
        $request->validate([
            'reason'        => ['nullable', 'string', 'max:1000'],
            'cancel_reason' => ['nullable', 'string', 'max:1000'], // backward-compat with older UI
        ]);

        $activePo = PurchaseOrder::where('purchase_rfq_id', $purchaseRfq->id)
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        if ($activePo) {
            return back()->with('error', 'Cannot cancel RFQ. Cancel linked PO first.');
        }

        $reason = $request->input('reason');
        if ($reason === null || trim((string) $reason) === '') {
            $reason = $request->input('cancel_reason');
        }
        $reason = $reason !== null ? trim((string) $reason) : null;

        $purchaseRfq->status = 'cancelled';

        if (Schema::hasColumn('purchase_rfqs', 'cancel_reason')) {
            $purchaseRfq->cancel_reason = $reason;
        }

        if (Schema::hasColumn('purchase_rfqs', 'cancelled_at')) {
            $purchaseRfq->cancelled_at = now();
        }
        if (Schema::hasColumn('purchase_rfqs', 'cancelled_by')) {
            $purchaseRfq->cancelled_by = auth()->id();
        }

        $purchaseRfq->save();

        $this->logActivity($purchaseRfq->id, 'cancelled', 'RFQ cancelled.', [
            'reason' => $reason,
        ]);

        // Recalculate indent procurement status when RFQ cancelled
        if ($purchaseRfq->purchase_indent_id) {
            app(PurchaseIndentProcurementService::class)->recalcIndent((int) $purchaseRfq->purchase_indent_id);
        }

        return redirect()
            ->route('purchase-rfqs.show', $purchaseRfq)
            ->with('success', 'RFQ cancelled.');
    }

    public function destroy(PurchaseRfq $purchaseRfq): RedirectResponse
    {
        if ($purchaseRfq->status === 'po_generated') {
            return back()->with('error', 'RFQ already converted to PO.');
        }

        $purchaseRfq->delete();

        return redirect()
            ->route('purchase-rfqs.index')
            ->with('success', 'RFQ deleted.');
    }

    // ---------------------------------------------------------------------
    // Brands helpers (Phase 2)
    // ---------------------------------------------------------------------

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

    private function validateLineBrands(array $items): void
    {
        if (empty($items)) {
            return;
        }

        $itemIds = [];
        foreach ($items as $row) {
            $id = (int) ($row['item_id'] ?? 0);
            if ($id > 0) {
                $itemIds[$id] = true;
            }
        }

        if (empty($itemIds)) {
            return;
        }

        $itemRows = Item::query()
            ->whereIn('id', array_keys($itemIds))
            ->get()
            ->keyBy('id');

        foreach ($items as $i => $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $brandRaw = trim((string) ($row['brand'] ?? ''));

            if ($itemId <= 0 || $brandRaw === '') {
                continue;
            }

            $item = $itemRows->get($itemId);
            if (! $item) {
                continue;
            }

            $allowed = $this->normalizeBrands($item->brands);
            $allowedLower = array_values(array_filter(array_map(fn ($b) => strtolower(trim((string) $b)), $allowed)));

            // If list exists -> enforce
            if (! empty($allowedLower)) {
                $chosen = strtolower($brandRaw);
                if (! in_array($chosen, $allowedLower, true)) {
                    throw ValidationException::withMessages([
                        "items.$i.brand" => "Brand '{$brandRaw}' is not configured for the selected item.",
                    ]);
                }
            }
        }
    }

    private function generateRfqCode(): string
    {
        $year = date('y');
        $prefix = 'RFQ-' . $year . '-';

        $last = PurchaseRfq::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();

        $next = $last ? ((int) substr((string) $last->code, -4) + 1) : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Replace placeholders like {{rfq_code}} or {rfq_code} inside template strings.
     */
    private function replaceTemplateVars(string $content, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $value = (string) $value;

            $content = str_replace(
                ['{{' . $key . '}}', '{{ ' . $key . ' }}', '{' . $key . '}', '{ ' . $key . ' }'],
                $value,
                $content
            );
        }

        return $content;
    }

    /**
     * Resolve mail profile for RFQ sending.
     *
     * Priority:
     * 1) Template's mail_profile_id
     * 2) Department default profile
     * 3) Global default profile
     * 4) Any active profile
     */
    private function resolveRfqMailProfile(PurchaseRfq $rfq, ?MailTemplate $template = null): ?MailProfile
    {
        if (!class_exists(MailProfile::class) || !Schema::hasTable('mail_profiles')) {
            return null;
        }

        $base = MailProfile::query();

        if (Schema::hasColumn('mail_profiles', 'is_active')) {
            $base->where('is_active', 1);
        }

        // 1) Template selected profile
        if ($template && Schema::hasTable('mail_templates') && Schema::hasColumn('mail_templates', 'mail_profile_id')) {
            $tplProfileId = (int) ($template->mail_profile_id ?? 0);
            if ($tplProfileId > 0) {
                $p = (clone $base)->where('id', $tplProfileId)->first();
                if ($p) {
                    return $p;
                }
            }
        }

        // 2) Department default profile
        if (!empty($rfq->department_id)
            && Schema::hasColumn('mail_profiles', 'department_id')
            && Schema::hasColumn('mail_profiles', 'is_default')) {
            $p = (clone $base)
                ->where('department_id', $rfq->department_id)
                ->where('is_default', 1)
                ->first();

            if ($p) {
                return $p;
            }
        }

        // 3) Global default profile
        if (Schema::hasColumn('mail_profiles', 'is_default')) {
            $p = (clone $base)->where('is_default', 1)->first();
            if ($p) {
                return $p;
            }
        }

        // 4) Any active profile
        return $base->first();
    }

    /**
     * Apply SMTP profile dynamically for this request lifecycle.
     *
     * NOTE: We set both modern (mail.mailers.smtp.*) and legacy (mail.*) keys,
     * so it works across Laravel versions / existing config patterns.
     */
    private function applyMailProfile(MailProfile $profile): void
    {
        $host = $profile->smtp_host ?? ($profile->host ?? null);
        $port = $profile->smtp_port ?? ($profile->port ?? null);
        $enc  = $profile->smtp_encryption ?? ($profile->encryption ?? null);
        $user = $profile->smtp_username ?? ($profile->username ?? null);
        $pass = $profile->smtp_password ?? ($profile->password ?? null);

        // Modern config (Laravel 8+)
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.encryption', $enc);
        Config::set('mail.mailers.smtp.username', $user);
        Config::set('mail.mailers.smtp.password', $pass);

        // Legacy keys (harmless if unused)
        Config::set('mail.host', $host);
        Config::set('mail.port', $port);
        Config::set('mail.encryption', $enc);
        Config::set('mail.username', $user);
        Config::set('mail.password', $pass);

        if (!empty($profile->from_email)) {
            Config::set('mail.from.address', $profile->from_email);
        }
        if (!empty($profile->from_name)) {
            Config::set('mail.from.name', $profile->from_name);
        }
    }

    private function logActivity(int $rfqId, string $action, string $message, array $meta = []): void
    {
        try {
            // If activity module/table not available in this deployment, skip silently.
            if (!class_exists(PurchaseRfqActivity::class) || !Schema::hasTable('purchase_rfq_activities')) {
                return;
            }

            $activity = new PurchaseRfqActivity();

            if (Schema::hasColumn('purchase_rfq_activities', 'purchase_rfq_id')) {
                $activity->purchase_rfq_id = $rfqId;
            }
            if (Schema::hasColumn('purchase_rfq_activities', 'action')) {
                $activity->action = $action;
            }
            if (Schema::hasColumn('purchase_rfq_activities', 'message')) {
                $activity->message = $message;
            }

            // ✅ Column name differs across deployments (user_id vs created_by)
            if (Schema::hasColumn('purchase_rfq_activities', 'user_id')) {
                $activity->user_id = auth()->id();
            } elseif (Schema::hasColumn('purchase_rfq_activities', 'created_by')) {
                $activity->created_by = auth()->id();
            }

            if (!empty($meta) && Schema::hasColumn('purchase_rfq_activities', 'meta')) {
                $activity->meta = $meta;
            }

            $activity->save();
        } catch (\Throwable $e) {
            Log::warning('Failed to log RFQ activity', [
                'rfq_id' => $rfqId,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
