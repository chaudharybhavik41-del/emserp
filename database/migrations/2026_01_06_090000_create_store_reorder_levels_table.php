<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_reorder_levels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')->constrained('items');
            $table->string('brand', 100)->nullable()->comment('If NULL, threshold applies to all brands');
            $table->foreignId('project_id')->nullable()->constrained('projects');

            // Quantities in item UOM (for store we use weight_kg_available as generic qty)
            $table->decimal('min_qty', 14, 3)->default(0);
            $table->decimal('target_qty', 14, 3)->default(0);

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();

            $table->index(['item_id', 'brand', 'project_id']);
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_reorder_levels');
    }
};
