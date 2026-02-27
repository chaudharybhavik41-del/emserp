<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCrmLinksToProjectsTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'client_party_id')) {
                $table->foreignId('client_party_id')
                    ->nullable()
                    ->after('name')
                    ->constrained('parties');
            }

            if (!Schema::hasColumn('projects', 'lead_id')) {
                $table->foreignId('lead_id')
                    ->nullable()
                    ->after('client_party_id')
                    ->constrained('crm_leads');
            }

            if (!Schema::hasColumn('projects', 'quotation_id')) {
                $table->foreignId('quotation_id')
                    ->nullable()
                    ->after('lead_id')
                    ->constrained('crm_quotations');
            }

            if (!Schema::hasColumn('projects', 'status')) {
                $table->string('status', 50)
                    ->default('active')
                    ->after('quotation_id');
            }
        });
    }

    public function down(): void
    {
        // Optional: drop columns if you ever need to roll back.
        // Leaving empty is also acceptable if you don't plan to rollback.
    }
}
