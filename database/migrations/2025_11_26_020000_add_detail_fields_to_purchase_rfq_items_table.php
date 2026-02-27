<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_rfq_items', function (Blueprint $table) {
            // Geometry & quantities (same concept as purchase_indent_items)
            $table->decimal('length_mm', 12, 2)->nullable()->after('line_no');
            $table->decimal('width_mm', 12, 2)->nullable()->after('length_mm');
            $table->decimal('thickness_mm', 10, 2)->nullable()->after('width_mm');

            // For sections (and general info)
            $table->decimal('weight_per_meter_kg', 12, 4)->nullable()->after('thickness_mm');

            // Pcs count (for both plate & section)
            $table->decimal('qty_pcs', 12, 3)->nullable()->after('quantity');

            // Grade snapshot
            $table->string('grade', 100)->nullable()->after('uom_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_rfq_items', function (Blueprint $table) {
            $table->dropColumn([
                'length_mm',
                'width_mm',
                'thickness_mm',
                'weight_per_meter_kg',
                'qty_pcs',
                'grade',
            ]);
        });
    }
};
