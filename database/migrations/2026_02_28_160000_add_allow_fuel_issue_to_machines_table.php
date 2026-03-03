<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('machines')) {
            return;
        }

        if (! Schema::hasColumn('machines', 'allow_fuel_issue')) {
            Schema::table('machines', function (Blueprint $table) {
                $table->boolean('allow_fuel_issue')
                    ->default(false)
                    ->after('fuel_type');
            });
        }

        // Sensible backfill so existing diesel/gas/hydraulic machines stay usable in fuel module.
        DB::table('machines')
            ->whereIn('fuel_type', ['diesel', 'gas', 'hydraulic', 'other'])
            ->update(['allow_fuel_issue' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('machines') || ! Schema::hasColumn('machines', 'allow_fuel_issue')) {
            return;
        }

        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn('allow_fuel_issue');
        });
    }
};

