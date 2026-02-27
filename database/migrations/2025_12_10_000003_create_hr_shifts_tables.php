<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Shifts Management - Industrial-grade shift planning
     */
    public function up(): void
    {
        // Shift Master
        Schema::create('hr_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('short_name', 20)->nullable();
            $table->text('description')->nullable();
            
            // Timing
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_night_shift')->default(false);
            $table->boolean('spans_next_day')->default(false); // For night shifts crossing midnight
            
            // Duration
            $table->decimal('working_hours', 4, 2)->default(8);
            $table->decimal('break_duration_minutes', 5, 2)->default(30);
            $table->decimal('paid_break_minutes', 5, 2)->default(0);
            
            // Grace Period & Late Marking
            $table->integer('grace_period_minutes')->default(10);
            $table->integer('late_mark_after_minutes')->default(15);
            $table->integer('half_day_late_minutes')->default(120); // After 2 hours
            $table->integer('absent_after_minutes')->default(240); // After 4 hours late
            
            // Early Going Rules
            $table->integer('early_going_grace_minutes')->default(10);
            $table->integer('half_day_early_minutes')->default(120);
            
            // Overtime Rules
            $table->boolean('ot_applicable')->default(true);
            $table->integer('ot_start_after_minutes')->default(30); // OT starts after 30 min
            $table->decimal('ot_rate_multiplier', 4, 2)->default(1.5); // 1.5x pay
            $table->decimal('ot_rate_holiday_multiplier', 4, 2)->default(2); // 2x on holidays
            $table->integer('max_ot_hours_per_day')->default(4);
            $table->integer('min_ot_minutes')->default(30); // Minimum 30 min to count as OT
            
            // Flexible Shift
            $table->boolean('is_flexible')->default(false);
            $table->time('flex_start_from')->nullable();
            $table->time('flex_start_to')->nullable();
            
            // Auto Punch Settings
            $table->time('auto_punch_out_time')->nullable();
            $table->boolean('auto_half_day_on_single_punch')->default(false);
            
            // Color for Calendar Display
            $table->string('color_code', 7)->default('#007bff');
            
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['company_id', 'is_active'], 'idx_hr_shift_company_active');
        });
        
        // Weekly Off Patterns
        Schema::create('hr_weekly_off_patterns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            
            // Days (0=Sunday, 1=Monday, ..., 6=Saturday)
            $table->boolean('sunday_off')->default(true);
            $table->boolean('monday_off')->default(false);
            $table->boolean('tuesday_off')->default(false);
            $table->boolean('wednesday_off')->default(false);
            $table->boolean('thursday_off')->default(false);
            $table->boolean('friday_off')->default(false);
            $table->boolean('saturday_off')->default(false);
            
            // Alternate Saturday Types
            $table->enum('saturday_pattern', ['all_working', 'all_off', 'alternate', 'first_third_off', 'second_fourth_off'])->default('all_off');
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['company_id', 'is_active'], 'idx_hr_wop_company_active');
        });
        
        // Shift Roster / Employee Shift Assignment
        Schema::create('hr_shift_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('hr_shift_id')->constrained('hr_shifts');
            $table->foreignId('hr_weekly_off_pattern_id')->nullable()->constrained('hr_weekly_off_patterns')->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_current')->default(true);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'is_current'], 'idx_hr_roster_emp_current');
            $table->index(['effective_from', 'effective_to'], 'idx_hr_roster_dates');
        });
        
        // Daily Shift Override (for rotational shifts)
        Schema::create('hr_daily_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('hr_shift_id')->nullable()->constrained('hr_shifts');
            $table->enum('day_type', ['working', 'weekly_off', 'holiday', 'leave'])->default('working');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['hr_employee_id', 'date'], 'unq_hr_daily_shift');
            $table->index('date', 'idx_hr_daily_shift_date');
        });
        
        // Add foreign key for default shift in employees
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->foreign('default_shift_id')->references('id')->on('hr_shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropForeign(['default_shift_id']);
        });
        
        Schema::dropIfExists('hr_daily_shift_assignments');
        Schema::dropIfExists('hr_shift_rosters');
        Schema::dropIfExists('hr_weekly_off_patterns');
        Schema::dropIfExists('hr_shifts');
    }
};
