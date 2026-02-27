<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_debit_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('purchase_bill_id')->nullable();

            $table->string('note_number', 50);
            $table->date('note_date');
            $table->string('reference', 100)->nullable();
            $table->text('remarks')->nullable();

            $table->decimal('total_basic', 18, 2)->default(0);
            $table->decimal('total_cgst', 18, 2)->default(0);
            $table->decimal('total_sgst', 18, 2)->default(0);
            $table->decimal('total_igst', 18, 2)->default(0);
            $table->decimal('total_tax', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);

            $table->unsignedBigInteger('voucher_id')->nullable();

            $table->string('status', 20)->default('draft'); // draft|posted|cancelled
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'note_number']);
            $table->index(['company_id', 'supplier_id']);
            $table->index(['company_id', 'note_date']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('purchase_debit_note_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_debit_note_id');
            $table->unsignedInteger('line_no')->default(1);

            // This is the account that will be CREDITED (inventory/expense/return)
            $table->unsignedBigInteger('account_id');

            $table->string('description', 255)->nullable();
            $table->decimal('basic_amount', 18, 2)->default(0);

            $table->decimal('cgst_rate', 8, 3)->default(0);
            $table->decimal('sgst_rate', 8, 3)->default(0);
            $table->decimal('igst_rate', 8, 3)->default(0);

            $table->decimal('cgst_amount', 18, 2)->default(0);
            $table->decimal('sgst_amount', 18, 2)->default(0);
            $table->decimal('igst_amount', 18, 2)->default(0);

            $table->decimal('total_amount', 18, 2)->default(0);

            $table->timestamps();

            $table->index(['purchase_debit_note_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_debit_note_lines');
        Schema::dropIfExists('purchase_debit_notes');
    }
};
