<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('machines')) {
            return;
        }

        Schema::table('machines', function (Blueprint $table) {
            if (! Schema::hasColumn('machines', 'opening_date')) {
                $table->date('opening_date')->nullable()->after('purchase_date');
            }
            if (! Schema::hasColumn('machines', 'opening_cost')) {
                $table->decimal('opening_cost', 15, 2)->nullable()->after('opening_date');
            }
            if (! Schema::hasColumn('machines', 'opening_accum_depr')) {
                $table->decimal('opening_accum_depr', 15, 2)->nullable()->after('opening_cost');
            }
            if (! Schema::hasColumn('machines', 'opening_wdv')) {
                $table->decimal('opening_wdv', 15, 2)->default(0)->after('opening_accum_depr');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('machines')) {
            return;
        }

        Schema::table('machines', function (Blueprint $table) {
            $drop = [];

            if (Schema::hasColumn('machines', 'opening_wdv')) {
                $drop[] = 'opening_wdv';
            }
            if (Schema::hasColumn('machines', 'opening_accum_depr')) {
                $drop[] = 'opening_accum_depr';
            }
            if (Schema::hasColumn('machines', 'opening_cost')) {
                $drop[] = 'opening_cost';
            }
            if (Schema::hasColumn('machines', 'opening_date')) {
                $drop[] = 'opening_date';
            }

            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};

