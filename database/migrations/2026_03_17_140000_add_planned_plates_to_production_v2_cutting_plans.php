<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_v2_cutting_plan_plates')) {
            Schema::create('production_v2_cutting_plan_plates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cutting_plan_id')->constrained('production_v2_cutting_plans')->cascadeOnDelete();
                $table->string('plate_ref', 120)->nullable();
                $table->decimal('planned_width_mm', 12, 3);
                $table->decimal('planned_length_mm', 12, 3);
                $table->decimal('planned_qty', 14, 3)->default(1);
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['cutting_plan_id'], 'idx_pv2_cutting_plan_plate_plan');
            });
        }

        Schema::table('production_v2_cutting_plan_allocations', function (Blueprint $table) {
            if (! Schema::hasColumn('production_v2_cutting_plan_allocations', 'cutting_plan_plate_id')) {
                $table->unsignedBigInteger('cutting_plan_plate_id')
                    ->nullable()
                    ->after('cutting_plan_id');
                $table->index(['cutting_plan_plate_id'], 'idx_pv2_cpa_plate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_v2_cutting_plan_allocations', function (Blueprint $table) {
            if (Schema::hasColumn('production_v2_cutting_plan_allocations', 'cutting_plan_plate_id')) {
                $table->dropIndex('idx_pv2_cpa_plate');
                $table->dropColumn('cutting_plan_plate_id');
            }
        });

        Schema::dropIfExists('production_v2_cutting_plan_plates');
    }
};
