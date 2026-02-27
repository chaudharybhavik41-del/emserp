<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_breakdown_register', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->string('breakdown_number', 50)->unique()->comment('BRK-YY-XXXX');
            
            // Breakdown Details
            $table->dateTime('reported_at');
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->enum('breakdown_type', ['mechanical', 'electrical', 'hydraulic', 'software', 'operator_error', 'other']);
            $table->enum('severity', ['minor', 'major', 'critical']);
            
            // Location & Context
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('operation_during_breakdown', 200)->nullable();
            
            // Problem Description
            $table->text('problem_description');
            $table->text('immediate_action_taken')->nullable();
            
            // Response
            $table->dateTime('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('maintenance_team_assigned')->nullable()->comment('Array of user IDs');
            
            $table->dateTime('repair_started_at')->nullable();
            $table->dateTime('repair_completed_at')->nullable();
            
            // Resolution
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->text('preventive_measures')->nullable();
            
            // Impact
            $table->decimal('production_loss_hours', 10, 2)->default(0);
            $table->decimal('estimated_cost', 15, 2)->default(0);
            
            // Status
            $table->enum('status', ['reported', 'acknowledged', 'in_progress', 'resolved', 'deferred'])->default('reported');
            
            // Link to Maintenance Log
            $table->foreignId('maintenance_log_id')->nullable()->constrained('machine_maintenance_logs')->nullOnDelete();
            
            $table->text('remarks')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('machine_id');
            $table->index('status');
            $table->index('severity');
            $table->index('reported_at');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_breakdown_register');
    }
};
