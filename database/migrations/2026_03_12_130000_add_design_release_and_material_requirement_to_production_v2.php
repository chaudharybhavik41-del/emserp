<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_v2_design_releases')) {
            Schema::create('production_v2_design_releases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('release_number', 80);
                $table->date('release_date')->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['project_id', 'release_number'], 'uq_pv2_design_release_project_number');
                $table->index(['project_id', 'release_date'], 'idx_pv2_design_release_project_date');
            });
        }

        foreach ([
            'production_v2_part_definitions',
            'production_v2_assemblies',
            'production_v2_cutting_plans',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'design_release_id')) {
                    $table->foreignId('design_release_id')->nullable()->after('status');
                    $table->foreign('design_release_id', $tableName . '_design_release_fk')
                        ->references('id')
                        ->on('production_v2_design_releases')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'released_by')) {
                    $table->foreignId('released_by')->nullable()->after('design_release_id');
                    $table->foreign('released_by', $tableName . '_released_by_fk')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'released_at')) {
                    $table->timestamp('released_at')->nullable()->after('released_by');
                }
            });
        }

        if (! Schema::hasTable('production_v2_material_requirements')) {
            Schema::create('production_v2_material_requirements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('requirement_number', 80);
                $table->date('requirement_date')->nullable();
                $table->string('basis', 80)->default('design_snapshot');
                $table->string('status', 40)->default('draft');
                $table->foreignId('design_release_id')->nullable();
                $table->foreignId('released_by')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['project_id', 'requirement_number'], 'uq_pv2_material_requirement_project_number');
                $table->index(['project_id', 'status'], 'idx_pv2_material_requirement_project_status');
                $table->foreign('design_release_id', 'fk_pv2_mr_design_release')
                    ->references('id')
                    ->on('production_v2_design_releases')
                    ->nullOnDelete();
                $table->foreign('released_by', 'fk_pv2_mr_released_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_v2_material_requirement_items')) {
            Schema::create('production_v2_material_requirement_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('material_requirement_id');
                $table->foreignId('material_item_id')->nullable();
                $table->string('material_category', 120)->nullable();
                $table->string('material_grade', 120)->nullable();
                $table->decimal('thickness_mm', 10, 3)->nullable();
                $table->decimal('width_mm', 12, 3)->nullable();
                $table->decimal('length_mm', 12, 3)->nullable();
                $table->string('profile_text', 150)->nullable();
                $table->decimal('required_qty', 14, 3)->default(0);
                $table->foreignId('uom_id')->nullable();
                $table->decimal('required_weight_kg', 14, 3)->nullable();
                $table->decimal('planned_cut_qty_snapshot', 14, 3)->default(0);
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['material_requirement_id', 'material_category'], 'idx_pv2_material_requirement_item_req_category');
                $table->foreign('material_requirement_id', 'fk_pv2_mri_requirement')
                    ->references('id')
                    ->on('production_v2_material_requirements')
                    ->cascadeOnDelete();
                $table->foreign('material_item_id', 'fk_pv2_mri_item')
                    ->references('id')
                    ->on('items')
                    ->nullOnDelete();
                $table->foreign('uom_id', 'fk_pv2_mri_uom')
                    ->references('id')
                    ->on('uoms')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_v2_material_requirement_items');
        Schema::dropIfExists('production_v2_material_requirements');

        foreach ([
            'production_v2_cutting_plans',
            'production_v2_assemblies',
            'production_v2_part_definitions',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'released_at')) {
                    $table->dropColumn('released_at');
                }

                if (Schema::hasColumn($tableName, 'released_by')) {
                    $table->dropForeign($tableName . '_released_by_fk');
                    $table->dropColumn('released_by');
                }

                if (Schema::hasColumn($tableName, 'design_release_id')) {
                    $table->dropForeign($tableName . '_design_release_fk');
                    $table->dropColumn('design_release_id');
                }
            });
        }

        Schema::dropIfExists('production_v2_design_releases');
    }
};
