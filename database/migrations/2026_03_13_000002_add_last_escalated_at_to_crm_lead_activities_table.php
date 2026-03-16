<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_lead_activities')) {
            return;
        }

        Schema::table('crm_lead_activities', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_lead_activities', 'last_escalated_at')) {
                $table->dateTime('last_escalated_at')->nullable()->after('last_reminded_at');
                $table->index('last_escalated_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_lead_activities')) {
            return;
        }

        Schema::table('crm_lead_activities', function (Blueprint $table) {
            if (Schema::hasColumn('crm_lead_activities', 'last_escalated_at')) {
                $table->dropIndex(['last_escalated_at']);
                $table->dropColumn('last_escalated_at');
            }
        });
    }
};
