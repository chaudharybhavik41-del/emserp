<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_receipt_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('material_receipt_lines', 'purchase_order_item_id')) {
                $table->foreignId('purchase_order_item_id')
                    ->nullable()
                    ->after('item_id')
                    ->constrained('purchase_order_items')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_receipt_lines', function (Blueprint $table) {
            if (Schema::hasColumn('material_receipt_lines', 'purchase_order_item_id')) {
                $table->dropForeign(['purchase_order_item_id']);
                $table->dropColumn('purchase_order_item_id');
            }
        });
    }
};
