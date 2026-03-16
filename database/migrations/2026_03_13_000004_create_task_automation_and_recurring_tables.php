<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('task_list_id')->nullable()->constrained('task_lists')->cascadeOnDelete();
            $table->string('name', 150);
            $table->enum('trigger_event', ['created', 'updated', 'status_changed', 'comment_added', 'overdue'])->default('updated');
            $table->json('conditions')->nullable();
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'trigger_event', 'is_active'], 'task_auto_rules_company_trigger_idx');
        });

        Schema::create('task_recurring_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('task_list_id')->constrained('task_lists')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('task_templates')->nullOnDelete();
            $table->string('name', 150);
            $table->string('title_template', 500);
            $table->longText('description_template')->nullable();
            $table->foreignId('status_id')->nullable()->constrained('task_statuses')->nullOnDelete();
            $table->foreignId('priority_id')->nullable()->constrained('task_priorities')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('task_type', [
                'general',
                'drawing_review',
                'material_procurement',
                'cutting',
                'welding',
                'assembly',
                'surface_treatment',
                'quality_check',
                'packaging',
                'dispatch',
                'installation',
                'documentation',
                'approval',
                'rework',
            ])->default('general');
            $table->integer('estimated_minutes')->nullable();
            $table->json('default_labels')->nullable();
            $table->enum('interval_unit', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->unsignedInteger('interval_value')->default(1);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'next_run_at'], 'task_recur_company_next_run_idx');
        });

        Schema::create('task_intake_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('task_list_id')->constrained('task_lists')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->json('fields');
            $table->foreignId('default_status_id')->nullable()->constrained('task_statuses')->nullOnDelete();
            $table->foreignId('default_priority_id')->nullable()->constrained('task_priorities')->nullOnDelete();
            $table->foreignId('default_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('success_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_auth')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active'], 'task_intake_forms_company_active_idx');
        });

        Schema::create('task_intake_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_intake_form_id')->constrained('task_intake_forms')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload');
            $table->timestamps();

            $table->index(['task_intake_form_id', 'created_at'], 'task_intake_submissions_form_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_intake_submissions');
        Schema::dropIfExists('task_intake_forms');
        Schema::dropIfExists('task_recurring_schedules');
        Schema::dropIfExists('task_automation_rules');
    }
};
