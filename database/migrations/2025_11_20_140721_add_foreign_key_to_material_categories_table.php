<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeyToMaterialCategoriesTable extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_categories') || ! Schema::hasTable('material_types')) {
            return;
        }

        Schema::table('material_categories', function (Blueprint $table) {
            // Only add FK if column exists
            if (Schema::hasColumn('material_categories', 'material_type_id')) {
                $table->foreign('material_type_id', 'material_categories_material_type_id_foreign')
                    ->references('id')->on('material_types')
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_categories')) {
            return;
        }

        Schema::table('material_categories', function (Blueprint $table) {
            // Drop by explicit name to avoid issues
            $table->dropForeign('material_categories_material_type_id_foreign');
        });
    }
}
