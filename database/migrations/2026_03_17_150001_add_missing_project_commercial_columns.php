<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'contractor_party_id')) {
                $table->unsignedBigInteger('contractor_party_id')
                    ->nullable()
                    ->after('client_party_id');
                $table->index('contractor_party_id', 'idx_projects_contractor_party_id');
            }

            if (! Schema::hasColumn('projects', 'po_number')) {
                $table->string('po_number', 100)
                    ->nullable()
                    ->after('end_date');
            }

            if (! Schema::hasColumn('projects', 'po_date')) {
                $table->date('po_date')
                    ->nullable()
                    ->after('po_number');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'contractor_party_id')) {
                $table->dropIndex('idx_projects_contractor_party_id');
                $table->dropColumn('contractor_party_id');
            }

            if (Schema::hasColumn('projects', 'po_date')) {
                $table->dropColumn('po_date');
            }

            if (Schema::hasColumn('projects', 'po_number')) {
                $table->dropColumn('po_number');
            }
        });
    }
};
