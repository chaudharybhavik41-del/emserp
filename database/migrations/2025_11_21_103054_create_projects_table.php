<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectsTable extends Migration
{
    public function up(): void
    {
        // Guardrail: if table already exists, do nothing
        if (Schema::hasTable('projects')) {
            return;
        }

        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // Human-friendly project code: e.g. PROJ-2025-0001
            $table->string('code', 50)->unique();

            // Project name (typically from quotation->project_name)
            $table->string('name', 255);

            // Client
            $table->foreignId('client_party_id')
                ->nullable()
                ->constrained('parties');

            // CRM linkage
            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('crm_leads');

            $table->foreignId('quotation_id')
                ->nullable()
                ->constrained('crm_quotations');

            // Basic dates
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Status: active / on-hold / completed / cancelled, etc.
            $table->string('status', 50)->default('active');

            $table->text('description')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            // Helpful indexes
            $table->index('client_party_id');
            $table->index('lead_id');
            $table->index('quotation_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
}
