<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('production_v2_cutting_plans', 'material_item_id')) {
            Schema::table('production_v2_cutting_plans', function (Blueprint $table) {
                $table->foreignId('material_item_id')
                    ->nullable()
                    ->after('plan_date')
                    ->constrained('items')
                    ->nullOnDelete();
            });
        }

        DB::table('production_v2_cutting_plans as cp')
            ->join('production_v2_cutting_plan_allocations as cpa', 'cpa.cutting_plan_id', '=', 'cp.id')
            ->join('production_v2_part_definitions as pd', 'pd.id', '=', 'cpa.part_definition_id')
            ->whereNull('cp.material_item_id')
            ->whereNotNull('pd.material_item_id')
            ->select('cp.id', DB::raw('MIN(pd.material_item_id) as material_item_id'))
            ->groupBy('cp.id')
            ->get()
            ->each(function ($row) {
                DB::table('production_v2_cutting_plans')
                    ->where('id', $row->id)
                    ->update(['material_item_id' => $row->material_item_id]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('production_v2_cutting_plans', 'material_item_id')) {
            Schema::table('production_v2_cutting_plans', function (Blueprint $table) {
                $table->dropConstrainedForeignId('material_item_id');
            });
        }
    }
};
