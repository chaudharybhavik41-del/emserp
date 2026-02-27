<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_receipt_lines')) {
            Schema::create('material_receipt_lines', function (Blueprint $table) {
                $table->id();

                $table->foreignId('material_receipt_id')
                    ->constrained('material_receipts')
                    ->cascadeOnDelete();

                $table->foreignId('item_id')
                    ->constrained('items')
                    ->restrictOnDelete();

                // Material category: steel_plate, steel_section, consumable, bought_out
                $table->string('material_category', 50);

                // Geometry for plates & sections
                $table->unsignedInteger('thickness_mm')->nullable();
                $table->unsignedInteger('width_mm')->nullable();
                $table->unsignedInteger('length_mm')->nullable();

                // For sections (e.g. ISMB300, ISMC200, etc.)
                $table->string('section_profile', 100)->nullable();

                // Grade (e.g. IS2062 E250)
                $table->string('grade', 100)->nullable();

                // Quantities from invoice
                $table->unsignedInteger('qty_pcs')->default(1);
                $table->decimal('received_weight_kg', 12, 3)->nullable();

                $table->foreignId('uom_id')
                    ->constrained('uoms')
                    ->restrictOnDelete();

                // Line status: pending, accepted, rejected
                $table->string('status', 30)->default('pending');

                $table->text('remarks')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('material_receipt_lines');
    }
};
