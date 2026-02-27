<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            if (! Schema::hasColumn('bom_items', 'unit_area_m2')) {
                $table->decimal('unit_area_m2', 14, 4)->nullable()->after('total_weight');
            }
            if (! Schema::hasColumn('bom_items', 'total_area_m2')) {
                $table->decimal('total_area_m2', 14, 4)->nullable()->after('unit_area_m2');
            }
            if (! Schema::hasColumn('bom_items', 'unit_cut_length_m')) {
                $table->decimal('unit_cut_length_m', 14, 4)->nullable()->after('total_area_m2');
            }
            if (! Schema::hasColumn('bom_items', 'total_cut_length_m')) {
                $table->decimal('total_cut_length_m', 14, 4)->nullable()->after('unit_cut_length_m');
            }
            if (! Schema::hasColumn('bom_items', 'unit_weld_length_m')) {
                $table->decimal('unit_weld_length_m', 14, 4)->nullable()->after('total_cut_length_m');
            }
            if (! Schema::hasColumn('bom_items', 'total_weld_length_m')) {
                $table->decimal('total_weld_length_m', 14, 4)->nullable()->after('unit_weld_length_m');
            }
        });

        Schema::table('bom_template_items', function (Blueprint $table) {
            if (! Schema::hasColumn('bom_template_items', 'unit_area_m2')) {
                $table->decimal('unit_area_m2', 14, 4)->nullable()->after('total_weight');
            }
            if (! Schema::hasColumn('bom_template_items', 'total_area_m2')) {
                $table->decimal('total_area_m2', 14, 4)->nullable()->after('unit_area_m2');
            }
            if (! Schema::hasColumn('bom_template_items', 'unit_cut_length_m')) {
                $table->decimal('unit_cut_length_m', 14, 4)->nullable()->after('total_area_m2');
            }
            if (! Schema::hasColumn('bom_template_items', 'total_cut_length_m')) {
                $table->decimal('total_cut_length_m', 14, 4)->nullable()->after('unit_cut_length_m');
            }
            if (! Schema::hasColumn('bom_template_items', 'unit_weld_length_m')) {
                $table->decimal('unit_weld_length_m', 14, 4)->nullable()->after('total_cut_length_m');
            }
            if (! Schema::hasColumn('bom_template_items', 'total_weld_length_m')) {
                $table->decimal('total_weld_length_m', 14, 4)->nullable()->after('unit_weld_length_m');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            if (Schema::hasColumn('bom_items', 'total_weld_length_m')) {
                $table->dropColumn('total_weld_length_m');
            }
            if (Schema::hasColumn('bom_items', 'unit_weld_length_m')) {
                $table->dropColumn('unit_weld_length_m');
            }
            if (Schema::hasColumn('bom_items', 'total_cut_length_m')) {
                $table->dropColumn('total_cut_length_m');
            }
            if (Schema::hasColumn('bom_items', 'unit_cut_length_m')) {
                $table->dropColumn('unit_cut_length_m');
            }
            if (Schema::hasColumn('bom_items', 'total_area_m2')) {
                $table->dropColumn('total_area_m2');
            }
            if (Schema::hasColumn('bom_items', 'unit_area_m2')) {
                $table->dropColumn('unit_area_m2');
            }
        });

        Schema::table('bom_template_items', function (Blueprint $table) {
            if (Schema::hasColumn('bom_template_items', 'total_weld_length_m')) {
                $table->dropColumn('total_weld_length_m');
            }
            if (Schema::hasColumn('bom_template_items', 'unit_weld_length_m')) {
                $table->dropColumn('unit_weld_length_m');
            }
            if (Schema::hasColumn('bom_template_items', 'total_cut_length_m')) {
                $table->dropColumn('total_cut_length_m');
            }
            if (Schema::hasColumn('bom_template_items', 'unit_cut_length_m')) {
                $table->dropColumn('unit_cut_length_m');
            }
            if (Schema::hasColumn('bom_template_items', 'total_area_m2')) {
                $table->dropColumn('total_area_m2');
            }
            if (Schema::hasColumn('bom_template_items', 'unit_area_m2')) {
                $table->dropColumn('unit_area_m2');
            }
        });
    }
};
