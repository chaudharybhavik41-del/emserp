<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_stock_adjustment_lines')) {
            return;
        }

        Schema::table('store_stock_adjustment_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('store_stock_adjustment_lines', 'brand')) {
                $table->string('brand', 100)->nullable()->after('item_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('store_stock_adjustment_lines')) {
            return;
        }

        Schema::table('store_stock_adjustment_lines', function (Blueprint $table) {
            if (Schema::hasColumn('store_stock_adjustment_lines', 'brand')) {
                $table->dropColumn('brand');
            }
        });
    }
};
