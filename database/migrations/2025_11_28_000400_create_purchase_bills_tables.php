<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('supplier_id'); // FK to parties
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->string('bill_number', 100);
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->string('reference_no', 100)->nullable(); // challan / LR no
            $table->text('remarks')->nullable();

            $table->decimal('total_basic', 15, 2)->default(0);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->string('currency', 10)->default('INR');
            $table->decimal('exchange_rate', 15, 6)->default(1);

            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->string('status', 20)->default('draft'); // draft, posted, cancelled

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index(['supplier_id', 'bill_date']);
            $table->index('purchase_order_id');
        });

        Schema::create('purchase_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_bill_id');
            $table->unsignedBigInteger('material_receipt_id')->nullable();
            $table->unsignedBigInteger('material_receipt_line_id')->nullable();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('uom_id')->nullable();

            $table->decimal('qty', 15, 3)->default(0);
            $table->decimal('rate', 15, 4)->default(0);
            $table->decimal('discount_percent', 8, 4)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('basic_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->unsignedBigInteger('account_id')->nullable(); // optional GL override
            $table->unsignedInteger('line_no')->default(1);

            $table->timestamps();

            $table->foreign('purchase_bill_id')
                ->references('id')
                ->on('purchase_bills')
                ->cascadeOnDelete();

            $table->index(
  			  ['material_receipt_id', 'material_receipt_line_id'],
  			  'pbill_lines_mr_mrl_idx'
				);
			$table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_bill_lines');
        Schema::dropIfExists('purchase_bills');
    }
};
