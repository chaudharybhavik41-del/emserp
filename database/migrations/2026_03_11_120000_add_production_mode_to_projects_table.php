<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'production_mode')) {
                $table->string('production_mode', 40)
                    ->default('legacy_only')
                    ->after('status');
                $table->index('production_mode', 'idx_projects_production_mode');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'production_mode')) {
                $table->dropIndex('idx_projects_production_mode');
                $table->dropColumn('production_mode');
            }
        });
    }
};
