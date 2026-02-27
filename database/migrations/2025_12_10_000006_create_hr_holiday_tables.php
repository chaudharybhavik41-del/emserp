<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Holiday Management
     */
    public function up(): void
    {
        // Holiday Calendar Master
        Schema::create('hr_holiday_calendars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->integer('year');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['company_id', 'year', 'code'], 'unq_hr_hc_company_year_code');
        });
        
        // Holidays
        Schema::create('hr_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_holiday_calendar_id')->constrained('hr_holiday_calendars')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name', 150);
            $table->enum('holiday_type', [
                'national', 'regional', 'company', 'restricted', 'optional'
            ])->default('company');
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_optional')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['hr_holiday_calendar_id', 'holiday_date'], 'unq_hr_hol_cal_date');
            $table->index('holiday_date', 'idx_hr_hol_date');
        });
        
        // Link holiday calendar to employees/locations
        Schema::create('hr_employee_holiday_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('hr_holiday_calendar_id')->constrained('hr_holiday_calendars');
            $table->integer('year');
            $table->timestamps();
            
            $table->unique(['hr_employee_id', 'year'], 'unq_hr_ehc_emp_year');
        });
        
        // Add foreign key in attendance
        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->foreign('hr_holiday_id')->references('id')->on('hr_holidays')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->dropForeign(['hr_holiday_id']);
        });
        
        Schema::dropIfExists('hr_employee_holiday_calendars');
        Schema::dropIfExists('hr_holidays');
        Schema::dropIfExists('hr_holiday_calendars');
    }
};
