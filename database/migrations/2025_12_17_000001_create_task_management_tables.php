<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task Management Module for Fabrication ERP
     * 
     * This migration creates a comprehensive task management system similar to ClickUp
     * with fabrication-specific features including:
     * - Task Lists (workspaces/folders)
     * - Tasks with hierarchy (subtasks)
     * - Custom statuses and workflows
     * - Time tracking
     * - Dependencies
     * - Comments and activity logs
     * - Integration with Projects, BOMs, Work Orders
     */
    public function up(): void
    {
        // Task Statuses - Customizable workflow statuses
        Schema::create('task_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->default(1)->constrained('companies')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('slug', 50);
            $table->string('color', 20)->default('#6b7280'); // Tailwind gray-500
            $table->string('icon', 50)->nullable();
            $table->enum('category', ['open', 'in_progress', 'review', 'completed', 'cancelled'])->default('open');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_closed')->default(false); // Marks task as done when in this status
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'category', 'is_active']);
        });

        // Task Priorities
        Schema::create('task_priorities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->default(1)->constrained('companies')->cascadeOnDelete();
            $table->string('name', 30);
            $table->string('slug', 30);
            $table->string('color', 20)->default('#6b7280');
            $table->string('icon', 50)->nullable();
            $table->integer('level')->default(0); // Higher = more urgent
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        // Task Labels/Tags
        Schema::create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->default(1)->constrained('companies')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('slug', 50);
            $table->string('color', 20)->default('#3b82f6'); // Blue
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        // Task Lists (Folders/Workspaces) - Container for tasks
        Schema::create('task_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->default(1)->constrained('companies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('task_lists')->nullOnDelete();
            
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#6366f1'); // Indigo
            $table->string('icon', 50)->nullable();
            
            // Link to project (optional)
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            
            // Default settings for tasks in this list
            $table->foreignId('default_status_id')->nullable()->constrained('task_statuses')->nullOnDelete();
            $table->foreignId('default_priority_id')->nullable()->constrained('task_priorities')->nullOnDelete();
            $table->foreignId('default_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Visibility
            $table->enum('visibility', ['private', 'team', 'public'])->default('team');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id']);
            $table->index(['company_id', 'is_active', 'is_archived']);
        });

        // Tasks - Main task entity
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->default(1)->constrained('companies')->cascadeOnDelete();
            
            // Hierarchy
            $table->foreignId('task_list_id')->constrained('task_lists')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete(); // Subtasks
            
            // Task identification
            $table->string('task_number', 30)->unique(); // TASK-2025-0001
            $table->string('title', 500);
            $table->longText('description')->nullable();
            
            // Status & Priority
            $table->foreignId('status_id')->constrained('task_statuses');
            $table->foreignId('priority_id')->nullable()->constrained('task_priorities')->nullOnDelete();
            
            // Assignment
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Dates
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->datetime('completed_at')->nullable();
            
            // Time tracking (in minutes)
            $table->integer('estimated_minutes')->nullable();
            $table->integer('logged_minutes')->default(0);
            
            // Progress
            $table->tinyInteger('progress_percent')->default(0); // 0-100
            
            // Fabrication-specific links
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('bom_id')->nullable()->constrained('boms')->nullOnDelete();
            
            // Polymorphic relation for linking to various models
            $table->string('linkable_type', 100)->nullable(); // e.g., App\Models\CuttingPlan
            $table->unsignedBigInteger('linkable_id')->nullable();
            
            // Task type (for fabrication workflows)
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
                'rework'
            ])->default('general');
            
            // Position for ordering
            $table->integer('position')->default(0);
            
            // Flags
            $table->boolean('is_milestone')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->text('blocked_reason')->nullable();
            $table->boolean('is_archived')->default(false);
            
            // Metadata
            $table->json('custom_fields')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'task_list_id', 'status_id']);
            $table->index(['company_id', 'assignee_id', 'status_id']);
            $table->index(['company_id', 'project_id']);
            $table->index(['company_id', 'due_date']);
            $table->index(['linkable_type', 'linkable_id']);
            $table->index(['parent_id']);
        });

        // Task Label Pivot
        Schema::create('task_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('task_label_id')->constrained('task_labels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'task_label_id']);
        });

        // Task Watchers (users following a task)
        Schema::create('task_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        // Task Dependencies
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->enum('dependency_type', [
                'finish_to_start',  // Can't start until dependency finishes
                'start_to_start',   // Can't start until dependency starts
                'finish_to_finish', // Can't finish until dependency finishes
                'start_to_finish'   // Can't finish until dependency starts
            ])->default('finish_to_start');
            $table->integer('lag_days')->default(0); // Days delay after dependency
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id'], 'task_dep_unique');
        });

        // Task Comments
        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('task_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->longText('content');
            $table->boolean('is_internal')->default(false); // Internal notes not visible to external
            $table->boolean('is_pinned')->default(false);
            $table->datetime('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_id', 'created_at']);
        });

        // Task Attachments
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('task_comment_id')->nullable()->constrained('task_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('filename', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size'); // bytes
            $table->string('disk', 50)->default('public');
            $table->string('path', 500);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['task_id']);
        });

        // Task Time Entries
        Schema::create('task_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->datetime('started_at');
            $table->datetime('ended_at')->nullable();
            $table->integer('duration_minutes')->default(0);
            $table->boolean('is_billable')->default(false);
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['task_id', 'user_id']);
            $table->index(['user_id', 'started_at']);
        });

        // Task Activity Log
        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50); // created, updated, status_changed, assigned, etc.
            $table->string('field_name', 100)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });

        // Task Checklists
        Schema::create('task_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title', 255);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Task Checklist Items
        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_checklist_id')->constrained('task_checklists')->cascadeOnDelete();
            $table->string('content', 500);
            $table->boolean('is_completed')->default(false);
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('completed_at')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Task List Members (for team collaboration)
        Schema::create('task_list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_list_id')->constrained('task_lists')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['owner', 'admin', 'member', 'viewer'])->default('member');
            $table->timestamps();

            $table->unique(['task_list_id', 'user_id']);
        });

        // Task Templates for recurring/standard fabrication tasks
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->default(1)->constrained('companies')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('title_template', 500);
            $table->longText('description_template')->nullable();
            $table->foreignId('default_status_id')->nullable()->constrained('task_statuses')->nullOnDelete();
            $table->foreignId('default_priority_id')->nullable()->constrained('task_priorities')->nullOnDelete();
            $table->enum('task_type', [
                'general', 'drawing_review', 'material_procurement', 'cutting',
                'welding', 'assembly', 'surface_treatment', 'quality_check',
                'packaging', 'dispatch', 'installation', 'documentation',
                'approval', 'rework'
            ])->default('general');
            $table->integer('default_estimated_minutes')->nullable();
            $table->json('default_checklist')->nullable();
            $table->json('default_labels')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'task_type', 'is_active']);
        });

        // Saved Filters/Views
        Schema::create('task_saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->default(1)->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_list_id')->nullable()->constrained('task_lists')->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('filters'); // status_ids, priority_ids, assignee_ids, date_range, etc.
            $table->enum('view_type', ['list', 'board', 'calendar', 'gantt', 'table'])->default('list');
            $table->json('columns')->nullable(); // Which columns to show
            $table->json('sort_by')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'task_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_saved_filters');
        Schema::dropIfExists('task_templates');
        Schema::dropIfExists('task_list_members');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_checklists');
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('task_time_entries');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_watchers');
        Schema::dropIfExists('task_label');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_lists');
        Schema::dropIfExists('task_labels');
        Schema::dropIfExists('task_priorities');
        Schema::dropIfExists('task_statuses');
    }
};
