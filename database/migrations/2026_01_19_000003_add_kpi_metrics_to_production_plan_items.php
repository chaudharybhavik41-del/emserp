<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_plan_items', function (Blueprint $table) {
            if (! Schema::hasColumn('production_plan_items', 'unit_area_m2')) {
                $table->decimal('unit_area_m2', 14, 4)->nullable()->after('planned_weight_kg');
            }
            if (! Schema::hasColumn('production_plan_items', 'unit_cut_length_m')) {
                $table->decimal('unit_cut_length_m', 14, 4)->nullable()->after('unit_area_m2');
            }
            if (! Schema::hasColumn('production_plan_items', 'unit_weld_length_m')) {
                $table->decimal('unit_weld_length_m', 14, 4)->nullable()->after('unit_cut_length_m');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_plan_items', function (Blueprint $table) {
            if (Schema::hasColumn('production_plan_items', 'unit_weld_length_m')) {
                $table->dropColumn('unit_weld_length_m');
            }
            if (Schema::hasColumn('production_plan_items', 'unit_cut_length_m')) {
                $table->dropColumn('unit_cut_length_m');
            }
            if (Schema::hasColumn('production_plan_items', 'unit_area_m2')) {
                $table->dropColumn('unit_area_m2');
            }
        });
    }
};
