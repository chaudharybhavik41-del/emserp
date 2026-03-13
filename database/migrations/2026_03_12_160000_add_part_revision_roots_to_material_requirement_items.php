<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_v2_material_requirement_items')) {
            return;
        }

        Schema::table('production_v2_material_requirement_items', function (Blueprint $table) {
            if (! Schema::hasColumn('production_v2_material_requirement_items', 'part_revision_root_ids_json')) {
                $table->text('part_revision_root_ids_json')->nullable()->after('profile_text');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('production_v2_material_requirement_items')) {
            return;
        }

        Schema::table('production_v2_material_requirement_items', function (Blueprint $table) {
            if (Schema::hasColumn('production_v2_material_requirement_items', 'part_revision_root_ids_json')) {
                $table->dropColumn('part_revision_root_ids_json');
            }
        });
    }
};
