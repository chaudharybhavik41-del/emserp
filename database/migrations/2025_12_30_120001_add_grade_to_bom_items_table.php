<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bom_items') && ! Schema::hasColumn('bom_items', 'grade')) {
            Schema::table('bom_items', function (Blueprint $table) {
                // Material grade (e.g., IS2062 E250 / E350). Critical for procurement + traceability.
                $table->string('grade', 100)->nullable()->after('material_source');
                $table->index('grade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bom_items') && Schema::hasColumn('bom_items', 'grade')) {
            Schema::table('bom_items', function (Blueprint $table) {
                $table->dropIndex(['grade']);
                $table->dropColumn('grade');
            });
        }
    }
};
