<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('items')) {
            return;
        }

        Schema::create('items', function (Blueprint $table) {
            $table->id();

            // Taxonomy & UOM
            $table->unsignedBigInteger('material_type_id');
            $table->unsignedBigInteger('material_category_id');
            $table->unsignedBigInteger('material_subcategory_id')->nullable();
            $table->unsignedBigInteger('uom_id');

            // Code & basic identity
            $table->string('code', 50)->unique();    // CAT-SUB-YYYY-SEQ4
            $table->string('name', 200);
            $table->string('short_name', 100)->nullable();

            // Optional spec fields (we can extend later)
            $table->string('grade', 100)->nullable();
            $table->string('spec', 150)->nullable();
            $table->string('thickness', 50)->nullable();
            $table->string('size', 100)->nullable(); // generic size text

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['material_type_id', 'material_category_id']);
            $table->index(['material_category_id', 'material_subcategory_id']);
            $table->index('uom_id');

            // Foreign keys
            $table->foreign('material_type_id')
                ->references('id')->on('material_types')
                ->restrictOnDelete();

            $table->foreign('material_category_id')
                ->references('id')->on('material_categories')
                ->restrictOnDelete();

            $table->foreign('material_subcategory_id')
                ->references('id')->on('material_subcategories')
                ->nullOnDelete();

            $table->foreign('uom_id')
                ->references('id')->on('uoms')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
}
