<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Enhancement: Add essential user profile fields including:
     * - is_active: Enable/disable users without deletion
     * - employee_code: Unique employee identifier
     * - phone: Contact number
     * - designation: Job title
     * - profile_photo: Avatar path
     * - last_login_at: Track last login time
     * - last_login_ip: Track last login IP
     * - deleted_at: Soft delete support
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // User status
            $table->boolean('is_active')->default(true)->after('remember_token');
            
            // Profile fields
            $table->string('employee_code', 50)->nullable()->unique()->after('id');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('designation', 100)->nullable()->after('phone');
            $table->string('profile_photo')->nullable()->after('designation');
            
            // Login tracking
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            
            // Soft deletes
            $table->softDeletes();
            
            // Index for common queries
            $table->index('is_active');
            $table->index('employee_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['is_active']);
            $table->dropIndex(['employee_code']);
            $table->dropColumn([
                'is_active',
                'employee_code',
                'phone',
                'designation',
                'profile_photo',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
