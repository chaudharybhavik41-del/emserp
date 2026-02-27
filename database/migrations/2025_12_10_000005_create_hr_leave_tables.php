<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Leave Management System
     */
    public function up(): void
    {
        // Leave Types Master
        Schema::create('hr_leave_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('short_name', 10)->nullable();
            $table->text('description')->nullable();
            
            // Leave Settings
            $table->decimal('default_days_per_year', 5, 1)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_encashable')->default(false);
            $table->boolean('is_carry_forward')->default(false);
            $table->decimal('max_carry_forward_days', 5, 1)->default(0);
            $table->decimal('max_accumulation_days', 5, 1)->default(0);
            
            // Credit Settings
            $table->enum('credit_type', ['annual', 'monthly', 'quarterly', 'on_joining', 'manual'])->default('annual');
            $table->decimal('monthly_credit', 4, 2)->default(0);
            $table->boolean('prorate_on_joining')->default(true);
            
            // Restrictions
            $table->integer('min_days_per_application')->default(1);
            $table->decimal('max_days_per_application', 5, 1)->default(365);
            $table->integer('advance_notice_days')->default(0);
            $table->integer('max_instances_per_month')->default(0); // 0 = unlimited
            $table->boolean('allow_half_day')->default(true);
            $table->boolean('allow_negative_balance')->default(false);
            $table->decimal('negative_balance_limit', 5, 1)->default(0);
            
            // Document Requirements
            $table->boolean('document_required')->default(false);
            $table->integer('document_required_after_days')->default(2);
            
            // Weekend/Holiday Handling
            $table->boolean('include_weekends')->default(false);
            $table->boolean('include_holidays')->default(false);
            
            // Applicable Categories
            $table->json('applicable_employee_types')->nullable(); // ['permanent', 'probation']
            $table->json('applicable_genders')->nullable(); // ['male', 'female', 'other']
            $table->integer('applicable_after_months')->default(0); // Service months
            
            // Display
            $table->string('color_code', 7)->default('#17a2b8');
            $table->integer('sort_order')->default(0);
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['company_id', 'is_active'], 'idx_hr_lt_company_active');
        });
        
        // Leave Policy Master
        Schema::create('hr_leave_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            
            // Fiscal Year Settings
            $table->enum('leave_year_type', ['calendar', 'financial', 'custom'])->default('calendar');
            $table->date('year_start_date')->nullable(); // For custom
            
            // Probation Rules
            $table->boolean('allow_leave_in_probation')->default(false);
            $table->json('probation_allowed_leave_types')->nullable();
            
            // General Settings
            $table->boolean('allow_backdated_application')->default(false);
            $table->integer('max_backdate_days')->default(7);
            $table->boolean('allow_future_application')->default(true);
            $table->integer('max_future_days')->default(90);
            
            // Sandwich Rule (count weekends/holidays between leave)
            $table->boolean('sandwich_rule_enabled')->default(true);
            $table->integer('sandwich_min_gap_days')->default(0);
            
            // Approval Settings
            $table->integer('approval_levels')->default(1);
            $table->boolean('skip_level_on_absence')->default(true);
            
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['company_id', 'is_active'], 'idx_hr_lp_company_active');
        });
        
        // Leave Policy Details (Leave types in policy)
        Schema::create('hr_leave_policy_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_leave_policy_id')->constrained('hr_leave_policies')->cascadeOnDelete();
            $table->foreignId('hr_leave_type_id')->constrained('hr_leave_types')->cascadeOnDelete();
            
            // Override defaults
            $table->decimal('days_per_year', 5, 1)->nullable();
            $table->decimal('max_carry_forward', 5, 1)->nullable();
            $table->boolean('allow_encashment')->nullable();
            
            $table->timestamps();
            
            $table->unique(['hr_leave_policy_id', 'hr_leave_type_id'], 'unq_hr_lpd_policy_type');
        });
        
        // Employee Leave Balance
        Schema::create('hr_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('hr_leave_type_id')->constrained('hr_leave_types');
            $table->integer('year');
            
            // Balances
            $table->decimal('opening_balance', 6, 2)->default(0);
            $table->decimal('credited', 6, 2)->default(0);
            $table->decimal('used', 6, 2)->default(0);
            $table->decimal('pending', 6, 2)->default(0);
            $table->decimal('encashed', 6, 2)->default(0);
            $table->decimal('lapsed', 6, 2)->default(0);
            $table->decimal('adjusted', 6, 2)->default(0);
            $table->decimal('carry_forward', 6, 2)->default(0);
            $table->decimal('closing_balance', 6, 2)->default(0);
            
            // Auto-calculated
            $table->decimal('available_balance', 6, 2)->virtualAs('opening_balance + credited - used - pending - encashed - lapsed + adjusted');
            
            $table->boolean('is_processed')->default(false); // Year-end processed
            $table->timestamps();
            
            $table->unique(['hr_employee_id', 'hr_leave_type_id', 'year'], 'unq_hr_lb_emp_type_year');
            $table->index(['hr_employee_id', 'year'], 'idx_hr_lb_emp_year');
        });
        
        // Leave Applications
        Schema::create('hr_leave_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 30)->unique();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('hr_leave_type_id')->constrained('hr_leave_types');
            
            // Leave Period
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('total_days', 5, 2);
            
            // Half Day Details
            $table->boolean('is_half_day')->default(false);
            $table->enum('half_day_type', ['first_half', 'second_half'])->nullable();
            $table->date('half_day_date')->nullable();
            
            // Reason & Documents
            $table->text('reason');
            $table->string('document_path')->nullable();
            $table->string('contact_during_leave', 20)->nullable();
            $table->string('address_during_leave', 500)->nullable();
            
            // Handover
            $table->foreignId('handover_to')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->text('handover_notes')->nullable();
            
            // Status
            $table->enum('status', [
                'draft', 'pending', 'approved', 'rejected', 
                'cancelled', 'recalled', 'partially_approved'
            ])->default('draft');
            
            // Approval Flow
            $table->integer('current_approval_level')->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('approval_remarks')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Cancellation
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            // Balance Impact
            $table->decimal('balance_before', 6, 2)->default(0);
            $table->decimal('balance_after', 6, 2)->default(0);
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'status'], 'idx_hr_la_emp_status');
            $table->index(['from_date', 'to_date'], 'idx_hr_la_dates');
            $table->index(['status', 'from_date'], 'idx_hr_la_status_date');
        });
        
        // Leave Approval Log (for multi-level approval)
        Schema::create('hr_leave_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_leave_application_id')->constrained('hr_leave_applications')->cascadeOnDelete();
            $table->integer('approval_level');
            $table->foreignId('approver_id')->constrained('users');
            $table->enum('action', ['approved', 'rejected', 'forwarded', 'returned'])->default('approved');
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->index('hr_leave_application_id', 'idx_hr_lal_app');
        });
        
        // Leave Balance Transactions (Audit Trail)
        Schema::create('hr_leave_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('hr_leave_type_id')->constrained('hr_leave_types');
            $table->foreignId('hr_leave_balance_id')->constrained('hr_leave_balances');
            
            // Transaction Details
            $table->date('transaction_date');
            $table->enum('transaction_type', [
                'opening', 'credit', 'debit', 'adjustment',
                'encashment', 'lapse', 'carry_forward', 'reversal'
            ])->default('credit');
            $table->decimal('days', 6, 2);
            $table->decimal('balance_after', 6, 2);
            
            // Reference
            $table->string('reference_type', 50)->nullable(); // hr_leave_applications, manual
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('remarks')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'transaction_date'], 'idx_hr_lt_emp_date');
        });
        
        // Add foreign key for leave policy in employees
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->foreign('hr_leave_policy_id')->references('id')->on('hr_leave_policies')->nullOnDelete();
        });
        
        // Add foreign key for leave application in attendance
        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->foreign('hr_leave_application_id')->references('id')->on('hr_leave_applications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->dropForeign(['hr_leave_application_id']);
        });
        
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropForeign(['hr_leave_policy_id']);
        });
        
        Schema::dropIfExists('hr_leave_transactions');
        Schema::dropIfExists('hr_leave_approval_logs');
        Schema::dropIfExists('hr_leave_applications');
        Schema::dropIfExists('hr_leave_balances');
        Schema::dropIfExists('hr_leave_policy_details');
        Schema::dropIfExists('hr_leave_policies');
        Schema::dropIfExists('hr_leave_types');
    }
};
