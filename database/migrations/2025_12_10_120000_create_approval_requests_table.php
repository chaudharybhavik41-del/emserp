<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();

            // Which module / document this approval is for
            $table->string('module', 50)->index();          // e.g. purchase, accounting
            $table->string('sub_module', 50)->nullable()->index(); // e.g. indent, po, voucher

            // Action type for this approval (approve, cancel, post, etc.)
            $table->string('action', 50)->default('approve')->index();

            // Optional: link to Spatie permission name (e.g. purchase.po.approve)
            $table->string('permission_name', 150)->nullable()->index();

            // Polymorphic link to the actual business document
            $table->morphs('approvable'); // approvable_type + approvable_id + index

            // Overall approval request status
            // pending, in_progress, approved, rejected, cancelled
            $table->string('status', 20)->default('pending')->index();

            // Current step number (1-based) if workflow is in progress
            $table->unsignedInteger('current_step')->nullable();

            // Who raised the approval and when
            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('requested_at')->nullable();

            // Who finally closed (approved/rejected/cancelled) the request
            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('closed_at')->nullable();

            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
