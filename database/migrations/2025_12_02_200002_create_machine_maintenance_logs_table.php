<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_maintenance_logs', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->foreignId('maintenance_plan_id')->nullable()->constrained('machine_maintenance_plans')->nullOnDelete();
            $table->string('log_number', 50)->unique()->comment('MTL-YY-XXXX');
            
            // Type & Dates
            $table->enum('maintenance_type', ['preventive', 'breakdown', 'predictive', 'calibration', 'inspection']);
            $table->date('scheduled_date')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            
            // Machine Status
            $table->decimal('meter_reading_before', 10, 2)->nullable();
            $table->decimal('meter_reading_after', 10, 2)->nullable();
            
            // Work Details
            $table->text('work_description');
            $table->text('work_performed')->nullable();
            $table->text('findings')->nullable();
            $table->text('recommendations')->nullable();
            
            // Resources
            $table->json('technician_user_ids')->nullable()->comment('Array of user IDs');
            $table->foreignId('external_vendor_party_id')->nullable()->constrained('parties')->nullOnDelete();
            
            // Status
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'deferred', 'cancelled'])->default('scheduled');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            
            // Cost Tracking
            $table->decimal('labor_cost', 15, 2)->default(0);
            $table->decimal('parts_cost', 15, 2)->default(0);
            $table->decimal('external_service_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            
            // Downtime
            $table->decimal('downtime_hours', 10, 2)->default(0);
            
            $table->text('remarks')->nullable();
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->index('machine_id');
            $table->index('maintenance_type');
            $table->index('status');
            $table->index('scheduled_date');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_maintenance_logs');
    }
};
