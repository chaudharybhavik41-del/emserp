<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bom_items')) {
            Schema::create('bom_items', function (Blueprint $table) {
                $table->id();

                $table->foreignId('bom_id')
                    ->constrained('boms')
                    ->restrictOnDelete();

                $table->foreignId('parent_item_id')
                    ->nullable()
                    ->constrained('bom_items')
                    ->nullOnDelete();

                $table->unsignedInteger('level')->default(0);

                $table->string('item_code')->nullable();
                $table->text('description')->nullable();

                $table->string('assembly_type')->nullable();
                $table->unsignedInteger('sequence_no')->default(0);
                $table->string('drawing_number')->nullable();
                $table->string('drawing_revision')->nullable();

                $table->string('material_category', 50);

                $table->foreignId('item_id')
                    ->nullable()
                    ->constrained('items')
                    ->nullOnDelete();

                $table->foreignId('uom_id')
                    ->nullable()
                    ->constrained('uoms')
                    ->nullOnDelete();

                $table->json('dimensions')->nullable();

                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_weight', 10, 3)->nullable();
                $table->decimal('total_weight', 10, 3)->nullable();
                $table->decimal('scrap_percentage', 5, 2)->default(0);

                $table->string('procurement_type', 20)->nullable();
                $table->string('material_source', 30)->nullable();

                $table->text('remarks')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['bom_id', 'parent_item_id', 'level']);
                $table->index('material_category');
                $table->index('procurement_type');
                $table->index('material_source');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_items');
    }
};
