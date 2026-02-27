<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRfqActivity;
use App\Models\PurchaseRfqVendor;
use App\Models\PurchaseRfqVendorQuote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PurchaseRfqVendorPortalController extends Controller
{
    /**
     * Vendor quotation portal (public, signed URL).
     */
    public function show(Request $request, PurchaseRfqVendor $purchase_rfq_vendor): View
    {
        $purchase_rfq_vendor->load([
            'vendor',
            'rfq',
            'rfq.project',
            'rfq.department',
            'rfq.items.item.uom',
        ]);

        $rfq = $purchase_rfq_vendor->rfq;

        // Link expiry info (read from signed URL query)
        $linkExpiresAt = null;
        $expiresTs = $request->query('expires');
        if (is_numeric($expiresTs)) {
            try {
                $linkExpiresAt = Carbon::createFromTimestamp((int) $expiresTs)
                    ->timezone(config('app.timezone', 'UTC'));
            } catch (\Throwable $e) {
                $linkExpiresAt = null;
            }
        }

        // If RFQ is closed / PO generated, show closed page
        [$closed, $closedReason] = $this->isPortalClosed($purchase_rfq_vendor, $rfq);

        if ($closed) {
            return view('vendor_rfqs.closed', [
                'rfqVendor'     => $purchase_rfq_vendor,
                'rfq'           => $rfq,
                'linkExpiresAt' => $linkExpiresAt,
                'closedReason'  => $closedReason,
            ]);
        }

        // Active (latest) quotes for this vendor, keyed by rfq_item_id
        $activeQuotes = PurchaseRfqVendorQuote::query()
            ->where('purchase_rfq_vendor_id', $purchase_rfq_vendor->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('purchase_rfq_item_id');

        // Full history for this vendor, grouped by item (for transparency)
        $historyByItem = PurchaseRfqVendorQuote::query()
            ->where('purchase_rfq_vendor_id', $purchase_rfq_vendor->id)
            ->orderBy('purchase_rfq_item_id')
            ->orderByDesc('revision_no')
            ->get()
            ->groupBy('purchase_rfq_item_id');

        // Use any active quote row to prefill header-like fields
        $headerQuote = $activeQuotes->first();

        return view('vendor_rfqs.quote', [
            'rfqVendor'     => $purchase_rfq_vendor,
            'rfq'           => $rfq,
            'activeQuotes'  => $activeQuotes,
            'headerQuote'   => $headerQuote,
            'historyByItem' => $historyByItem,
            'linkExpiresAt' => $linkExpiresAt,
        ]);
    }

    /**
     * POST handler for vendor quotation submission.
     *
     * Your routes may point to store() or submit().
     * Keep both for backward-compat.
     */
    public function store(Request $request, PurchaseRfqVendor $purchase_rfq_vendor): RedirectResponse
    {
        return $this->submit($request, $purchase_rfq_vendor);
    }

    /**
     * Submit vendor quotation (creates revision rows per item, keeps history).
     */
    public function submit(Request $request, PurchaseRfqVendor $purchase_rfq_vendor): RedirectResponse
    {
        $purchase_rfq_vendor->load([
            'vendor',
            'rfq',
            'rfq.items',
        ]);

        $rfq = $purchase_rfq_vendor->rfq;

        [$closed, $closedReason] = $this->isPortalClosed($purchase_rfq_vendor, $rfq);

        if ($closed) {
            return back()->with('error', $closedReason ?: 'This RFQ is closed and not accepting quotations.');
        }

        $validated = $request->validate([
            // Header-ish fields
            'vendor_quote_no'   => ['nullable', 'string', 'max:255'],
            'vendor_quote_date' => ['nullable', 'date'],
            'valid_till'        => ['nullable', 'date'],

            // Vendor offered terms (RFQ terms are shown separately in portal)
            'payment_terms_days'  => ['nullable', 'integer', 'min:0', 'max:3650'],
            'delivery_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'freight_terms'       => ['nullable', 'string', 'max:255'],

            // Lines: quotes[rfq_item_id][rate,discount_percent,tax_percent,delivery_days,remarks]
            'quotes'                       => ['required', 'array'],
            'quotes.*.rate'                => ['nullable', 'numeric', 'min:0'],
            'quotes.*.discount_percent'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quotes.*.tax_percent'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quotes.*.delivery_days'       => ['nullable', 'integer', 'min:0', 'max:3650'],
            'quotes.*.remarks'             => ['nullable', 'string', 'max:1000'],
        ]);

        // Filter only RFQ item ids that belong to this RFQ
        $validItemIds = $rfq->items->pluck('id')->map(fn ($v) => (string) $v)->all();
        $quotesIn = $validated['quotes'] ?? [];
        $quotesFiltered = array_intersect_key($quotesIn, array_flip($validItemIds));

        // Must have at least one rate entered
        $hasRate = false;
        foreach ($quotesFiltered as $row) {
            $rate = $row['rate'] ?? null;
            if ($rate !== null && $rate !== '' && (float) $rate > 0) {
                $hasRate = true;
                break;
            }
        }
        if (!$hasRate) {
            return back()->withInput()->with('error', 'Please enter rate for at least one item.');
        }

        DB::beginTransaction();

        try {
            // Save vendor commercial terms at vendor level (columns exist in your DB)
            if (Schema::hasColumn('purchase_rfq_vendors', 'payment_terms_days')) {
                $purchase_rfq_vendor->payment_terms_days = $validated['payment_terms_days'] ?? null;
            }
            if (Schema::hasColumn('purchase_rfq_vendors', 'delivery_terms_days')) {
                $purchase_rfq_vendor->delivery_terms_days = $validated['delivery_terms_days'] ?? null;
            }
            if (Schema::hasColumn('purchase_rfq_vendors', 'freight_terms')) {
                $purchase_rfq_vendor->freight_terms = $validated['freight_terms'] ?? null;
            }

            // Mark vendor responded
            $purchase_rfq_vendor->status = 'responded';
            $purchase_rfq_vendor->save();

            // Create quote revisions per item
            foreach ($quotesFiltered as $rfqItemIdStr => $row) {
                $rfqItemId = (int) $rfqItemIdStr;

                $rate = $row['rate'] ?? null;
                if ($rate === null || $rate === '' || (float) $rate <= 0) {
                    continue;
                }

                // Deactivate old active quote for this item/vendor
                PurchaseRfqVendorQuote::query()
                    ->where('purchase_rfq_item_id', $rfqItemId)
                    ->where('purchase_rfq_vendor_id', $purchase_rfq_vendor->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                // Increment revision no
                $maxRev = (int) PurchaseRfqVendorQuote::query()
                    ->where('purchase_rfq_item_id', $rfqItemId)
                    ->where('purchase_rfq_vendor_id', $purchase_rfq_vendor->id)
                    ->max('revision_no');

                $revisionNo = $maxRev + 1;

                PurchaseRfqVendorQuote::create([
                    'purchase_rfq_item_id'   => $rfqItemId,
                    'purchase_rfq_vendor_id' => $purchase_rfq_vendor->id,
                    'revision_no'            => $revisionNo,
                    'is_active'              => true,

                    'vendor_quote_no'        => $validated['vendor_quote_no'] ?? null,
                    'vendor_quote_date'      => !empty($validated['vendor_quote_date'])
                        ? Carbon::parse($validated['vendor_quote_date'])->toDateString()
                        : null,
                    'valid_till'             => !empty($validated['valid_till'])
                        ? Carbon::parse($validated['valid_till'])->toDateString()
                        : null,

                    'rate'                   => (float) $rate,
                    'discount_percent'       => (float) ($row['discount_percent'] ?? 0),
                    'tax_percent'            => (float) ($row['tax_percent'] ?? 0),
                    'delivery_days'          => !empty($row['delivery_days']) ? (int) $row['delivery_days'] : null,
                    'remarks'                => $row['remarks'] ?? null,
                ]);
            }

            // Log activity (transparent audit trail)
            if (class_exists(PurchaseRfqActivity::class) && Schema::hasTable('purchase_rfq_activities')) {
                PurchaseRfqActivity::create([
                    'purchase_rfq_id' => $rfq->id,
                    'user_id'         => null, // vendor portal (no internal user)
                    'action'          => 'vendor_quote_submitted',
                    'message'         => 'Vendor quotation submitted via portal.',
                    'meta'            => [
                        'purchase_rfq_vendor_id' => $purchase_rfq_vendor->id,
                        'vendor_party_id'        => $purchase_rfq_vendor->vendor_party_id,
                        'ip'                     => $request->ip(),
                        'user_agent'             => substr((string) $request->userAgent(), 0, 255),
                    ],
                ]);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Thank you! Your quotation has been submitted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Vendor RFQ quote portal submission failed', [
                'purchase_rfq_vendor_id' => $purchase_rfq_vendor->id ?? null,
                'purchase_rfq_id'        => $rfq->id ?? null,
                'error'                  => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'Failed to submit quotation. Please try again.');
        }
    }

    /**
     * Determine whether vendor portal should be read-only/closed.
     *
     * Important:
     * - Even if RFQ status is not updated, if a PO exists for this RFQ we must block vendor edits.
     */
    private function isPortalClosed(PurchaseRfqVendor $rfqVendor, $rfq): array
    {
        $vendorStatus = (string) ($rfqVendor->status ?? '');
        if (in_array($vendorStatus, ['withdrawn', 'cancelled'], true)) {
            return [true, 'This vendor inquiry is closed.'];
        }

        $rfqStatus = (string) ($rfq->status ?? '');

        if ($rfqStatus === 'po_generated') {
            return [true, 'Purchase Order has been generated. Quotation entry is locked.'];
        }
        if ($rfqStatus === 'closed') {
            return [true, 'This RFQ is closed and not accepting quotations.'];
        }
        if ($rfqStatus === 'cancelled') {
            return [true, 'This RFQ is cancelled.'];
        }

        // Extra guard: if PO exists, close portal even if status not updated (rare but safe)
        $hasActivePo = false;

        try {
            if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'purchase_rfq_id')) {
                $poQ = DB::table('purchase_orders')
                    ->where('purchase_rfq_id', $rfq->id);

                if (Schema::hasColumn('purchase_orders', 'deleted_at')) {
                    $poQ->whereNull('deleted_at');
                }
                if (Schema::hasColumn('purchase_orders', 'status')) {
                    $poQ->whereNotIn('status', ['cancelled']);
                }

                $hasActivePo = $poQ->exists();
            }
        } catch (\Throwable $e) {
            $hasActivePo = false;
        }

        if ($hasActivePo) {
            return [true, 'Purchase Order has been generated. Quotation entry is locked.'];
        }

        return [false, null];
    }
}
