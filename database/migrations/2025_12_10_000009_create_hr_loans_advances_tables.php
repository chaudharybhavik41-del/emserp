<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Loans, Advances, and Tax Declarations
     */
    public function up(): void
    {
        // Loan Types
        Schema::create('hr_loan_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            
            $table->decimal('max_amount', 15, 2)->default(0);
            $table->integer('max_tenure_months')->default(36);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->boolean('interest_applicable')->default(false);
            
            // Eligibility
            $table->integer('min_service_months')->default(12);
            $table->decimal('max_percent_of_salary', 5, 2)->default(50);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
        
        // Employee Loans
        Schema::create('hr_employee_loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number', 30)->unique();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('hr_loan_type_id')->constrained('hr_loan_types');
            
            // Loan Details
            $table->date('application_date');
            $table->decimal('applied_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2)->default(0);
            $table->decimal('disbursed_amount', 15, 2)->default(0);
            $table->integer('tenure_months')->default(12);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('emi_amount', 15, 2)->default(0);
            
            // Dates
            $table->date('approved_date')->nullable();
            $table->date('disbursement_date')->nullable();
            $table->date('emi_start_date')->nullable();
            $table->date('emi_end_date')->nullable();
            
            // Balance Tracking
            $table->decimal('principal_outstanding', 15, 2)->default(0);
            $table->decimal('interest_outstanding', 15, 2)->default(0);
            $table->decimal('total_outstanding', 15, 2)->default(0);
            $table->decimal('total_recovered', 15, 2)->default(0);
            $table->integer('emis_paid')->default(0);
            $table->integer('emis_pending')->default(0);
            
            // Status
            $table->enum('status', [
                'applied', 'pending_approval', 'approved', 'rejected',
                'disbursed', 'active', 'closed', 'written_off', 'cancelled'
            ])->default('applied');
            $table->text('rejection_reason')->nullable();
            
            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_remarks')->nullable();
            
            $table->text('purpose')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'status'], 'idx_hr_loan_emp_status');
        });
        
        // Loan Repayment Schedule
        Schema::create('hr_loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_loan_id')->constrained('hr_employee_loans')->cascadeOnDelete();
            
            $table->integer('installment_no');
            $table->date('due_date');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('emi_amount', 15, 2);
            $table->decimal('opening_balance', 15, 2);
            $table->decimal('closing_balance', 15, 2);
            
            // Payment
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->date('paid_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'partial', 'skipped', 'waived'])->default('pending');
            $table->foreignId('hr_payroll_id')->nullable();
            
            $table->timestamps();
            
            $table->unique(['hr_employee_loan_id', 'installment_no'], 'unq_hr_lr_loan_inst');
            $table->index(['due_date', 'status'], 'idx_hr_lr_due_status');
        });
        
        // Salary Advances
        Schema::create('hr_salary_advances', function (Blueprint $table) {
            $table->id();
            $table->string('advance_number', 30)->unique();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            
            // Advance Details
            $table->date('application_date');
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2)->default(0);
            $table->decimal('disbursed_amount', 15, 2)->default(0);
            $table->text('purpose');
            
            // Recovery
            $table->integer('recovery_months')->default(1);
            $table->decimal('monthly_deduction', 15, 2)->default(0);
            $table->decimal('recovered_amount', 15, 2)->default(0);
            $table->decimal('balance_amount', 15, 2)->default(0);
            $table->date('recovery_start_date')->nullable();
            
            // Status
            $table->enum('status', ['applied', 'approved', 'rejected', 'disbursed', 'recovering', 'closed', 'cancelled'])->default('applied');
            
            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'status'], 'idx_hr_adv_emp_status');
        });
        
        // Tax Declaration (80C, 80D, etc.)
        Schema::create('hr_tax_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('financial_year', 10); // 2024-25
            $table->enum('tax_regime', ['old', 'new'])->default('new');
            
            // Status
            $table->enum('status', ['draft', 'submitted', 'verified', 'locked'])->default('draft');
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            
            // Summary
            $table->decimal('total_declared', 15, 2)->default(0);
            $table->decimal('total_verified', 15, 2)->default(0);
            $table->decimal('total_exemption', 15, 2)->default(0);
            $table->decimal('taxable_income', 15, 2)->default(0);
            $table->decimal('tax_liability', 15, 2)->default(0);
            
            $table->timestamps();
            
            $table->unique(['hr_employee_id', 'financial_year'], 'unq_hr_td_emp_fy');
        });
        
        // Tax Declaration Details
        Schema::create('hr_tax_declaration_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_tax_declaration_id')->constrained('hr_tax_declarations')->cascadeOnDelete();
            
            // Section & Details
            $table->string('section_code', 20); // 80C, 80D, HRA, etc.
            $table->string('section_name', 100);
            $table->string('investment_type', 100); // PPF, ELSS, LIC, etc.
            $table->text('description')->nullable();
            
            // Amounts
            $table->decimal('declared_amount', 15, 2);
            $table->decimal('max_limit', 15, 2)->default(0);
            $table->decimal('verified_amount', 15, 2)->default(0);
            
            // Proof
            $table->string('proof_document_path')->nullable();
            $table->boolean('proof_submitted')->default(false);
            $table->boolean('proof_verified')->default(false);
            
            $table->timestamps();
            
            $table->index('hr_tax_declaration_id', 'idx_hr_tdd_declaration');
        });
        
        // Work Locations
        Schema::create('hr_work_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('geofence_radius_meters')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['company_id', 'is_active'], 'idx_hr_wl_company_active');
        });
        
        // Add foreign key in employees
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->foreign('work_location_id')->references('id')->on('hr_work_locations')->nullOnDelete();
        });
        
        // Attendance Policy
        Schema::create('hr_attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            
            // Late Policy
            $table->integer('grace_period_minutes')->default(10);
            $table->integer('late_deduction_per_instance')->default(0); // Minutes
            $table->integer('max_late_instances_per_month')->default(3);
            $table->decimal('late_deduction_from_leave', 4, 2)->default(0); // After max instances
            
            // Half Day Rules
            $table->integer('half_day_after_late_minutes')->default(120);
            $table->integer('half_day_after_early_minutes')->default(120);
            $table->decimal('min_hours_for_full_day', 4, 2)->default(8);
            $table->decimal('min_hours_for_half_day', 4, 2)->default(4);
            
            // Absent Rules
            $table->integer('absent_after_late_minutes')->default(240);
            $table->boolean('mark_absent_on_no_punch')->default(true);
            
            // Overtime Rules
            $table->boolean('ot_allowed')->default(true);
            $table->integer('ot_min_minutes')->default(30);
            $table->integer('ot_max_hours_per_day')->default(4);
            $table->integer('ot_max_hours_per_month')->default(50);
            $table->boolean('ot_needs_approval')->default(true);
            
            // Week Off & Holiday Work
            $table->boolean('allow_week_off_work')->default(true);
            $table->decimal('week_off_ot_multiplier', 4, 2)->default(2);
            $table->decimal('holiday_ot_multiplier', 4, 2)->default(2);
            
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['company_id', 'is_active'], 'idx_hr_ap_company_active');
        });
        
        // Link attendance policy in employee
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->foreign('hr_attendance_policy_id')->references('id')->on('hr_attendance_policies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropForeign(['hr_attendance_policy_id']);
            $table->dropForeign(['work_location_id']);
        });
        
        Schema::dropIfExists('hr_attendance_policies');
        Schema::dropIfExists('hr_work_locations');
        Schema::dropIfExists('hr_tax_declaration_details');
        Schema::dropIfExists('hr_tax_declarations');
        Schema::dropIfExists('hr_salary_advances');
        Schema::dropIfExists('hr_loan_repayments');
        Schema::dropIfExists('hr_employee_loans');
        Schema::dropIfExists('hr_loan_types');
    }
};
