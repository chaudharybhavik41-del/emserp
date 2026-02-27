<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_indent_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_indent_id')
                ->constrained('purchase_indents')
                ->onDelete('cascade');

            $table->foreignId('item_id')->constrained('items');
            $table->unsignedInteger('line_no')->default(1)->comment('Line number within indent');

            // Source of requirement
            $table->string('origin_type', 20)->default('DIRECT'); // BOM/DIRECT/MINMAX
            $table->unsignedBigInteger('origin_id')->nullable()->comment('bom_item_id or stock_reorder_id, etc.');

            // Geometry & calculation fields (for RAW materials)
            $table->decimal('length_mm', 12, 2)->nullable();
            $table->decimal('width_mm', 12, 2)->nullable();
            $table->decimal('thickness_mm', 10, 2)->nullable();

            $table->decimal('density_kg_per_m3', 10, 2)->nullable();
            $table->decimal('weight_per_meter_kg', 12, 4)->nullable();
            $table->decimal('weight_per_piece_kg', 14, 4)->nullable();

            // Quantities
            $table->decimal('qty_pcs', 12, 3)->nullable()
                ->comment('Number of pieces (for raw material)');

            $table->decimal('order_qty', 14, 3)->default(0)
                ->comment('Order quantity in UOM (for raw this is total weight in KG)');

            $table->foreignId('uom_id')->nullable()->constrained('uoms');

            // Descriptive
            $table->string('grade', 100)->nullable();
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['purchase_indent_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_indent_items');
    }
};
