<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_plan_items') && ! Schema::hasColumn('production_plan_items', 'grade')) {
            Schema::table('production_plan_items', function (Blueprint $table) {
                // Carry grade from BOM into plan items (helps cutting grouping & reconciliation)
                $table->string('grade', 100)->nullable()->after('description');
                $table->index('grade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_plan_items') && Schema::hasColumn('production_plan_items', 'grade')) {
            Schema::table('production_plan_items', function (Blueprint $table) {
                $table->dropIndex(['grade']);
                $table->dropColumn('grade');
            });
        }
    }
};
