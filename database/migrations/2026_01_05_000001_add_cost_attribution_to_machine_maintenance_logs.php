<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machine_maintenance_logs', function (Blueprint $table) {
            $table->foreignId('machine_assignment_id')
                ->nullable()
                ->after('maintenance_plan_id')
                ->constrained('machine_assignments')
                ->nullOnDelete();

            $table->foreignId('contractor_party_id')
                ->nullable()
                ->after('machine_assignment_id')
                ->constrained('parties')
                ->nullOnDelete();

            $table->foreignId('worker_user_id')
                ->nullable()
                ->after('contractor_party_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['machine_id', 'contractor_party_id'], 'idx_mml_machine_contractor');
        });

        // Optional but recommended: add "adhoc" into the enum if you want it in UI
        DB::statement("
            ALTER TABLE machine_maintenance_logs
            MODIFY COLUMN maintenance_type
            ENUM('preventive','breakdown','predictive','calibration','inspection','adhoc')
            NOT NULL DEFAULT 'preventive'
        ");
    }

    public function down(): void
    {
        // Revert enum first (remove adhoc)
        DB::statement("
            ALTER TABLE machine_maintenance_logs
            MODIFY COLUMN maintenance_type
            ENUM('preventive','breakdown','predictive','calibration','inspection')
            NOT NULL DEFAULT 'preventive'
        ");

        Schema::table('machine_maintenance_logs', function (Blueprint $table) {
            $table->dropIndex('idx_mml_machine_contractor');
            $table->dropConstrainedForeignId('worker_user_id');
            $table->dropConstrainedForeignId('contractor_party_id');
            $table->dropConstrainedForeignId('machine_assignment_id');
        });
    }
};
