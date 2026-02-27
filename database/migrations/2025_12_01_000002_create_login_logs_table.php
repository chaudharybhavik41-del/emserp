<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Security Enhancement: Track all login attempts for audit and security monitoring
     * - Successful logins
     * - Failed login attempts
     * - Account lockouts
     * - Logout events
     */
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->index();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('location')->nullable(); // City/Country from IP (optional)
            $table->enum('event_type', [
                'login_success',
                'login_failed',
                'logout',
                'password_reset_requested',
                'password_reset_completed',
                'account_locked',
                'account_unlocked',
                'session_expired'
            ])->default('login_success');
            $table->string('failure_reason')->nullable(); // invalid_password, user_not_found, account_disabled, etc.
            $table->json('metadata')->nullable(); // Additional context data
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for common queries
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
