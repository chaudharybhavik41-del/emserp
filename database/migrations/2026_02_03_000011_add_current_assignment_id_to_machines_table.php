<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add machines.current_assignment_id as the pointer to the active assignment.
     *
     * Why?
     * - Controller updates machines.current_assignment_id on issue/return.
     * - DB schema (create_machines_table) currently doesn't include this column.
     */
    public function up(): void
    {
        if (! Schema::hasTable('machines')) {
            return;
        }

        // machine_assignments must exist for the FK.
        if (! Schema::hasTable('machine_assignments')) {
            return;
        }

        Schema::table('machines', function (Blueprint $table) {
            if (! Schema::hasColumn('machines', 'current_assignment_id')) {
                $table->foreignId('current_assignment_id')
                    ->nullable()
                    ->after('current_assignment_type')
                    ->constrained('machine_assignments')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('machines')) {
            return;
        }

        Schema::table('machines', function (Blueprint $table) {
            if (Schema::hasColumn('machines', 'current_assignment_id')) {
                // Prefer the convenience method (drops FK + column)
                try {
                    $table->dropConstrainedForeignId('current_assignment_id');
                    return;
                } catch (Throwable $e) {
                    // Fallback (in case constraint name differs)
                }

                try {
                    $table->dropForeign(['current_assignment_id']);
                } catch (Throwable $e) {
                    // ignore
                }

                $table->dropColumn('current_assignment_id');
            }
        });
    }
};
