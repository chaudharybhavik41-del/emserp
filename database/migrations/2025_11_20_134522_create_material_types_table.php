<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaterialTypesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('material_types')) {
            return;
        }

        Schema::create('material_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();      // RAW, CONSUMABLE, etc.
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_types');
    }
}
