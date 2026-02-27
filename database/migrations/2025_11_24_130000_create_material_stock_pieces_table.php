<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_stock_pieces')) {
            Schema::create('material_stock_pieces', function (Blueprint $table) {
                $table->id();

                // Link to Item master (grade, density, etc. live there)
                $table->foreignId('item_id')
                    ->constrained('items')
                    ->restrictOnDelete();

                // Raw material category: steel_plate / steel_section
                // Stored as string, cast to BomItemMaterialCategory enum in the model
                $table->string('material_category', 50);

                // Geometry (plates & sections share these fields)
                $table->unsignedInteger('thickness_mm')->nullable(); // for plates
                $table->unsignedInteger('width_mm')->nullable();     // for plates
                $table->unsignedInteger('length_mm')->nullable();    // plates & sections length

                // For sections: ISMB300, ISA75x75x6, etc.
                $table->string('section_profile', 100)->nullable();

                // Calculated weight in kg (from geometry + item.density/weight_per_meter)
                $table->decimal('weight_kg', 12, 3)->nullable();

                // Traceability identifiers
                $table->string('plate_number', 50)->nullable();
                $table->string('heat_number', 100)->nullable();
                $table->string('mtc_number', 100)->nullable();

                // Where this piece originally came from (optional)
                $table->foreignId('origin_project_id')
                    ->nullable()
                    ->constrained('projects')
                    ->nullOnDelete();

                $table->foreignId('origin_bom_id')
                    ->nullable()
                    ->constrained('boms')
                    ->nullOnDelete();

                // Mother-child chain for remnants
                $table->foreignId('mother_piece_id')
                    ->nullable()
                    ->constrained('material_stock_pieces')
                    ->nullOnDelete();

                // Status: available / reserved / consumed / scrap
                // Cast to MaterialStockPieceStatus enum in the model
                $table->string('status', 30)->default('available');

                // Reservation info (for material planning wizard later)
                $table->foreignId('reserved_for_project_id')
                    ->nullable()
                    ->constrained('projects')
                    ->nullOnDelete();

                $table->foreignId('reserved_for_bom_id')
                    ->nullable()
                    ->constrained('boms')
                    ->nullOnDelete();

                // Generic source fields until GRN / store module exists
                // Later, store module can populate these from GRN / PO / Invoice
                $table->string('source_type', 50)->nullable();       // e.g. 'manual', 'grn'
                $table->string('source_reference', 100)->nullable(); // e.g. GRN no / invoice no

                // Store / location info
                $table->string('location', 100)->nullable();
                $table->text('remarks')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes for searching in wizard / remnant library
                $table->index(['item_id', 'material_category']);
                $table->index(['material_category', 'thickness_mm']);
                $table->index(['status', 'material_category']);
                $table->index('heat_number');
                $table->index('plate_number');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stock_pieces');
    }
};
