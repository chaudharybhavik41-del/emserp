<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Payroll Processing Tables
     */
    public function up(): void
    {
        // Payroll Periods
        Schema::create('hr_payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('period_code', 20)->unique();
            $table->string('name', 100);
            $table->integer('year');
            $table->integer('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('attendance_start');
            $table->date('attendance_end');
            $table->date('payment_date')->nullable();
            
            // Working Days
            $table->integer('total_days')->default(0);
            $table->integer('working_days')->default(0);
            $table->integer('holidays')->default(0);
            $table->integer('week_offs')->default(0);
            
            // Status
            $table->enum('status', [
                'draft', 'attendance_locked', 'processing', 
                'processed', 'approved', 'paid', 'closed'
            ])->default('draft');
            
            // Processing Info
            $table->dateTime('attendance_locked_at')->nullable();
            $table->foreignId('attendance_locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            $table->unique(['company_id', 'year', 'month'], 'unq_hr_pp_company_year_month');
        });
        
        // Payroll Batches (for processing)
        Schema::create('hr_payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 30)->unique();
            $table->foreignId('hr_payroll_period_id')->constrained('hr_payroll_periods');
            $table->string('name', 100);
            $table->text('description')->nullable();
            
            // Batch Type
            $table->enum('batch_type', ['regular', 'supplementary', 'arrears', 'bonus', 'final_settlement'])->default('regular');
            
            // Filter Criteria (JSON)
            $table->json('department_ids')->nullable();
            $table->json('employee_ids')->nullable();
            $table->json('employee_types')->nullable();
            
            // Summary
            $table->integer('total_employees')->default(0);
            $table->integer('processed_employees')->default(0);
            $table->integer('error_employees')->default(0);
            $table->decimal('total_gross', 18, 2)->default(0);
            $table->decimal('total_deductions', 18, 2)->default(0);
            $table->decimal('total_net_pay', 18, 2)->default(0);
            
            // Status
            $table->enum('status', ['draft', 'processing', 'processed', 'approved', 'cancelled'])->default('draft');
            $table->text('processing_log')->nullable();
            $table->text('error_log')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['hr_payroll_period_id', 'status'], 'idx_hr_pb_period_status');
        });
        
        // Individual Payroll Records
        Schema::create('hr_payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_number', 30)->unique();
            $table->foreignId('hr_payroll_period_id')->constrained('hr_payroll_periods');
            $table->foreignId('hr_payroll_batch_id')->nullable()->constrained('hr_payroll_batches')->nullOnDelete();
            $table->foreignId('hr_employee_id')->constrained('hr_employees');
            $table->foreignId('hr_employee_salary_id')->nullable()->constrained('hr_employee_salaries');
            
            // Employee Snapshot
            $table->string('employee_code', 30);
            $table->string('employee_name', 200);
            $table->string('department_name', 100)->nullable();
            $table->string('designation_name', 100)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('bank_ifsc', 15)->nullable();
            
            // Attendance Summary
            $table->integer('working_days')->default(0);
            $table->integer('present_days')->default(0);
            $table->decimal('paid_days', 5, 2)->default(0);
            $table->integer('absent_days')->default(0);
            $table->integer('leave_days')->default(0);
            $table->integer('holidays')->default(0);
            $table->integer('week_offs')->default(0);
            $table->decimal('half_days', 4, 1)->default(0);
            $table->integer('late_days')->default(0);
            $table->decimal('ot_hours', 6, 2)->default(0);
            $table->integer('lop_days')->default(0); // Loss of Pay
            
            // Earnings
            $table->decimal('basic', 15, 2)->default(0);
            $table->decimal('hra', 15, 2)->default(0);
            $table->decimal('da', 15, 2)->default(0);
            $table->decimal('special_allowance', 15, 2)->default(0);
            $table->decimal('conveyance', 15, 2)->default(0);
            $table->decimal('medical', 15, 2)->default(0);
            $table->decimal('other_earnings', 15, 2)->default(0);
            $table->decimal('ot_amount', 15, 2)->default(0);
            $table->decimal('incentive', 15, 2)->default(0);
            $table->decimal('bonus', 15, 2)->default(0);
            $table->decimal('arrears', 15, 2)->default(0);
            $table->decimal('reimbursements', 15, 2)->default(0);
            $table->decimal('total_earnings', 15, 2)->default(0);
            $table->decimal('gross_salary', 15, 2)->default(0);
            
            // Deductions
            $table->decimal('pf_employee', 15, 2)->default(0);
            $table->decimal('esi_employee', 15, 2)->default(0);
            $table->decimal('professional_tax', 15, 2)->default(0);
            $table->decimal('tds', 15, 2)->default(0);
            $table->decimal('lwf_employee', 15, 2)->default(0);
            $table->decimal('loan_deduction', 15, 2)->default(0);
            $table->decimal('advance_deduction', 15, 2)->default(0);
            $table->decimal('other_deductions', 15, 2)->default(0);
            $table->decimal('lop_deduction', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            
            // Net Pay
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('net_payable', 15, 2)->default(0);
            
            // Employer Contributions
            $table->decimal('pf_employer', 15, 2)->default(0);
            $table->decimal('eps_employer', 15, 2)->default(0);
            $table->decimal('edli_employer', 15, 2)->default(0);
            $table->decimal('pf_admin_charges', 15, 2)->default(0);
            $table->decimal('esi_employer', 15, 2)->default(0);
            $table->decimal('lwf_employer', 15, 2)->default(0);
            $table->decimal('gratuity_provision', 15, 2)->default(0);
            $table->decimal('total_employer_cost', 15, 2)->default(0);
            $table->decimal('ctc', 15, 2)->default(0);
            
            // Status & Payment
            $table->enum('status', ['draft', 'processed', 'approved', 'paid', 'cancelled', 'hold'])->default('draft');
            $table->string('payment_mode', 20)->default('bank_transfer');
            $table->string('payment_reference', 100)->nullable();
            $table->date('payment_date')->nullable();
            $table->boolean('is_hold')->default(false);
            $table->text('hold_reason')->nullable();
            
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['hr_payroll_period_id', 'hr_employee_id'], 'unq_hr_pay_period_emp');
            $table->index(['hr_employee_id', 'status'], 'idx_hr_pay_emp_status');
        });
        
        // Payroll Component Details
        Schema::create('hr_payroll_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_payroll_id')->constrained('hr_payrolls')->cascadeOnDelete();
            $table->foreignId('hr_salary_component_id')->constrained('hr_salary_components');
            
            // Component Details
            $table->string('component_code', 30);
            $table->string('component_name', 100);
            $table->enum('component_type', ['earning', 'deduction', 'employer_contribution', 'reimbursement']);
            
            // Calculation
            $table->decimal('base_amount', 15, 2)->default(0);
            $table->decimal('calculated_amount', 15, 2)->default(0);
            $table->decimal('adjusted_amount', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2)->default(0);
            $table->text('calculation_notes')->nullable();
            
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('hr_payroll_id', 'idx_hr_pc_payroll');
        });
        
        // Payroll Adjustments (Manual entries)
        Schema::create('hr_payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_payroll_id')->constrained('hr_payrolls')->cascadeOnDelete();
            $table->foreignId('hr_salary_component_id')->nullable()->constrained('hr_salary_components');
            
            $table->string('description', 255);
            $table->enum('adjustment_type', ['earning', 'deduction'])->default('earning');
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            
            $table->index('hr_payroll_id', 'idx_hr_pa_payroll');
        });
        
        // Link overtime records to payroll
        Schema::table('hr_overtime_records', function (Blueprint $table) {
            $table->foreign('hr_payroll_id')->references('id')->on('hr_payrolls')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_overtime_records', function (Blueprint $table) {
            $table->dropForeign(['hr_payroll_id']);
        });
        
        Schema::dropIfExists('hr_payroll_adjustments');
        Schema::dropIfExists('hr_payroll_components');
        Schema::dropIfExists('hr_payrolls');
        Schema::dropIfExists('hr_payroll_batches');
        Schema::dropIfExists('hr_payroll_periods');
    }
};
