<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add machine_id to production_plan_item_activities so machine can be planned
     * at routing stage (Route Edit / Route Matrix).
     */
    public function up(): void
    {
        Schema::table('production_plan_item_activities', function (Blueprint $table) {
            if (!Schema::hasColumn('production_plan_item_activities', 'machine_id')) {
                $table->foreignId('machine_id')
                    ->nullable()
                    ->after('worker_user_id')
                    ->constrained('machines')
                    ->nullOnDelete();

                $table->index('machine_id', 'idx_ppia_machine_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_plan_item_activities', function (Blueprint $table) {
            if (Schema::hasColumn('production_plan_item_activities', 'machine_id')) {
                // Laravel will auto-name constraint, but we drop by column to be safe.
                try {
                    $table->dropForeign(['machine_id']);
                } catch (Throwable $e) {
                    // ignore
                }

                try {
                    $table->dropIndex('idx_ppia_machine_id');
                } catch (Throwable $e) {
                    // ignore
                }

                $table->dropColumn('machine_id');
            }
        });
    }
};
