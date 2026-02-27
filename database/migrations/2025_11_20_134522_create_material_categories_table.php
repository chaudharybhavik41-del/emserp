<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaterialCategoriesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('material_categories')) {
            return;
        }

        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_type_id');
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['material_type_id', 'code']);
            // Foreign key will be added in a separate migration once material_types exists
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_categories');
    }
}
