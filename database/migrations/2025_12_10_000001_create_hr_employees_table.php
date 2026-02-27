<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Employee Master Table
     * Industrial-grade employee management with Indian compliance
     */
    public function up(): void
    {
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            
            // Link to User account (optional - employee may or may not have system login)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Basic Identity
            $table->string('employee_code', 20)->unique();
            $table->string('biometric_id', 50)->nullable()->unique();
            $table->string('card_number', 50)->nullable();
            
            // Personal Information
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('father_name', 200)->nullable();
            $table->string('mother_name', 200)->nullable();
            $table->string('spouse_name', 200)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('blood_group', 10)->nullable();
            $table->string('nationality', 50)->default('Indian');
            $table->string('religion', 50)->nullable();
            $table->string('caste_category', 50)->nullable(); // General/OBC/SC/ST
            
            // Contact Information
            $table->string('personal_email', 150)->nullable();
            $table->string('official_email', 150)->nullable();
            $table->string('personal_mobile', 20)->nullable();
            $table->string('emergency_contact_name', 200)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();
            
            // Present Address
            $table->text('present_address')->nullable();
            $table->string('present_city', 100)->nullable();
            $table->string('present_state', 100)->nullable();
            $table->string('present_pincode', 10)->nullable();
            
            // Permanent Address
            $table->text('permanent_address')->nullable();
            $table->string('permanent_city', 100)->nullable();
            $table->string('permanent_state', 100)->nullable();
            $table->string('permanent_pincode', 10)->nullable();
            $table->boolean('address_same_as_present')->default(false);
            
            // Government IDs (Indian)
            $table->string('pan_number', 20)->nullable();
            $table->string('aadhar_number', 20)->nullable();
            $table->string('passport_number', 20)->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('driving_license', 30)->nullable();
            $table->date('dl_expiry')->nullable();
            $table->string('voter_id', 30)->nullable();
            
            // Employment Details
            $table->date('date_of_joining');
            $table->date('confirmation_date')->nullable();
            $table->date('date_of_leaving')->nullable();
            $table->string('leaving_reason', 255)->nullable();
            $table->enum('employment_type', [
                'permanent', 'probation', 'contract', 'trainee', 
                'intern', 'consultant', 'casual', 'daily_wage'
            ])->default('probation');
            $table->enum('employee_category', [
                'staff', 'worker', 'supervisor', 'manager', 
                'executive', 'contractor_employee'
            ])->default('staff');
            $table->integer('probation_period_months')->default(6);
            $table->integer('notice_period_days')->default(30);
            
            // Department & Designation
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('hr_designation_id')->nullable();
            $table->foreignId('hr_grade_id')->nullable();
            $table->foreignId('reporting_to')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->string('cost_center', 50)->nullable();
            $table->foreignId('work_location_id')->nullable();
            
            // Shift & Attendance
            $table->foreignId('default_shift_id')->nullable();
            $table->foreignId('hr_attendance_policy_id')->nullable();
            $table->foreignId('hr_leave_policy_id')->nullable();
            $table->boolean('overtime_applicable')->default(true);
            $table->enum('attendance_mode', ['biometric', 'manual', 'both'])->default('both');
            
            // Payroll & Statutory (Indian)
            $table->foreignId('hr_salary_structure_id')->nullable();
            $table->enum('payment_mode', ['bank_transfer', 'cheque', 'cash'])->default('bank_transfer');
            $table->boolean('pf_applicable')->default(true);
            $table->string('pf_number', 30)->nullable(); // UAN
            $table->date('pf_join_date')->nullable();
            $table->boolean('eps_applicable')->default(true);
            $table->boolean('esi_applicable')->default(false);
            $table->string('esi_number', 30)->nullable();
            $table->date('esi_join_date')->nullable();
            $table->boolean('pt_applicable')->default(true);
            $table->string('pt_state', 50)->nullable();
            $table->boolean('lwf_applicable')->default(false);
            $table->boolean('tds_applicable')->default(true);
            $table->enum('tax_regime', ['old', 'new'])->default('new');
            
            // Bank Details
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_branch', 150)->nullable();
            $table->string('bank_account_number', 30)->nullable();
            $table->string('bank_ifsc', 15)->nullable();
            $table->string('bank_account_type', 20)->default('savings');
            
            // Insurance & Benefits
            $table->boolean('gratuity_applicable')->default(true);
            $table->boolean('health_insurance_enrolled')->default(false);
            $table->string('health_insurance_policy_no', 50)->nullable();
            $table->decimal('sum_insured', 12, 2)->default(0);
            
            // Qualifications Summary
            $table->string('highest_qualification', 100)->nullable();
            $table->string('specialization', 100)->nullable();
            $table->integer('total_experience_months')->default(0);
            
            // Status Flags
            $table->enum('status', ['active', 'inactive', 'resigned', 'terminated', 'absconded', 'retired', 'deceased'])->default('active');
            $table->boolean('is_active')->default(true);
            
            // Profile Photo
            $table->string('photo_path')->nullable();
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('company_id', 'idx_hr_emp_company');
            $table->index('employee_code', 'idx_hr_emp_code');
            $table->index('department_id', 'idx_hr_emp_dept');
            $table->index('status', 'idx_hr_emp_status');
            $table->index('date_of_joining', 'idx_hr_emp_doj');
            $table->index(['is_active', 'status'], 'idx_hr_emp_active_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employees');
    }
};
