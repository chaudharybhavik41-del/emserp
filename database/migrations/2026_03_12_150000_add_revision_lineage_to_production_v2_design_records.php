<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alterDesignTable(
            'production_v2_part_definitions',
            'part_code',
            'uq_prod_v2_part_project_code',
            'uq_prod_v2_part_project_code_rev'
        );

        $this->alterDesignTable(
            'production_v2_assemblies',
            'assembly_code',
            'uq_prod_v2_assembly_project_code',
            'uq_prod_v2_assembly_project_code_rev'
        );

        $this->alterDesignTable(
            'production_v2_cutting_plans',
            'plan_number',
            'uq_prod_v2_cutting_plan_project_number',
            'uq_prod_v2_cutting_plan_project_number_rev'
        );

        $this->alterDesignTable(
            'production_v2_material_requirements',
            'requirement_number',
            'uq_pv2_material_requirement_project_number',
            'uq_pv2_material_requirement_project_number_rev'
        );
    }

    public function down(): void
    {
        $this->revertDesignTable(
            'production_v2_part_definitions',
            'part_code',
            'uq_prod_v2_part_project_code_rev',
            'uq_prod_v2_part_project_code'
        );

        $this->revertDesignTable(
            'production_v2_assemblies',
            'assembly_code',
            'uq_prod_v2_assembly_project_code_rev',
            'uq_prod_v2_assembly_project_code'
        );

        $this->revertDesignTable(
            'production_v2_cutting_plans',
            'plan_number',
            'uq_prod_v2_cutting_plan_project_number_rev',
            'uq_prod_v2_cutting_plan_project_number'
        );

        $this->revertDesignTable(
            'production_v2_material_requirements',
            'requirement_number',
            'uq_pv2_material_requirement_project_number_rev',
            'uq_pv2_material_requirement_project_number'
        );
    }

    protected function alterDesignTable(string $tableName, string $codeColumn, string $legacyUnique, string $revisionUnique): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'revision_no')) {
                $table->unsignedInteger('revision_no')->default(1)->after('status');
            }
            if (! Schema::hasColumn($tableName, 'revision_root_id')) {
                $table->unsignedBigInteger('revision_root_id')->nullable()->after('revision_no');
            }
            if (! Schema::hasColumn($tableName, 'previous_revision_id')) {
                $table->unsignedBigInteger('previous_revision_id')->nullable()->after('revision_root_id');
            }
            if (! Schema::hasColumn($tableName, 'superseded_by_revision_id')) {
                $table->unsignedBigInteger('superseded_by_revision_id')->nullable()->after('previous_revision_id');
            }
            if (! Schema::hasColumn($tableName, 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable()->after('released_at');
            }
        });

        DB::table($tableName)->whereNull('revision_no')->update(['revision_no' => 1]);
        DB::statement("UPDATE {$tableName} SET revision_root_id = id WHERE revision_root_id IS NULL");

        try {
            Schema::table($tableName, function (Blueprint $table) use ($legacyUnique) {
                $table->dropUnique($legacyUnique);
            });
        } catch (\Throwable $e) {
            // Index may already be replaced on environments that reran this migration logic.
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $codeColumn, $revisionUnique) {
            $table->unique(['project_id', $codeColumn, 'revision_no'], $revisionUnique);
            $table->index(['project_id', 'revision_root_id'], 'idx_' . $tableName . '_project_root');
            $table->index(['project_id', 'status', 'revision_root_id'], 'idx_' . $tableName . '_project_status_root');
        });
    }

    protected function revertDesignTable(string $tableName, string $codeColumn, string $revisionUnique, string $legacyUnique): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $revisionUnique) {
                $table->dropUnique($revisionUnique);
                $table->dropIndex('idx_' . $tableName . '_project_root');
                $table->dropIndex('idx_' . $tableName . '_project_status_root');
            });
        } catch (\Throwable $e) {
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $legacyUnique, $codeColumn) {
            if (Schema::hasColumn($tableName, 'superseded_at')) {
                $table->dropColumn('superseded_at');
            }
            if (Schema::hasColumn($tableName, 'superseded_by_revision_id')) {
                $table->dropColumn('superseded_by_revision_id');
            }
            if (Schema::hasColumn($tableName, 'previous_revision_id')) {
                $table->dropColumn('previous_revision_id');
            }
            if (Schema::hasColumn($tableName, 'revision_root_id')) {
                $table->dropColumn('revision_root_id');
            }
            if (Schema::hasColumn($tableName, 'revision_no')) {
                $table->dropColumn('revision_no');
            }
            $table->unique(['project_id', $codeColumn], $legacyUnique);
        });
    }
};
