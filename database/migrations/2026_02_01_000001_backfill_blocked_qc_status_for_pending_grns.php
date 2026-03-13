<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safety checks for environments where store module tables may not exist yet.
        if (
            ! Schema::hasTable('store_stock_items') ||
            ! Schema::hasTable('material_receipts') ||
            ! Schema::hasTable('material_receipt_lines')
        ) {
            return;
        }

        // Backfill: if a GRN is not QC passed, stock must be explicitly blocked.
        //
        // This fixes legacy data where status remained 'available' while availability was 0.
        //
        // Conditions:
        // - linked GRN is draft / qc_pending / qc_rejected
        // - stock status is NULL or 'available'
        // - no available qty (both pcs & weight are effectively zero)
        if (DB::getDriverName() === 'sqlite') {
            DB::table('store_stock_items')
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhere('status', 'available');
                })
                ->whereRaw('COALESCE(qty_pcs_available, 0) = 0')
                ->whereRaw('COALESCE(weight_kg_available, 0) = 0')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('material_receipt_lines as l')
                        ->join('material_receipts as h', 'h.id', '=', 'l.material_receipt_id')
                        ->whereColumn('l.id', 'store_stock_items.material_receipt_line_id')
                        ->whereIn('h.status', ['draft', 'qc_pending', 'qc_rejected']);
                })
                ->update(['status' => 'blocked_qc']);

            return;
        }

        DB::statement("
            UPDATE store_stock_items s
            JOIN material_receipt_lines l ON s.material_receipt_line_id = l.id
            JOIN material_receipts h ON l.material_receipt_id = h.id
            SET s.status = 'blocked_qc'
            WHERE h.status IN ('draft', 'qc_pending', 'qc_rejected')
              AND (s.status IS NULL OR s.status = 'available')
              AND COALESCE(s.qty_pcs_available, 0) = 0
              AND COALESCE(s.weight_kg_available, 0) = 0
        ");
    }

    public function down(): void
    {
        // Non-destructive migration (no down).
        // If needed, statuses can be corrected by re-running GRN QC status updates.
    }
};
