<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_bill_expense_lines', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('purchase_bill_id');
            $table->unsignedBigInteger('account_id');

            $table->string('description', 255)->nullable();

            // Amounts: follow same style as other monetary columns
            $table->decimal('basic_amount', 15, 2)->default(0);   // taxable base
            $table->decimal('tax_rate', 5, 2)->default(0);        // % (IGST-equivalent)
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);   // basic + tax

            $table->unsignedInteger('line_no')->default(1);

            $table->timestamps();

            $table->index('purchase_bill_id');
            $table->index('account_id');

            $table->foreign('purchase_bill_id')
                  ->references('id')->on('purchase_bills')
                  ->onDelete('cascade');

            $table->foreign('account_id')
                  ->references('id')->on('accounts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_bill_expense_lines');
    }
};
