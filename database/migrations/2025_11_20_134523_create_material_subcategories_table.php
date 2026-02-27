<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaterialSubcategoriesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('material_subcategories')) {
            return;
        }

        Schema::create('material_subcategories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_category_id');
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['material_category_id', 'code']);

            $table->foreign('material_category_id')
                ->references('id')->on('material_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_subcategories');
    }
}
