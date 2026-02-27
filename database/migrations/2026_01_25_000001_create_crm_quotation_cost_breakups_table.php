<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_quotation_cost_breakups')) {
            return;
        }

        Schema::create('crm_quotation_cost_breakups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quotation_item_id')
                ->constrained('crm_quotation_items')
                ->cascadeOnDelete();

            // Optional code for mapping to a "standard head" list
            $table->string('component_code', 50)->nullable();

            // What the user sees/edits (e.g., Fabrication labour, Consumables, Painting labour, etc.)
            $table->string('component_name', 150);

            // per_unit = rate is per unit of the item UOM (e.g. Rs/kg or Rs/pc)
            // lumpsum  = rate is a total amount for this line item (converted to per-unit for pricing)
            // percent  = rate is % of base direct cost (excluding percent rows)
            $table->string('basis', 20)->default('per_unit');

            // Rate value in quotation currency
            $table->decimal('rate', 15, 2)->default(0);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['quotation_item_id', 'sort_order'], 'crm_qtn_cost_breakups_item_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quotation_cost_breakups');
    }
};
