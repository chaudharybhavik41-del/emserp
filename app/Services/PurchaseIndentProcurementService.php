<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recalculate indent procurement tracking from RFQs + POs.
 *
 * - Uses purchase_indent_items.order_qty as requested quantity
 * - RFQ allocations: purchase_rfq_items.allocated_indent_qty (fallback to quantity)
 * - PO quantities: purchase_order_items.quantity (only non-cancelled POs)
 *
 * This service NEVER writes to purchase_indents.status (approval status).
 * It writes to purchase_indents.procurement_status (if the column exists).
 */
class PurchaseIndentProcurementService
{
    public function recalcIndent(int $indentId): void
    {
        // If purchase indents table doesn't exist in a fresh env, do nothing.
        if (!Schema::hasTable('purchase_indents') || !Schema::hasTable('purchase_indent_items')) {
            return;
        }

        DB::transaction(function () use ($indentId) {
            $indent = DB::table('purchase_indents')->where('id', $indentId)->first();
            if (!$indent) {
                return;
            }

            // Indent items
            $items = DB::table('purchase_indent_items')
                ->where('purchase_indent_id', $indentId)
                ->when(Schema::hasColumn('purchase_indent_items', 'deleted_at'), function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->get(['id', 'order_qty']);

            if ($items->isEmpty()) {
                // If no lines, keep procurement_status open
                $this->updateIndentProcurementStatus($indentId, 'open');
                return;
            }

            $itemIds = $items->pluck('id')->all();

            // RFQ allocated qty per indent item (active RFQs only)
            $rfqAlloc = DB::table('purchase_rfq_items as ri')
                ->join('purchase_rfqs as r', 'r.id', '=', 'ri.purchase_rfq_id')
                ->whereIn('ri.purchase_indent_item_id', $itemIds)
                ->whereNull('ri.purchase_indent_item_id', 'and', false); // no-op for safety
            // apply soft delete filters if present
            if (Schema::hasColumn('purchase_rfq_items', 'deleted_at')) {
                $rfqAlloc->whereNull('ri.deleted_at');
            }
            if (Schema::hasColumn('purchase_rfqs', 'deleted_at')) {
                $rfqAlloc->whereNull('r.deleted_at');
            }
            // status filter (ignore cancelled)
            if (Schema::hasColumn('purchase_rfqs', 'status')) {
                $rfqAlloc->whereNotIn('r.status', ['cancelled']);
            }

            // allocation column may not exist yet; fallback to quantity
            $allocCol = Schema::hasColumn('purchase_rfq_items', 'allocated_indent_qty') ? 'ri.allocated_indent_qty' : 'ri.quantity';

            $rfqAlloc = $rfqAlloc
                ->selectRaw('ri.purchase_indent_item_id as indent_item_id, COALESCE(SUM(' . $allocCol . '),0) as qty')
                ->groupBy('ri.purchase_indent_item_id')
                ->pluck('qty', 'indent_item_id');

            // PO ordered qty per indent item (active POs only)
            $poQty = DB::table('purchase_order_items as poi')
                ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                ->whereIn('poi.purchase_indent_item_id', $itemIds)
                ->whereNotNull('poi.purchase_indent_item_id');

            if (Schema::hasColumn('purchase_order_items', 'deleted_at')) {
                $poQty->whereNull('poi.deleted_at');
            }
            if (Schema::hasColumn('purchase_orders', 'deleted_at')) {
                $poQty->whereNull('po.deleted_at');
            }
            if (Schema::hasColumn('purchase_orders', 'status')) {
                $poQty->whereNotIn('po.status', ['cancelled']);
            }

            $poQty = $poQty
                ->selectRaw('poi.purchase_indent_item_id as indent_item_id, COALESCE(SUM(poi.quantity),0) as qty')
                ->groupBy('poi.purchase_indent_item_id')
                ->pluck('qty', 'indent_item_id');

            // Update line totals if columns exist
            $hasRfqTotal = Schema::hasColumn('purchase_indent_items', 'rfq_qty_total');
            $hasPoTotal  = Schema::hasColumn('purchase_indent_items', 'po_qty_total');
            $hasFulfill  = Schema::hasColumn('purchase_indent_items', 'fulfillment_status');

            $allOrdered = true;
            $anyOrdered = false;
            $anyRfq = false;

            foreach ($items as $line) {
                $requested = (float) ($line->order_qty ?? 0);
                $rfq = (float) ($rfqAlloc[$line->id] ?? 0);
                $po  = (float) ($poQty[$line->id] ?? 0);

                if ($rfq > 0) { $anyRfq = true; }
                if ($po > 0)  { $anyOrdered = true; }

                // determine ordered completion per line
                if ($requested > 0 && $po + 1e-9 < $requested) {
                    $allOrdered = false;
                }

                $updates = [];
                if ($hasRfqTotal) { $updates['rfq_qty_total'] = $rfq; }
                if ($hasPoTotal)  { $updates['po_qty_total'] = $po; }

                if ($hasFulfill) {
                    if ($requested > 0 && $po + 1e-9 >= $requested) {
                        $updates['fulfillment_status'] = 'ordered';
                    } elseif ($po > 0) {
                        $updates['fulfillment_status'] = 'partially_ordered';
                    } elseif ($rfq > 0) {
                        $updates['fulfillment_status'] = 'rfq_created';
                    } else {
                        $updates['fulfillment_status'] = 'open';
                    }
                }

                if (!empty($updates)) {
                    $updates['updated_at'] = now();
                    DB::table('purchase_indent_items')->where('id', $line->id)->update($updates);
                }
            }

            // Determine indent-level procurement_status
            $procStatus = 'open';
            if ($allOrdered && $anyOrdered) {
                $procStatus = 'ordered';
            } elseif ($anyOrdered) {
                $procStatus = 'partially_ordered';
            } elseif ($anyRfq) {
                $procStatus = 'rfq_created';
            }

            $this->updateIndentProcurementStatus($indentId, $procStatus);
        });
    }

    public function recalcAll(): void
    {
        if (!Schema::hasTable('purchase_indents')) {
            return;
        }

        $ids = DB::table('purchase_indents')
            ->when(Schema::hasColumn('purchase_indents', 'deleted_at'), function ($q) {
                $q->whereNull('deleted_at');
            })
            ->pluck('id');

        foreach ($ids as $id) {
            $this->recalcIndent((int) $id);
        }
    }

    private function updateIndentProcurementStatus(int $indentId, string $status): void
    {
        if (!Schema::hasColumn('purchase_indents', 'procurement_status')) {
            return;
        }

        DB::table('purchase_indents')->where('id', $indentId)->update([
            'procurement_status' => $status,
            'updated_at' => now(),
        ]);
    }
}
