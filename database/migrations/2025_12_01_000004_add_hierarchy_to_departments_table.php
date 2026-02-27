<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Enhancement: Add department hierarchy and head assignment
     * - parent_id: For department tree structure
     * - head_user_id: Department manager/head
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Self-referencing for hierarchy
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('departments')
                ->nullOnDelete();
            
            // Department head
            $table->foreignId('head_user_id')
                ->nullable()
                ->after('description')
                ->constrained('users')
                ->nullOnDelete();
            
            // Sort order for display
            $table->integer('sort_order')->default(0)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['head_user_id']);
            $table->dropColumn(['parent_id', 'head_user_id', 'sort_order']);
        });
    }
};
