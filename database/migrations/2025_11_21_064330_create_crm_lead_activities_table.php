<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCrmLeadActivitiesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_lead_activities')) {
            return;
        }

        Schema::create('crm_lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads');
            $table->foreignId('user_id')->constrained('users');

            $table->string('type', 50)->nullable(); // call, meeting, email, note
            $table->string('subject')->nullable();
            $table->text('description')->nullable();

            $table->dateTime('due_at')->nullable();
            $table->dateTime('done_at')->nullable();
            $table->string('outcome')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_activities');
    }
}
