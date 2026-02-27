<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_plan_bars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_plan_id')->constrained()->cascadeOnDelete();
            $table->integer('length_mm');
            $table->integer('quantity');
            $table->string('remarks', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_plan_bars');
    }
};
