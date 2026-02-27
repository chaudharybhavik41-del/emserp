<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('material_receipt_lines') && ! Schema::hasColumn('material_receipt_lines', 'brand')) {
            Schema::table('material_receipt_lines', function (Blueprint $table) {
                $table->string('brand', 100)->nullable()->after('item_id');
            });
        }

        if (Schema::hasTable('store_stock_items') && ! Schema::hasColumn('store_stock_items', 'brand')) {
            Schema::table('store_stock_items', function (Blueprint $table) {
                $table->string('brand', 100)->nullable()->after('item_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('material_receipt_lines') && Schema::hasColumn('material_receipt_lines', 'brand')) {
            Schema::table('material_receipt_lines', function (Blueprint $table) {
                $table->dropColumn('brand');
            });
        }

        if (Schema::hasTable('store_stock_items') && Schema::hasColumn('store_stock_items', 'brand')) {
            Schema::table('store_stock_items', function (Blueprint $table) {
                $table->dropColumn('brand');
            });
        }
    }
};
