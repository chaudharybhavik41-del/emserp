<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_rfq_vendor_quotes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_rfq_vendor_id')
                ->constrained('purchase_rfq_vendors')
                ->cascadeOnDelete();

            $table->foreignId('purchase_rfq_item_id')
                ->constrained('purchase_rfq_items')
                ->cascadeOnDelete();

            $table->decimal('rate', 15, 3)->nullable();
            $table->decimal('discount_percent', 8, 3)->nullable();
            $table->decimal('tax_percent', 8, 3)->nullable();

            $table->unsignedInteger('delivery_days')->nullable();
            $table->date('valid_till')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            // Short index name to avoid MySQL 64-char limit
            $table->index(
                ['purchase_rfq_vendor_id', 'purchase_rfq_item_id'],
                'rfq_vendor_item_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_rfq_vendor_quotes');
    }
};
