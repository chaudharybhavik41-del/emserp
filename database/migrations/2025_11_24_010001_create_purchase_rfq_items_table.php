<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_rfq_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_rfq_id')
                ->constrained('purchase_rfqs')
                ->onDelete('cascade');

            $table->foreignId('item_id')->constrained('items');
            $table->unsignedInteger('line_no')->default(1);

            $table->decimal('quantity', 14, 3)->default(0);
            $table->foreignId('uom_id')->nullable()->constrained('uoms');

            $table->unsignedBigInteger('purchase_indent_item_id')->nullable()
                ->comment('Optional link back to indent item');

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['purchase_rfq_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_rfq_items');
    }
};
