<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bom_items') && ! Schema::hasColumn('bom_items', 'is_billable')) {
            Schema::table('bom_items', function (Blueprint $table) {
                $table->boolean('is_billable')
                    ->default(true)
                    ->after('material_source');

                $table->index('is_billable');
            });
        }

        if (Schema::hasTable('bom_template_items') && ! Schema::hasColumn('bom_template_items', 'is_billable')) {
            Schema::table('bom_template_items', function (Blueprint $table) {
                $table->boolean('is_billable')
                    ->default(true)
                    ->after('material_source');

                $table->index('is_billable');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bom_items') && Schema::hasColumn('bom_items', 'is_billable')) {
            Schema::table('bom_items', function (Blueprint $table) {
                $table->dropIndex(['is_billable']);
                $table->dropColumn('is_billable');
            });
        }

        if (Schema::hasTable('bom_template_items') && Schema::hasColumn('bom_template_items', 'is_billable')) {
            Schema::table('bom_template_items', function (Blueprint $table) {
                $table->dropIndex(['is_billable']);
                $table->dropColumn('is_billable');
            });
        }
    }
};
