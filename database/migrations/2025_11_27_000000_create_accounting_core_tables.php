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
        // Chart of accounts groups
        Schema::create('account_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('nature'); // asset, liability, income, expense, equity, off_balance
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('account_groups')->nullOnDelete();
        });

        // Ledgers / accounts
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('account_group_id');
            $table->string('name');
            $table->string('code')->nullable(); // unique per company recommended
            $table->string('type')->default('ledger'); // ledger, debtor, creditor, bank, tax, inventory, wip, etc.
            $table->boolean('is_active')->default(true);
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->enum('opening_balance_type', ['dr', 'cr'])->default('dr');
            $table->string('gstin')->nullable();
            $table->string('pan')->nullable();
            $table->decimal('credit_limit', 18, 2)->nullable();
            $table->integer('credit_days')->nullable();
            $table->nullableMorphs('related_model'); // link to parties, banks, etc.
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('account_group_id')->references('id')->on('account_groups')->cascadeOnDelete();
            $table->unique(['company_id', 'code']);
        });

        // Cost centers (mostly projects now, but extendable)
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->unique(['company_id', 'code']);
        });

        // Currencies
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // INR, USD, EUR
            $table->string('symbol', 8)->nullable();
            $table->string('name');
            $table->timestamps();
        });

        // Exchange rates to base currency
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('currency_id');
            $table->date('rate_date');
            $table->decimal('rate_to_base', 18, 6);
            $table->timestamps();

            $table->foreign('currency_id')->references('id')->on('currencies')->cascadeOnDelete();
            $table->unique(['currency_id', 'rate_date']);
        });

        // Vouchers (document header)
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('voucher_no');
            $table->string('voucher_type'); // journal, purchase, sales, payment, receipt, contra, ra_subcontractor, ra_client, etc.
            $table->date('voucher_date');
            $table->string('reference')->nullable();
            $table->text('narration')->nullable();
            $table->unsignedBigInteger('project_id')->nullable(); // shortcut link
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('amount_base', 18, 2)->default(0); // total absolute amount in base currency
            $table->string('status')->default('draft'); // draft, approved, posted, cancelled
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['company_id', 'voucher_no', 'voucher_type']);
        });

        // Voucher lines (double-entry rows)
        Schema::create('voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_id');
            $table->integer('line_no')->default(1);
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->string('description')->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->nullableMorphs('reference'); // link to GRN, RA bill, invoice etc.
            $table->timestamps();

            $table->foreign('voucher_id')->references('id')->on('vouchers')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->nullOnDelete();
        });

        // Tax master
        Schema::create('tax_masters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tax_type'); // CGST, SGST, IGST, CESS, TDS, OTHER
            $table->decimal('tax_rate', 5, 2); // 0.00 to 100.00
            $table->string('applicable_on')->default('both'); // inventory, services, both
            $table->boolean('is_input_allowed')->default(true);
            $table->boolean('is_reverse_charge')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tax configuration mapping to accounts & document types
        Schema::create('tax_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tax_master_id');
            $table->unsignedBigInteger('output_account_id')->nullable(); // GST Output ledger
            $table->unsignedBigInteger('input_account_id')->nullable();  // GST Input ledger
            $table->json('applies_to_document_types')->nullable(); // ["purchase", "sales", "ra_bill", ...]
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('tax_master_id')->references('id')->on('tax_masters')->cascadeOnDelete();
            $table->foreign('output_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('input_account_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_configurations');
        Schema::dropIfExists('tax_masters');
        Schema::dropIfExists('voucher_lines');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('account_groups');
    }
};
