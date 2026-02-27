<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_bill_allocations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('voucher_id');
            $table->unsignedBigInteger('voucher_line_id');
            $table->unsignedBigInteger('account_id');

            // Polymorphic bill reference (e.g. App\Models\PurchaseBill)
            $table->string('bill_type');
            $table->unsignedBigInteger('bill_id');

            // against / on_account / advance (for future extension)
            $table->string('mode')->default('against');

            $table->decimal('amount', 18, 2);

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('voucher_id')->references('id')->on('vouchers')->cascadeOnDelete();
            $table->foreign('voucher_line_id')->references('id')->on('voucher_lines')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();

            $table->index(['company_id', 'account_id']);
            $table->index(['bill_type', 'bill_id']);
            $table->unique(['voucher_line_id', 'bill_type', 'bill_id'], 'uq_bill_alloc_vline_bill');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_bill_allocations');
    }
};
