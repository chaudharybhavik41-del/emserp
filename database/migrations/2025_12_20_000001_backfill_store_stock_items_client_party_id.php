<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill client_party_id on client-supplied stock items created from GRNs.
        // Older code created StoreStockItem rows without setting client_party_id.

        if (! Schema::hasTable('store_stock_items') ||
            ! Schema::hasTable('material_receipt_lines') ||
            ! Schema::hasTable('material_receipts')) {
            return;
        }

        if (! Schema::hasColumn('store_stock_items', 'client_party_id') ||
            ! Schema::hasColumn('store_stock_items', 'material_receipt_line_id') ||
            ! Schema::hasColumn('store_stock_items', 'is_client_material')) {
            return;
        }

        // MySQL update-join to copy client_party_id from the GRN header.
        DB::statement(
            "UPDATE store_stock_items s\n" .
            "JOIN material_receipt_lines l ON s.material_receipt_line_id = l.id\n" .
            "JOIN material_receipts r ON l.material_receipt_id = r.id\n" .
            "SET s.client_party_id = r.client_party_id\n" .
            "WHERE s.is_client_material = 1\n" .
            "  AND s.client_party_id IS NULL\n" .
            "  AND r.client_party_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        // No rollback: this is a safe data backfill.
    }
};
