<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cutting_plan_plates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_plan_id')->constrained('cutting_plans')->cascadeOnDelete();
            $table->foreignId('material_stock_piece_id')
                ->nullable()
                ->constrained('material_stock_pieces')
                ->nullOnDelete();
            $table->string('plate_label', 50)->nullable(); // e.g. P1, P2
            $table->unsignedInteger('thickness_mm');
            $table->unsignedInteger('width_mm');
            $table->unsignedInteger('length_mm');
            $table->decimal('gross_area_m2', 12, 4)->nullable();
            $table->decimal('gross_weight_kg', 12, 3)->nullable();
            $table->string('source_type', 20)->nullable(); // stock, remnant, planned
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['cutting_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cutting_plan_plates');
    }
};
