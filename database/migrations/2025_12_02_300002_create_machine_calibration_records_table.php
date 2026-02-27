<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_calibration_records', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->string('calibration_number', 50)->unique()->comment('CAL-YY-XXXX');
            
            // Calibration Details
            $table->date('calibration_date');
            $table->date('due_date')->nullable();
            $table->date('next_due_date')->nullable();
            $table->string('calibration_agency', 200)->nullable();
            $table->string('certificate_number', 100)->nullable();
            
            // Standards & Parameters
            $table->string('standard_followed', 200)->nullable();
            $table->json('parameters_calibrated')->nullable()->comment('Array of parameters checked');
            
            // Results
            $table->enum('result', ['pass', 'pass_with_adjustment', 'fail'])->default('pass');
            $table->text('observations')->nullable();
            $table->text('remarks')->nullable();
            
            // Documents
            $table->string('certificate_file_path')->nullable();
            $table->string('report_file_path')->nullable();
            
            // Cost
            $table->decimal('calibration_cost', 15, 2)->default(0);
            
            // Status
            $table->enum('status', ['scheduled', 'completed', 'overdue', 'cancelled'])->default('completed');
            
            // Audit
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->index('machine_id');
            $table->index('calibration_date');
            $table->index('next_due_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_calibration_records');
    }
};
