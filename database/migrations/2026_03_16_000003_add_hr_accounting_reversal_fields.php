<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_payrolls', 'accrual_reversal_voucher_id')) {
                $table->foreignId('accrual_reversal_voucher_id')
                    ->nullable()
                    ->after('accrual_voucher_id')
                    ->constrained('vouchers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_payrolls', 'accrual_reversed_at')) {
                $table->timestamp('accrual_reversed_at')->nullable()->after('accrual_posted_at');
            }

            if (! Schema::hasColumn('hr_payrolls', 'accrual_reversal_reason')) {
                $table->text('accrual_reversal_reason')->nullable()->after('accrual_reversed_at');
            }

            if (! Schema::hasColumn('hr_payrolls', 'payment_reversal_voucher_id')) {
                $table->foreignId('payment_reversal_voucher_id')
                    ->nullable()
                    ->after('payment_voucher_id')
                    ->constrained('vouchers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_payrolls', 'payment_reversed_at')) {
                $table->timestamp('payment_reversed_at')->nullable()->after('payment_posted_at');
            }

            if (! Schema::hasColumn('hr_payrolls', 'payment_reversal_reason')) {
                $table->text('payment_reversal_reason')->nullable()->after('payment_reversed_at');
            }
        });

        Schema::table('hr_employee_loans', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_employee_loans', 'disbursement_reversal_voucher_id')) {
                $table->foreignId('disbursement_reversal_voucher_id')
                    ->nullable()
                    ->after('disbursement_voucher_id')
                    ->constrained('vouchers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_employee_loans', 'disbursement_reversed_at')) {
                $table->timestamp('disbursement_reversed_at')->nullable()->after('disbursement_posted_at');
            }

            if (! Schema::hasColumn('hr_employee_loans', 'disbursement_reversal_reason')) {
                $table->text('disbursement_reversal_reason')->nullable()->after('disbursement_reversed_at');
            }
        });

        Schema::table('hr_salary_advances', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_salary_advances', 'disbursement_reversal_voucher_id')) {
                $table->foreignId('disbursement_reversal_voucher_id')
                    ->nullable()
                    ->after('disbursement_voucher_id')
                    ->constrained('vouchers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_salary_advances', 'disbursement_reversed_at')) {
                $table->timestamp('disbursement_reversed_at')->nullable()->after('disbursement_posted_at');
            }

            if (! Schema::hasColumn('hr_salary_advances', 'disbursement_reversal_reason')) {
                $table->text('disbursement_reversal_reason')->nullable()->after('disbursement_reversed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_salary_advances', function (Blueprint $table) {
            if (Schema::hasColumn('hr_salary_advances', 'disbursement_reversal_reason')) {
                $table->dropColumn('disbursement_reversal_reason');
            }

            if (Schema::hasColumn('hr_salary_advances', 'disbursement_reversed_at')) {
                $table->dropColumn('disbursement_reversed_at');
            }

            if (Schema::hasColumn('hr_salary_advances', 'disbursement_reversal_voucher_id')) {
                $table->dropConstrainedForeignId('disbursement_reversal_voucher_id');
            }
        });

        Schema::table('hr_employee_loans', function (Blueprint $table) {
            if (Schema::hasColumn('hr_employee_loans', 'disbursement_reversal_reason')) {
                $table->dropColumn('disbursement_reversal_reason');
            }

            if (Schema::hasColumn('hr_employee_loans', 'disbursement_reversed_at')) {
                $table->dropColumn('disbursement_reversed_at');
            }

            if (Schema::hasColumn('hr_employee_loans', 'disbursement_reversal_voucher_id')) {
                $table->dropConstrainedForeignId('disbursement_reversal_voucher_id');
            }
        });

        Schema::table('hr_payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('hr_payrolls', 'payment_reversal_reason')) {
                $table->dropColumn('payment_reversal_reason');
            }

            if (Schema::hasColumn('hr_payrolls', 'payment_reversed_at')) {
                $table->dropColumn('payment_reversed_at');
            }

            if (Schema::hasColumn('hr_payrolls', 'payment_reversal_voucher_id')) {
                $table->dropConstrainedForeignId('payment_reversal_voucher_id');
            }

            if (Schema::hasColumn('hr_payrolls', 'accrual_reversal_reason')) {
                $table->dropColumn('accrual_reversal_reason');
            }

            if (Schema::hasColumn('hr_payrolls', 'accrual_reversed_at')) {
                $table->dropColumn('accrual_reversed_at');
            }

            if (Schema::hasColumn('hr_payrolls', 'accrual_reversal_voucher_id')) {
                $table->dropConstrainedForeignId('accrual_reversal_voucher_id');
            }
        });
    }
};
