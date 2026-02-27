<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_maintenance_plans', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->string('plan_code', 50)->unique()->comment('MTP-YYYY-XXXX');
            $table->string('plan_name', 200);
            
            // Schedule
            $table->enum('maintenance_type', ['preventive', 'predictive', 'calibration', 'inspection']);
            $table->enum('frequency_type', ['daily', 'weekly', 'monthly', 'quarterly', 'half_yearly', 'yearly', 'operating_hours']);
            $table->integer('frequency_value')->comment('Number for frequency (e.g., 90 for 90 days, 500 for 500 hours)');
            
            // Checklist (JSON)
            $table->json('checklist_items')->nullable()->comment('Array of checklist items');
            
            // Planning
            $table->decimal('estimated_duration_hours', 5, 2)->nullable();
            $table->boolean('requires_shutdown')->default(true);
            
            // Notifications
            $table->integer('alert_days_before')->default(7);
            $table->json('alert_user_ids')->nullable()->comment('Array of user IDs to notify');
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->date('last_executed_date')->nullable();
            $table->date('next_scheduled_date')->nullable();
            
            $table->text('remarks')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->index('machine_id');
            $table->index('next_scheduled_date');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_maintenance_plans');
    }
};
