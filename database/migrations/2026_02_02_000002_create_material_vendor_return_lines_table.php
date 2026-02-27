<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_vendor_return_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('material_vendor_return_id')
                ->constrained('material_vendor_returns')
                ->cascadeOnDelete();

            $table->foreignId('material_receipt_line_id')
                ->constrained('material_receipt_lines')
                ->cascadeOnDelete();

            $table->foreignId('store_stock_item_id')
                ->constrained('store_stock_items')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->cascadeOnDelete();

            $table->string('material_category', 50)->nullable();

            $table->unsignedInteger('returned_qty_pcs')->default(0);
            $table->decimal('returned_weight_kg', 12, 3)->default(0);

            $table->string('remarks', 255)->nullable();

            $table->timestamps();

            $table->index(['material_receipt_line_id']);
            $table->index(['item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_vendor_return_lines');
    }
};
