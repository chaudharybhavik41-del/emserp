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

                // For opening entries this will point to the stock row we create
                // For increase/decrease it points to the existing stock row being adjusted
                $table->foreignId('store_stock_item_id')
                    ->nullable()
                    ->constrained('store_stock_items')
                    ->nullOnDelete();

                $table->foreignId('item_id')
                    ->constrained('items')
                    ->restrictOnDelete();

                $table->foreignId('uom_id')
                    ->constrained('uoms')
                    ->restrictOnDelete();

                $table->foreignId('project_id')
                    ->nullable()
                    ->constrained('projects')
                    ->nullOnDelete();

                // Signed quantity: positive for opening/increase, negative for decrease
                $table->decimal('quantity', 12, 3);

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
