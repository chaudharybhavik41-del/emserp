<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_stock_adjustment_lines')) {
            Schema::create('store_stock_adjustment_lines', function (Blueprint $table) {
                $table->id();

                $table->foreignId('store_stock_adjustment_id')
                    ->constrained('store_stock_adjustments')
                    ->cascadeOnDelete();

                $table->foreignId('store_stock_item_id')
                    ->nullable()
                    ->constrained('store_stock_items')
                    ->nullOnDelete();

                $table->foreignId('item_id')
                    ->constrained('items')
                    ->restrictOnDelete();

                $table->foreignId('uom_id')
                    ->nullable()
                    ->constrained('uoms')
                    ->nullOnDelete();

                $table->foreignId('project_id')
                    ->nullable()
                    ->constrained('projects')
                    ->nullOnDelete();

                // For now we only support a single logical quantity field (same logic as GRN quantity)
                $table->decimal('quantity', 12, 3)->nullable();
                $table->decimal('quantity_pcs', 12, 3)->nullable();

                $table->text('remarks')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_stock_adjustment_lines');
    }
};
