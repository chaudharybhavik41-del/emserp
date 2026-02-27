<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Machine Assignments Table
     * Tracks when machines are issued to contractors or company workers
     */
    public function up(): void
    {
        Schema::create('machine_assignments', function (Blueprint $table) {
            $table->id();
            
            // Machine Reference
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->string('assignment_number', 50)->unique()->comment('ASN-YY-XXXX');
            
            // Assignment Details
            $table->enum('assignment_type', ['contractor', 'company_worker']);
            
            // Contractor Details (if assignment_type = contractor)
            $table->foreignId('contractor_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('contractor_person_name', 200)->nullable();
            
            // Worker Details (if assignment_type = company_worker)
            $table->foreignId('worker_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Project Linkage (optional)
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            
            // Dates
            $table->date('assigned_date');
            $table->date('expected_return_date')->nullable();
            $table->date('actual_return_date')->nullable();
            
            // Condition Tracking
            $table->enum('condition_at_issue', ['excellent', 'good', 'fair', 'requires_attention'])->default('good');
            $table->enum('condition_at_return', ['excellent', 'good', 'fair', 'damaged', 'not_returned'])->nullable();
            
            // Meter Reading (for machines with hour meters)
            $table->decimal('meter_reading_at_issue', 10, 2)->nullable();
            $table->decimal('meter_reading_at_return', 10, 2)->nullable();
            $table->decimal('operating_hours_used', 10, 2)->default(0);
            
            // Status
            $table->enum('status', ['active', 'returned', 'extended', 'damaged', 'lost'])->default('active');
            
            // Remarks
            $table->text('issue_remarks')->nullable();
            $table->text('return_remarks')->nullable();
            
            // Audit Trail
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes - WITH EXPLICIT SHORT NAMES
            $table->index('machine_id', 'idx_ma_machine');
            $table->index('status', 'idx_ma_status');
            $table->index(['assignment_type', 'contractor_party_id', 'worker_user_id'], 'idx_ma_assign_details');
            $table->index('assigned_date', 'idx_ma_assigned_date');
            $table->index('project_id', 'idx_ma_project');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_assignments');
    }
};