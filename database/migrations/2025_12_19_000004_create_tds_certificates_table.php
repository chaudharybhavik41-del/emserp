<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tds_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');

            // receivable = client deducted TDS; payable = we deducted TDS on supplier/subcontractor payments
            $table->enum('direction', ['receivable', 'payable'])->default('receivable');

            // Source document
            $table->unsignedBigInteger('voucher_id')->nullable();

            // Counterparty ledger
            $table->unsignedBigInteger('party_account_id')->nullable();

            // Section master (code), rate and amount
            $table->string('tds_section', 20)->nullable();
            $table->decimal('tds_rate', 8, 4)->nullable();
            $table->decimal('tds_amount', 18, 2)->default(0);

            // Certificate details (Form 16A / 16 etc)
            $table->string('certificate_no', 100)->nullable();
            $table->date('certificate_date')->nullable();

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('voucher_id')->references('id')->on('vouchers')->nullOnDelete();
            $table->foreign('party_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['company_id', 'direction']);
            $table->index(['company_id', 'direction', 'certificate_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tds_certificates');
    }
};
