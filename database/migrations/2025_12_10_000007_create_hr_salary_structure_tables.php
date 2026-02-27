<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Salary Structure & Payroll Components (Indian Compliance)
     */
    public function up(): void
    {
        // Salary Components Master (Earnings & Deductions)
        Schema::create('hr_salary_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('short_name', 20)->nullable();
            $table->text('description')->nullable();
            
            // Type
            $table->enum('component_type', ['earning', 'deduction', 'employer_contribution', 'reimbursement'])->default('earning');
            
            // Component Category
            $table->enum('category', [
                // Earnings
                'basic', 'hra', 'da', 'special_allowance', 'conveyance', 
                'medical', 'lta', 'bonus', 'incentive', 'commission',
                'overtime', 'arrears', 'other_earning',
                // Deductions
                'pf_employee', 'pf_employer', 'esi_employee', 'esi_employer',
                'professional_tax', 'tds', 'lwf_employee', 'lwf_employer',
                'loan_recovery', 'advance_recovery', 'other_deduction',
                // Employer Contributions
                'eps', 'edli', 'admin_charges',
                // Reimbursements
                'fuel', 'telephone', 'travel', 'food', 'other_reimbursement'
            ])->default('other_earning');
            
            // Calculation Method
            $table->enum('calculation_type', [
                'fixed', 'percent_of_basic', 'percent_of_gross', 
                'percent_of_ctc', 'formula', 'slab_based', 'attendance_based'
            ])->default('fixed');
            $table->decimal('default_value', 15, 2)->default(0);
            $table->decimal('percentage', 8, 4)->default(0);
            $table->string('formula', 500)->nullable(); // For custom formula
            
            // Statutory Flags
            $table->boolean('is_statutory')->default(false);
            $table->boolean('affects_pf')->default(false); // Part of PF calculation
            $table->boolean('affects_esi')->default(false);
            $table->boolean('affects_gratuity')->default(false);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_part_of_ctc')->default(true);
            $table->boolean('is_part_of_gross')->default(true);
            
            // Display & Processing
            $table->boolean('show_in_payslip')->default(true);
            $table->boolean('show_if_zero')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index(['company_id', 'component_type', 'is_active'], 'idx_hr_sc_company_type_active');
        });
        
        // Salary Structure Master
        Schema::create('hr_salary_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            
            // Base
            $table->enum('base_type', ['ctc', 'gross', 'basic'])->default('gross');
            $table->boolean('is_default')->default(false);
            
            // Applicability
            $table->json('applicable_employee_types')->nullable();
            $table->json('applicable_grades')->nullable();
            
            // Payroll Settings
            $table->enum('payroll_frequency', ['monthly', 'weekly', 'biweekly', 'daily'])->default('monthly');
            $table->integer('payment_day')->default(1); // Day of month
            
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['company_id', 'is_active'], 'idx_hr_ss_company_active');
        });
        
        // Salary Structure Components
        Schema::create('hr_salary_structure_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_salary_structure_id')->constrained('hr_salary_structures')->cascadeOnDelete();
            $table->foreignId('hr_salary_component_id')->constrained('hr_salary_components');
            
            // Override calculation
            $table->enum('calculation_type', [
                'fixed', 'percent_of_basic', 'percent_of_gross', 
                'percent_of_ctc', 'formula', 'slab_based'
            ])->nullable();
            $table->decimal('value', 15, 2)->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->string('formula', 500)->nullable();
            
            // Limits
            $table->decimal('min_value', 15, 2)->nullable();
            $table->decimal('max_value', 15, 2)->nullable();
            
            $table->boolean('is_mandatory')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['hr_salary_structure_id', 'hr_salary_component_id'], 'unq_hr_ssc_struct_comp');
        });
        
        // Employee Salary Details
        Schema::create('hr_employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('hr_salary_structure_id')->constrained('hr_salary_structures');
            
            // Effective Period
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_current')->default(true);
            
            // CTC Breakdown
            $table->decimal('annual_ctc', 15, 2)->default(0);
            $table->decimal('monthly_ctc', 15, 2)->default(0);
            $table->decimal('monthly_gross', 15, 2)->default(0);
            $table->decimal('monthly_basic', 15, 2)->default(0);
            $table->decimal('monthly_net', 15, 2)->default(0);
            
            // Revision Info
            $table->string('revision_type', 50)->nullable(); // joining, appraisal, promotion
            $table->decimal('increment_percent', 5, 2)->default(0);
            $table->decimal('previous_ctc', 15, 2)->default(0);
            $table->text('remarks')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'is_current'], 'idx_hr_es_emp_current');
            $table->index(['effective_from', 'effective_to'], 'idx_hr_es_dates');
        });
        
        // Employee Salary Component Values
        Schema::create('hr_employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_salary_id')->constrained('hr_employee_salaries')->cascadeOnDelete();
            $table->foreignId('hr_salary_component_id')->constrained('hr_salary_components');
            
            // Values
            $table->decimal('monthly_amount', 15, 2)->default(0);
            $table->decimal('annual_amount', 15, 2)->default(0);
            $table->enum('calculation_type', ['fixed', 'percent_of_basic', 'percent_of_gross', 'formula'])->default('fixed');
            $table->decimal('percentage', 8, 4)->nullable();
            
            $table->timestamps();
            
            $table->unique(['hr_employee_salary_id', 'hr_salary_component_id'], 'unq_hr_esc_sal_comp');
        });
        
        // Add foreign key in employee table
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->foreign('hr_salary_structure_id')->references('id')->on('hr_salary_structures')->nullOnDelete();
        });
        
        // PF Slabs (Current Indian rates)
        Schema::create('hr_pf_slabs', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('wage_ceiling', 15, 2)->default(15000); // Current ceiling
            $table->decimal('employee_contribution_rate', 5, 2)->default(12);
            $table->decimal('employer_pf_rate', 5, 2)->default(3.67);
            $table->decimal('employer_eps_rate', 5, 2)->default(8.33);
            $table->decimal('employer_edli_rate', 5, 2)->default(0.50);
            $table->decimal('admin_charges_rate', 5, 2)->default(0.50);
            $table->decimal('edli_admin_rate', 5, 2)->default(0.01);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        // ESI Slabs (Current Indian rates)
        Schema::create('hr_esi_slabs', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('wage_ceiling', 15, 2)->default(21000);
            $table->decimal('employee_rate', 5, 2)->default(0.75);
            $table->decimal('employer_rate', 5, 2)->default(3.25);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        // Professional Tax Slabs (State-wise)
        Schema::create('hr_professional_tax_slabs', function (Blueprint $table) {
            $table->id();
            $table->string('state_code', 10);
            $table->string('state_name', 50);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('salary_from', 15, 2);
            $table->decimal('salary_to', 15, 2);
            $table->decimal('tax_amount', 10, 2);
            $table->enum('frequency', ['monthly', 'annual'])->default('monthly');
            $table->enum('gender', ['all', 'male', 'female'])->default('all');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['state_code', 'is_active'], 'idx_hr_pt_state_active');
        });
        
        // TDS Slabs (Income Tax)
        Schema::create('hr_tds_slabs', function (Blueprint $table) {
            $table->id();
            $table->string('financial_year', 10); // 2024-25
            $table->enum('regime', ['old', 'new'])->default('new');
            $table->enum('category', ['general', 'senior', 'super_senior'])->default('general');
            $table->decimal('income_from', 15, 2);
            $table->decimal('income_to', 15, 2);
            $table->decimal('tax_percent', 5, 2);
            $table->decimal('surcharge_percent', 5, 2)->default(0);
            $table->decimal('cess_percent', 5, 2)->default(4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['financial_year', 'regime', 'is_active'], 'idx_hr_tds_fy_regime');
        });
        
        // LWF (Labour Welfare Fund) Slabs
        Schema::create('hr_lwf_slabs', function (Blueprint $table) {
            $table->id();
            $table->string('state_code', 10);
            $table->string('state_name', 50);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('employee_contribution', 10, 2);
            $table->decimal('employer_contribution', 10, 2);
            $table->enum('frequency', ['monthly', 'half_yearly', 'annual'])->default('monthly');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['state_code', 'is_active'], 'idx_hr_lwf_state_active');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropForeign(['hr_salary_structure_id']);
        });
        
        Schema::dropIfExists('hr_lwf_slabs');
        Schema::dropIfExists('hr_tds_slabs');
        Schema::dropIfExists('hr_professional_tax_slabs');
        Schema::dropIfExists('hr_esi_slabs');
        Schema::dropIfExists('hr_pf_slabs');
        Schema::dropIfExists('hr_employee_salary_components');
        Schema::dropIfExists('hr_employee_salaries');
        Schema::dropIfExists('hr_salary_structure_components');
        Schema::dropIfExists('hr_salary_structures');
        Schema::dropIfExists('hr_salary_components');
    }
};
