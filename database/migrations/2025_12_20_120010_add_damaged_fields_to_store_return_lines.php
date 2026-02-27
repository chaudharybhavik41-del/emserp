<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_return_lines')) return;

        Schema::table('store_return_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('store_return_lines', 'damaged_qty_pcs')) {
                $table->integer('damaged_qty_pcs')->default(0)->after('returned_qty_pcs');
            }
            if (! Schema::hasColumn('store_return_lines', 'damaged_weight_kg')) {
                $table->decimal('damaged_weight_kg', 15, 3)->nullable()->after('returned_weight_kg');
            }
            if (! Schema::hasColumn('store_return_lines', 'damage_reason')) {
                $table->string('damage_reason', 255)->nullable()->after('remarks');
            }
            if (! Schema::hasColumn('store_return_lines', 'scrap_stock_item_id')) {
                $table->unsignedBigInteger('scrap_stock_item_id')->nullable()->after('store_stock_item_id');
                $table->index('scrap_stock_item_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('store_return_lines')) return;

        Schema::table('store_return_lines', function (Blueprint $table) {
            if (Schema::hasColumn('store_return_lines', 'scrap_stock_item_id')) {
                $table->dropIndex(['scrap_stock_item_id']);
                $table->dropColumn('scrap_stock_item_id');
            }
            if (Schema::hasColumn('store_return_lines', 'damage_reason')) {
                $table->dropColumn('damage_reason');
            }
            if (Schema::hasColumn('store_return_lines', 'damaged_weight_kg')) {
                $table->dropColumn('damaged_weight_kg');
            }
            if (Schema::hasColumn('store_return_lines', 'damaged_qty_pcs')) {
                $table->dropColumn('damaged_qty_pcs');
            }
        });
    }
};
