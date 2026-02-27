<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_stock_items')) {
            Schema::create('store_stock_items', function (Blueprint $table) {
                $table->id();

                $table->foreignId('material_receipt_line_id')
                    ->nullable()
                    ->constrained('material_receipt_lines')
                    ->nullOnDelete();

                $table->foreignId('item_id')
                    ->constrained('items')
                    ->restrictOnDelete();

                $table->foreignId('project_id')
                    ->nullable()
                    ->constrained('projects')
                    ->nullOnDelete();

                $table->boolean('is_client_material')->default(false);

                // Material category: steel_plate, steel_section, consumable, bought_out
                $table->string('material_category', 50);

                // Geometry (for plates & sections)
                $table->unsignedInteger('thickness_mm')->nullable();
                $table->unsignedInteger('width_mm')->nullable();
                $table->unsignedInteger('length_mm')->nullable();

                // For sections
                $table->string('section_profile', 100)->nullable();

                $table->string('grade', 100)->nullable();

                // Traceability identifiers
                $table->string('plate_number', 50)->nullable();
                $table->string('heat_number', 100)->nullable();
                $table->string('mtc_number', 100)->nullable();

                // Quantities per stock row (usually per plate / batch)
                $table->unsignedInteger('qty_pcs_total')->default(1);
                $table->unsignedInteger('qty_pcs_available')->default(1);

                $table->decimal('weight_kg_total', 12, 3)->nullable();
                $table->decimal('weight_kg_available', 12, 3)->nullable();

                // Source: purchase_grn, client_grn, etc.
                $table->string('source_type', 50)->nullable();
                $table->string('source_reference', 100)->nullable();

                // Stock status: available, reserved, consumed, scrap, blocked_qc
                $table->string('status', 30)->default('available');

                $table->string('location', 100)->nullable();
                $table->text('remarks')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_stock_items');
    }
};
