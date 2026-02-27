<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Audit Trail Enhancement: Track all model changes across the system
     * - Created, Updated, Deleted events
     * - Store old and new values for comparison
     * - Track who made changes and from where
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable(); // Denormalized for when user is deleted
            
            // Action details
            $table->string('action', 50); // created, updated, deleted, restored, force_deleted, etc.
            $table->string('description')->nullable(); // Human readable description
            
            // Subject (the model being acted upon)
            $table->string('subject_type')->nullable(); // App\Models\User
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_name')->nullable(); // Denormalized identifier
            
            // Changes
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable(); // Array of field names that changed
            
            // Context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable(); // GET, POST, PUT, DELETE
            
            // Additional metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['subject_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
