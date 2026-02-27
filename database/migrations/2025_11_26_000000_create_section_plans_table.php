<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('section_profile', 100);
            $table->string('grade', 100)->nullable();
            $table->string('name')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(
                ['project_id', 'bom_id', 'section_profile', 'grade'],
                'section_plans_unique_group_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_plans');
    }
};
