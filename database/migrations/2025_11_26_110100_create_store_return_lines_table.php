<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_return_lines')) {
            return;
        }

        Schema::create('store_return_lines', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('store_return_id');
            $table->unsignedBigInteger('store_issue_line_id')->nullable();
            $table->unsignedBigInteger('store_stock_item_id');

            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('uom_id')->nullable();

            $table->integer('returned_qty_pcs')->default(1);
            $table->decimal('returned_weight_kg', 15, 3)->nullable();

            $table->string('remarks', 255)->nullable();

            $table->timestamps();

            $table->index('store_return_id');
            $table->index('store_stock_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_return_lines');
    }
};
