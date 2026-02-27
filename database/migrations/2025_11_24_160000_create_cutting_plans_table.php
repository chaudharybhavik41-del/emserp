<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cutting_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_id')->constrained('boms')->cascadeOnDelete();
            $table->string('grade', 50)->nullable();
            $table->unsignedInteger('thickness_mm');
            $table->string('name', 100);
            $table->string('status', 20)->default('draft'); // draft, final
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'bom_id']);
            $table->index(['grade', 'thickness_mm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cutting_plans');
    }
};
