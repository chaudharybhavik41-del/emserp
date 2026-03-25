<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_payrolls', 'company_id')) {
                $table->unsignedBigInteger('company_id')
                    ->nullable()
                    ->after('id');
                $table->index('company_id', 'hr_payrolls_company_id_idx');
            }

            if (! Schema::hasColumn('hr_payrolls', 'accrual_voucher_id')) {
                $table->foreignId('accrual_voucher_id')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('vouchers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_payrolls', 'accrual_accounting_status')) {
                $table->string('accrual_accounting_status', 20)
                    ->default('pending')
                    ->after('accrual_voucher_id');
                $table->index('accrual_accounting_status', 'hr_payrolls_accrual_status_idx');
            }

            if (! Schema::hasColumn('hr_payrolls', 'accrual_posted_at')) {
                $table->timestamp('accrual_posted_at')
                    ->nullable()
                    ->after('accrual_accounting_status');
            }

            if (! Schema::hasColumn('hr_payrolls', 'payment_voucher_id')) {
                $table->foreignId('payment_voucher_id')
                    ->nullable()
                    ->after('accrual_posted_at')
                    ->constrained('vouchers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_payrolls', 'payment_accounting_status')) {
                $table->string('payment_accounting_status', 20)
                    ->default('pending')
                    ->after('payment_voucher_id');
                $table->index('payment_accounting_status', 'hr_payrolls_payment_status_idx');
            }

            if (! Schema::hasColumn('hr_payrolls', 'payment_posted_at')) {
                $table->timestamp('payment_posted_at')
                    ->nullable()
                    ->after('payment_accounting_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('hr_payrolls', 'payment_posted_at')) {
                $table->dropColumn('payment_posted_at');
            }

            if (Schema::hasColumn('hr_payrolls', 'payment_accounting_status')) {
                $table->dropIndex('hr_payrolls_payment_status_idx');
                $table->dropColumn('payment_accounting_status');
            }

            if (Schema::hasColumn('hr_payrolls', 'payment_voucher_id')) {
                $table->dropConstrainedForeignId('payment_voucher_id');
            }

            if (Schema::hasColumn('hr_payrolls', 'accrual_posted_at')) {
                $table->dropColumn('accrual_posted_at');
            }

            if (Schema::hasColumn('hr_payrolls', 'accrual_accounting_status')) {
                $table->dropIndex('hr_payrolls_accrual_status_idx');
                $table->dropColumn('accrual_accounting_status');
            }

            if (Schema::hasColumn('hr_payrolls', 'accrual_voucher_id')) {
                $table->dropConstrainedForeignId('accrual_voucher_id');
            }

            if (Schema::hasColumn('hr_payrolls', 'company_id')) {
                $table->dropIndex('hr_payrolls_company_id_idx');
                $table->dropColumn('company_id');
            }
        });
    }
};
