<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing columns used by MachineAssignmentController.
     *
     * - expected_duration_days: optional numeric duration (used to derive expected_return_date)
     * - extended_reason: stores reason text when assignment is extended
     */
    public function up(): void
    {
        if (!Schema::hasTable('machine_assignments')) {
            return;
        }

        // Add columns only if missing (safe to run on any env)
        Schema::table('machine_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('machine_assignments', 'expected_duration_days')) {
                $table->unsignedInteger('expected_duration_days')
                    ->nullable()
                    ->after('expected_return_date');
            }

            if (!Schema::hasColumn('machine_assignments', 'extended_reason')) {
                $table->text('extended_reason')
                    ->nullable()
                    ->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('machine_assignments')) {
            return;
        }

        Schema::table('machine_assignments', function (Blueprint $table) {
            // Drop in reverse order
            if (Schema::hasColumn('machine_assignments', 'extended_reason')) {
                $table->dropColumn('extended_reason');
            }
            if (Schema::hasColumn('machine_assignments', 'expected_duration_days')) {
                $table->dropColumn('expected_duration_days');
            }
        });
    }
};
