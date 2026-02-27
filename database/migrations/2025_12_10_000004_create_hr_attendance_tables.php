<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Attendance Management - Industrial grade with OT tracking
     */
    public function up(): void
    {
        // Raw Punch Data (from biometric or manual)
        Schema::create('hr_attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->dateTime('punch_time');
            $table->enum('punch_type', ['in', 'out', 'break_start', 'break_end', 'unknown'])->default('unknown');
            $table->enum('source', ['biometric', 'manual', 'mobile_app', 'web', 'import'])->default('biometric');
            $table->string('device_id', 50)->nullable();
            $table->string('location_name', 100)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('raw_data', 255)->nullable(); // Original biometric data
            $table->boolean('is_processed')->default(false);
            $table->boolean('is_valid')->default(true);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'punch_time'], 'idx_hr_punch_emp_time');
            $table->index(['punch_time', 'is_processed'], 'idx_hr_punch_time_proc');
        });
        
        // Daily Attendance Summary
        Schema::create('hr_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->foreignId('hr_shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();
            
            // Punch Timing
            $table->dateTime('first_in')->nullable();
            $table->dateTime('last_out')->nullable();
            $table->dateTime('break_start')->nullable();
            $table->dateTime('break_end')->nullable();
            
            // Calculated Times
            $table->decimal('total_hours', 5, 2)->default(0);
            $table->decimal('working_hours', 5, 2)->default(0);
            $table->decimal('break_hours', 5, 2)->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('early_leaving_minutes')->default(0);
            
            // Status
            $table->enum('status', [
                'present', 'absent', 'half_day', 'weekly_off', 
                'holiday', 'leave', 'on_duty', 'comp_off',
                'late', 'early_leaving', 'late_and_early'
            ])->default('absent');
            
            // Day Classification
            $table->enum('day_type', ['working', 'weekly_off', 'holiday', 'leave'])->default('working');
            $table->boolean('is_week_off')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->foreignId('hr_holiday_id')->nullable();
            $table->foreignId('hr_leave_application_id')->nullable();
            
            // Overtime
            $table->decimal('ot_hours', 5, 2)->default(0);
            $table->decimal('ot_hours_approved', 5, 2)->default(0);
            $table->enum('ot_status', ['none', 'pending', 'approved', 'rejected'])->default('none');
            $table->foreignId('ot_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('ot_approved_at')->nullable();
            
            // Regularization
            $table->boolean('is_regularized')->default(false);
            $table->text('regularization_reason')->nullable();
            $table->foreignId('regularized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('regularized_at')->nullable();
            
            // Original values (before regularization)
            $table->dateTime('original_first_in')->nullable();
            $table->dateTime('original_last_out')->nullable();
            $table->string('original_status', 20)->nullable();
            
            // Manual Override
            $table->boolean('is_manual_entry')->default(false);
            $table->text('remarks')->nullable();
            
            // Processing Flags
            $table->boolean('is_processed')->default(false);
            $table->boolean('is_locked')->default(false); // After payroll processing
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['hr_employee_id', 'attendance_date'], 'unq_hr_att_emp_date');
            $table->index('attendance_date', 'idx_hr_att_date');
            $table->index('status', 'idx_hr_att_status');
            $table->index(['hr_employee_id', 'status'], 'idx_hr_att_emp_status');
        });
        
        // Overtime Requests/Records
        Schema::create('hr_overtime_records', function (Blueprint $table) {
            $table->id();
            $table->string('ot_number', 30)->unique();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('ot_date');
            $table->foreignId('hr_attendance_id')->nullable()->constrained('hr_attendances')->cascadeOnDelete();
            
            // OT Details
            $table->time('ot_start_time');
            $table->time('ot_end_time');
            $table->decimal('ot_hours', 5, 2);
            $table->decimal('approved_hours', 5, 2)->default(0);
            
            // Rates
            $table->enum('ot_type', ['normal', 'holiday', 'weekly_off'])->default('normal');
            $table->decimal('rate_multiplier', 4, 2)->default(1.5);
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('ot_amount', 12, 2)->default(0);
            
            // Project/Cost Allocation
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('work_description', 500)->nullable();
            
            // Approval Flow
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'cancelled'])->default('draft');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('requested_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('approval_remarks')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Payroll Reference
            $table->foreignId('hr_payroll_id')->nullable();
            $table->boolean('is_paid')->default(false);
            
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'ot_date'], 'idx_hr_ot_emp_date');
            $table->index(['status', 'ot_date'], 'idx_hr_ot_status_date');
        });
        
        // Attendance Regularization Requests
        Schema::create('hr_attendance_regularizations', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 30)->unique();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('hr_attendance_id')->constrained('hr_attendances')->cascadeOnDelete();
            $table->date('attendance_date');
            
            // Original Values
            $table->dateTime('original_in_time')->nullable();
            $table->dateTime('original_out_time')->nullable();
            $table->string('original_status', 20)->nullable();
            
            // Requested Values
            $table->dateTime('requested_in_time')->nullable();
            $table->dateTime('requested_out_time')->nullable();
            $table->string('requested_status', 20)->nullable();
            
            // Reason
            $table->enum('regularization_type', [
                'missed_punch', 'wrong_punch', 'forgot_id', 
                'biometric_issue', 'on_duty', 'other'
            ])->default('missed_punch');
            $table->text('reason');
            $table->string('supporting_document_path')->nullable();
            
            // Approval
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('approval_remarks')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'status'], 'idx_hr_reg_emp_status');
            $table->index('attendance_date', 'idx_hr_reg_date');
        });
        
        // Comp-Off Records
        Schema::create('hr_comp_offs', function (Blueprint $table) {
            $table->id();
            $table->string('comp_off_number', 30)->unique();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            
            // Earned For
            $table->date('worked_on_date'); // Holiday/Week-off date worked
            $table->enum('worked_day_type', ['weekly_off', 'holiday'])->default('weekly_off');
            $table->foreignId('hr_attendance_id')->nullable()->constrained('hr_attendances')->nullOnDelete();
            
            // Comp-Off Details
            $table->decimal('days_earned', 3, 1)->default(1); // 0.5 or 1
            $table->date('valid_from');
            $table->date('valid_until'); // Expiry date
            $table->decimal('days_used', 3, 1)->default(0);
            $table->decimal('days_balance', 3, 1)->default(1);
            $table->decimal('days_lapsed', 3, 1)->default(0);
            
            // Status
            $table->enum('status', ['pending', 'approved', 'rejected', 'used', 'expired', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('remarks')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'status'], 'idx_hr_co_emp_status');
            $table->index(['valid_from', 'valid_until'], 'idx_hr_co_validity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_comp_offs');
        Schema::dropIfExists('hr_attendance_regularizations');
        Schema::dropIfExists('hr_overtime_records');
        Schema::dropIfExists('hr_attendances');
        Schema::dropIfExists('hr_attendance_punches');
    }
};
