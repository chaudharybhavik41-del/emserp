<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('purchase_order_id');

            // Links back to RFQ & Indent for traceability
            $table->unsignedBigInteger('purchase_rfq_item_id')->nullable();
            $table->unsignedBigInteger('purchase_rfq_vendor_id')->nullable();
            $table->unsignedBigInteger('purchase_indent_item_id')->nullable();

            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedInteger('line_no')->nullable();

            // Geometry + qty same pattern as indent / RFQ
            $table->decimal('length_mm', 12, 2)->nullable();
            $table->decimal('width_mm', 12, 2)->nullable();
            $table->decimal('thickness_mm', 10, 2)->nullable();
            $table->decimal('weight_per_meter_kg', 12, 4)->nullable();
            $table->decimal('qty_pcs', 12, 3)->nullable();

            $table->decimal('quantity', 15, 3)->nullable(); // kg / main UOM
            $table->unsignedBigInteger('uom_id')->nullable();

            $table->string('grade', 100)->nullable();
            $table->text('description')->nullable();

            // Commercials
            $table->decimal('rate', 15, 3)->nullable();
            $table->decimal('discount_percent', 8, 3)->nullable();
            $table->decimal('tax_percent', 8, 3)->nullable();

            $table->decimal('amount', 15, 3)->nullable();    // qty * rate - discount
            $table->decimal('net_amount', 15, 3)->nullable(); // amount + tax

            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index('purchase_rfq_item_id');
            $table->index('purchase_rfq_vendor_id');
            $table->index('purchase_indent_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
