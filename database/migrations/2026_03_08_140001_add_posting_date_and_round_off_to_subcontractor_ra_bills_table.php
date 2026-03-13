<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subcontractor_ra_bills')) {
            return;
        }

        Schema::table('subcontractor_ra_bills', function (Blueprint $table) {
            if (! Schema::hasColumn('subcontractor_ra_bills', 'posting_date')) {
                $table->date('posting_date')->nullable()->after('bill_date');
                $table->index('posting_date');
            }

            if (! Schema::hasColumn('subcontractor_ra_bills', 'round_off')) {
                $table->decimal('round_off', 15, 2)->default(0)->after('total_amount');
            }
        });

        if (Schema::hasColumn('subcontractor_ra_bills', 'posting_date')) {
            DB::table('subcontractor_ra_bills')
                ->whereNull('posting_date')
                ->update(['posting_date' => DB::raw('bill_date')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('subcontractor_ra_bills')) {
            return;
        }

        Schema::table('subcontractor_ra_bills', function (Blueprint $table) {
            if (Schema::hasColumn('subcontractor_ra_bills', 'posting_date')) {
                $table->dropIndex(['posting_date']);
                $table->dropColumn('posting_date');
            }

            if (Schema::hasColumn('subcontractor_ra_bills', 'round_off')) {
                $table->dropColumn('round_off');
            }
        });
    }
};
