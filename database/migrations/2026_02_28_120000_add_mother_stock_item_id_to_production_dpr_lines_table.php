<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_dpr_lines') || ! Schema::hasTable('store_stock_items')) {
            return;
        }

        if (Schema::hasColumn('production_dpr_lines', 'mother_stock_item_id')) {
            return;
        }

        Schema::table('production_dpr_lines', function (Blueprint $table) {
            $table->foreignId('mother_stock_item_id')
                ->nullable()
                ->after('production_plan_item_activity_id')
                ->constrained('store_stock_items')
                ->nullOnDelete();

            $table->index('mother_stock_item_id', 'idx_dpr_line_mother_stock');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('production_dpr_lines') || ! Schema::hasColumn('production_dpr_lines', 'mother_stock_item_id')) {
            return;
        }

        Schema::table('production_dpr_lines', function (Blueprint $table) {
            try {
                $table->dropConstrainedForeignId('mother_stock_item_id');
            } catch (\Throwable $e) {
                if (Schema::hasColumn('production_dpr_lines', 'mother_stock_item_id')) {
                    $table->dropColumn('mother_stock_item_id');
                }
            }
        });
    }
};

