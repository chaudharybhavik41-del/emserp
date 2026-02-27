<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_request_id')
                ->constrained('approval_requests')
                ->cascadeOnDelete();

            // 1, 2, 3... – execution order of this step
            $table->unsignedInteger('step_number')->default(1);

            // Optional label like "HOD Approval", "Finance Approval"
            $table->string('name', 100)->nullable();

            $table->boolean('is_mandatory')->default(true);

            // Who is configured to approve this step
            $table->foreignId('approver_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approver_role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete();

            // Runtime status information for this step
            // pending, notified, approved, rejected, skipped
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('status_changed_at')->nullable();

            // Who actually acted (in case of delegation, etc.)
            $table->foreignId('acted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('acted_at')->nullable();

            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['approval_request_id', 'step_number'], 'approval_steps_request_step_index');
            $table->index(['approver_user_id', 'status'], 'approval_steps_approver_user_status_index');
            $table->index(['approver_role_id', 'status'], 'approval_steps_approver_role_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
