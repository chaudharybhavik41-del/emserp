<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCrmQuotationItemsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_quotation_items')) {
            return;
        }

        Schema::create('crm_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('crm_quotations');
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->text('description');
            $table->decimal('quantity', 15, 3)->default(0);
            $table->foreignId('uom_id')->nullable()->constrained('uoms');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quotation_items');
    }
}
