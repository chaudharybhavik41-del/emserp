<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_items', 'grn_tolerance_percent')) {
                $table->decimal('grn_tolerance_percent', 5, 2)
                    ->nullable()
                    ->comment('Allowed GRN over-receipt tolerance in percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'grn_tolerance_percent')) {
                $table->dropColumn('grn_tolerance_percent');
            }
        });
    }
};
