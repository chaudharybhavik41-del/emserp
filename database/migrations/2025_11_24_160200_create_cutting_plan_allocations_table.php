<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cutting_plan_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_plan_plate_id')
                ->constrained('cutting_plan_plates')
                ->cascadeOnDelete();
            $table->foreignId('bom_item_id')
                ->constrained('bom_items')
                ->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('used_area_m2', 12, 4)->nullable();
            $table->decimal('used_weight_kg', 12, 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cutting_plan_plate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cutting_plan_allocations');
    }
};
