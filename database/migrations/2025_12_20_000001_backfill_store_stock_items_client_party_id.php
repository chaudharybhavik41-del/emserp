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

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "UPDATE store_stock_items s\n" .
                "JOIN material_receipt_lines l ON s.material_receipt_line_id = l.id\n" .
                "JOIN material_receipts r ON l.material_receipt_id = r.id\n" .
                "SET s.client_party_id = r.client_party_id\n" .
                "WHERE s.is_client_material = 1\n" .
                "  AND s.client_party_id IS NULL\n" .
                "  AND r.client_party_id IS NOT NULL"
            );

            return;
        }

        // SQLite/Postgres-compatible correlated update.
        DB::statement(
            "UPDATE store_stock_items\n" .
            "SET client_party_id = (\n" .
            "  SELECT r.client_party_id\n" .
            "  FROM material_receipt_lines l\n" .
            "  JOIN material_receipts r ON l.material_receipt_id = r.id\n" .
            "  WHERE l.id = store_stock_items.material_receipt_line_id\n" .
            "  LIMIT 1\n" .
            ")\n" .
            "WHERE is_client_material = 1\n" .
            "  AND client_party_id IS NULL\n" .
            "  AND material_receipt_line_id IS NOT NULL\n" .
            "  AND EXISTS (\n" .
            "    SELECT 1\n" .
            "    FROM material_receipt_lines l2\n" .
            "    JOIN material_receipts r2 ON l2.material_receipt_id = r2.id\n" .
            "    WHERE l2.id = store_stock_items.material_receipt_line_id\n" .
            "      AND r2.client_party_id IS NOT NULL\n" .
            "  )"
        );
    }

    public function down(): void
    {
        // No rollback: this is a safe data backfill.
    }
};
