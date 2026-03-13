<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_v2_cutting_plan_allocations')) {
            return;
        }

        Schema::table('production_v2_cutting_plan_allocations', function (Blueprint $table) {
            if (! Schema::hasColumn('production_v2_cutting_plan_allocations', 'planned_blank_ref')) {
                $table->string('planned_blank_ref', 120)->nullable()->after('planned_qty');
            }

            if (! Schema::hasColumn('production_v2_cutting_plan_allocations', 'planned_blank_width_mm')) {
                $table->decimal('planned_blank_width_mm', 12, 3)->nullable()->after('planned_blank_ref');
            }

            if (! Schema::hasColumn('production_v2_cutting_plan_allocations', 'planned_blank_length_mm')) {
                $table->decimal('planned_blank_length_mm', 12, 3)->nullable()->after('planned_blank_width_mm');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('production_v2_cutting_plan_allocations')) {
            return;
        }

        Schema::table('production_v2_cutting_plan_allocations', function (Blueprint $table) {
            foreach (['planned_blank_length_mm', 'planned_blank_width_mm', 'planned_blank_ref'] as $column) {
                if (Schema::hasColumn('production_v2_cutting_plan_allocations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
