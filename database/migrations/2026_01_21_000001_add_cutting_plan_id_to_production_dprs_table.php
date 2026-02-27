<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_dprs')) {
            return;
        }

        if (! Schema::hasColumn('production_dprs', 'cutting_plan_id')) {
            Schema::table('production_dprs', function (Blueprint $table) {
                $table->foreignId('cutting_plan_id')
                    ->nullable()
                    ->after('production_activity_id')
                    ->constrained('cutting_plans')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('production_dprs')) {
            return;
        }

        if (Schema::hasColumn('production_dprs', 'cutting_plan_id')) {
            Schema::table('production_dprs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('cutting_plan_id');
            });
        }
    }
};
