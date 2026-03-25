<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employee_loans', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_employee_loans', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id', 'hr_employee_loans_company_id_idx');
            }

            if (! Schema::hasColumn('hr_employee_loans', 'disbursement_voucher_id')) {
                $table->foreignId('disbursement_voucher_id')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('vouchers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_employee_loans', 'disbursement_accounting_status')) {
                $table->string('disbursement_accounting_status', 20)
                    ->default('pending')
                    ->after('disbursement_voucher_id');
                $table->index('disbursement_accounting_status', 'hr_employee_loans_disb_acc_status_idx');
            }

            if (! Schema::hasColumn('hr_employee_loans', 'disbursement_posted_at')) {
                $table->timestamp('disbursement_posted_at')
                    ->nullable()
                    ->after('disbursement_accounting_status');
            }
        });

        Schema::table('hr_salary_advances', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_salary_advances', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id', 'hr_salary_advances_company_id_idx');
            }

            if (! Schema::hasColumn('hr_salary_advances', 'disbursement_voucher_id')) {
                $table->foreignId('disbursement_voucher_id')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('vouchers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('hr_salary_advances', 'disbursement_accounting_status')) {
                $table->string('disbursement_accounting_status', 20)
                    ->default('pending')
                    ->after('disbursement_voucher_id');
                $table->index('disbursement_accounting_status', 'hr_salary_advances_disb_acc_status_idx');
            }

            if (! Schema::hasColumn('hr_salary_advances', 'disbursement_posted_at')) {
                $table->timestamp('disbursement_posted_at')
                    ->nullable()
                    ->after('disbursement_accounting_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_salary_advances', function (Blueprint $table) {
            if (Schema::hasColumn('hr_salary_advances', 'disbursement_posted_at')) {
                $table->dropColumn('disbursement_posted_at');
            }

            if (Schema::hasColumn('hr_salary_advances', 'disbursement_accounting_status')) {
                $table->dropIndex('hr_salary_advances_disb_acc_status_idx');
                $table->dropColumn('disbursement_accounting_status');
            }

            if (Schema::hasColumn('hr_salary_advances', 'disbursement_voucher_id')) {
                $table->dropConstrainedForeignId('disbursement_voucher_id');
            }

            if (Schema::hasColumn('hr_salary_advances', 'company_id')) {
                $table->dropIndex('hr_salary_advances_company_id_idx');
                $table->dropColumn('company_id');
            }
        });

        Schema::table('hr_employee_loans', function (Blueprint $table) {
            if (Schema::hasColumn('hr_employee_loans', 'disbursement_posted_at')) {
                $table->dropColumn('disbursement_posted_at');
            }

            if (Schema::hasColumn('hr_employee_loans', 'disbursement_accounting_status')) {
                $table->dropIndex('hr_employee_loans_disb_acc_status_idx');
                $table->dropColumn('disbursement_accounting_status');
            }

            if (Schema::hasColumn('hr_employee_loans', 'disbursement_voucher_id')) {
                $table->dropConstrainedForeignId('disbursement_voucher_id');
            }

            if (Schema::hasColumn('hr_employee_loans', 'company_id')) {
                $table->dropIndex('hr_employee_loans_company_id_idx');
                $table->dropColumn('company_id');
            }
        });
    }
};
