<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Designations and Grades for organizational hierarchy
     */
    public function up(): void
    {
        // Designations Table
        Schema::create('hr_designations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('short_name', 20)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('hr_grade_id')->nullable();
            $table->integer('level')->default(0); // Hierarchy level (0=Top, higher=lower)
            $table->decimal('min_salary', 12, 2)->default(0);
            $table->decimal('max_salary', 12, 2)->default(0);
            $table->boolean('is_supervisory')->default(false);
            $table->boolean('is_managerial')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['company_id', 'is_active'], 'idx_hr_desg_company_active');
        });
        
        // Grades/Levels Table
        Schema::create('hr_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->integer('level')->default(0);
            $table->decimal('min_basic', 12, 2)->default(0);
            $table->decimal('max_basic', 12, 2)->default(0);
            $table->decimal('min_gross', 12, 2)->default(0);
            $table->decimal('max_gross', 12, 2)->default(0);
            
            // Grade-specific allowances percentages
            $table->decimal('hra_percent', 5, 2)->default(40); // HRA %
            $table->decimal('transport_allowance', 10, 2)->default(0);
            $table->decimal('special_allowance_percent', 5, 2)->default(0);
            
            // Leave entitlements per grade
            $table->integer('annual_leave_days')->default(12);
            $table->integer('sick_leave_days')->default(7);
            $table->integer('casual_leave_days')->default(7);
            
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['company_id', 'is_active'], 'idx_hr_grade_company_active');
        });
        
        // Add foreign key for designation -> grade
        Schema::table('hr_designations', function (Blueprint $table) {
            $table->foreign('hr_grade_id')->references('id')->on('hr_grades')->nullOnDelete();
        });
        
        // Add foreign keys for employee
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->foreign('hr_designation_id')->references('id')->on('hr_designations')->nullOnDelete();
            $table->foreign('hr_grade_id')->references('id')->on('hr_grades')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropForeign(['hr_designation_id']);
            $table->dropForeign(['hr_grade_id']);
        });
        
        Schema::table('hr_designations', function (Blueprint $table) {
            $table->dropForeign(['hr_grade_id']);
        });
        
        Schema::dropIfExists('hr_designations');
        Schema::dropIfExists('hr_grades');
    }
};
