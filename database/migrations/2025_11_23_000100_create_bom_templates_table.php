<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_code')->unique();
            $table->string('name');
            $table->string('structure_type')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('total_weight', 12, 3)->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'structure_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_templates');
    }
};
