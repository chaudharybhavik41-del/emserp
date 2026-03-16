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
            if (! Schema::hasColumn('crm_lead_activities', 'last_reminded_at')) {
                $table->dateTime('last_reminded_at')->nullable()->after('done_at');
                $table->index('last_reminded_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_lead_activities')) {
            return;
        }

        Schema::table('crm_lead_activities', function (Blueprint $table) {
            if (Schema::hasColumn('crm_lead_activities', 'last_reminded_at')) {
                $table->dropIndex(['last_reminded_at']);
                $table->dropColumn('last_reminded_at');
            }
        });
    }
};
