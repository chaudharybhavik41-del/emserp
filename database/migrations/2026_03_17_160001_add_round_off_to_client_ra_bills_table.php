<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_ra_bills') && ! Schema::hasColumn('client_ra_bills', 'round_off')) {
            Schema::table('client_ra_bills', function (Blueprint $table) {
                $table->decimal('round_off', 15, 2)->default(0)->after('tds_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client_ra_bills') && Schema::hasColumn('client_ra_bills', 'round_off')) {
            Schema::table('client_ra_bills', function (Blueprint $table) {
                $table->dropColumn('round_off');
            });
        }
    }
};
