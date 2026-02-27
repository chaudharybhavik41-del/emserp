<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)->unique();

            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('vendor_party_id')->nullable();

            $table->unsignedBigInteger('purchase_rfq_id')->nullable();
            $table->unsignedBigInteger('purchase_indent_id')->nullable();

            $table->date('po_date')->nullable();
            $table->date('expected_delivery_date')->nullable();

            $table->unsignedInteger('payment_terms_days')->nullable();
            $table->unsignedInteger('delivery_terms_days')->nullable();
            $table->string('freight_terms', 255)->nullable();

            $table->decimal('total_amount', 15, 3)->nullable();

            $table->string('status', 20)->default('draft'); // draft / approved / cancelled

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();

            $table->timestamps();

            // simple indexes (FK constraints optional as per your style)
            $table->index('project_id');
            $table->index('department_id');
            $table->index('vendor_party_id');
            $table->index('purchase_rfq_id');
            $table->index('purchase_indent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
