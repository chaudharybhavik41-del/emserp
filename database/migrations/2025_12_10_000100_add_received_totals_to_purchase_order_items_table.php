<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_items', 'qty_pcs_received')) {
                $table->decimal('qty_pcs_received', 15, 3)
                    ->default(0)
                    ->after('qty_pcs');
            }

            if (! Schema::hasColumn('purchase_order_items', 'quantity_received')) {
                $table->decimal('quantity_received', 15, 3)
                    ->default(0)
                    ->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'qty_pcs_received')) {
                $table->dropColumn('qty_pcs_received');
            }

            if (Schema::hasColumn('purchase_order_items', 'quantity_received')) {
                $table->dropColumn('quantity_received');
            }
        });
    }
};
