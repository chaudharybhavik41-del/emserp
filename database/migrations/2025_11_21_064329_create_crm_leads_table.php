<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCrmLeadsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_leads')) {
            return;
        }

        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');

            $table->foreignId('party_id')->nullable()->constrained('parties');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            $table->foreignId('lead_source_id')->nullable()->constrained('crm_lead_sources');
            $table->foreignId('lead_stage_id')->nullable()->constrained('crm_lead_stages');

            $table->decimal('expected_value', 15, 2)->nullable();
            $table->unsignedTinyInteger('probability')->nullable();

            $table->date('lead_date')->nullable();
            $table->date('expected_close_date')->nullable();

            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('department_id')->nullable()->constrained('departments');

            $table->string('status', 50)->default('open');
            $table->text('lost_reason')->nullable();
            $table->longText('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
}
