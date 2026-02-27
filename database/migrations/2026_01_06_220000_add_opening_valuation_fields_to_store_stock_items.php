<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_stock_items')) {
            return;
        }

        Schema::table('store_stock_items', function (Blueprint $table) {
            // Opening valuation rate (per item UOM). Used for valued opening entries.
            if (! Schema::hasColumn('store_stock_items', 'opening_unit_rate')) {
                $table->decimal('opening_unit_rate', 15, 4)
                    ->nullable()
                    ->after('source_reference');
            }

            // Snapshot of rate basis uom (optional, usually same as item.uom_id).
            if (! Schema::hasColumn('store_stock_items', 'opening_rate_uom_id')) {
                $table->foreignId('opening_rate_uom_id')
                    ->nullable()
                    ->after('opening_unit_rate')
                    ->constrained('uoms')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('store_stock_items')) {
            return;
        }

        Schema::table('store_stock_items', function (Blueprint $table) {
            if (Schema::hasColumn('store_stock_items', 'opening_rate_uom_id')) {
                try {
                    $table->dropConstrainedForeignId('opening_rate_uom_id');
                } catch (\Throwable $e) {
                    $table->dropColumn('opening_rate_uom_id');
                }
            }

            if (Schema::hasColumn('store_stock_items', 'opening_unit_rate')) {
                $table->dropColumn('opening_unit_rate');
            }
        });
    }
};
