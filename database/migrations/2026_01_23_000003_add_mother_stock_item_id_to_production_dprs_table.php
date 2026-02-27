<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_dprs') || ! Schema::hasTable('store_stock_items')) {
            return;
        }

        if (! Schema::hasColumn('production_dprs', 'mother_stock_item_id')) {
            Schema::table('production_dprs', function (Blueprint $table) {
                $table->foreignId('mother_stock_item_id')
                    ->nullable()
                    ->after('cutting_plan_id')
                    ->constrained('store_stock_items')
                    ->nullOnDelete();

                $table->index('mother_stock_item_id', 'idx_dpr_mother_stock');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('production_dprs')) {
            return;
        }

        if (Schema::hasColumn('production_dprs', 'mother_stock_item_id')) {
            Schema::table('production_dprs', function (Blueprint $table) {
                // drop FK + column
                $table->dropConstrainedForeignId('mother_stock_item_id');
                // drop index (ignore if missing)
                try {
                    $table->dropIndex('idx_dpr_mother_stock');
                } catch (\Throwable $e) {
                    // no-op
                }
            });
        }
    }
};
