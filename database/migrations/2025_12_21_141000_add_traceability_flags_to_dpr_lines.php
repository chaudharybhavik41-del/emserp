<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_dpr_lines')) {
            Schema::table('production_dpr_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('production_dpr_lines', 'traceability_done')) {
                    $table->boolean('traceability_done')->default(false)->after('remarks');
                }
                if (!Schema::hasColumn('production_dpr_lines', 'traceability_done_at')) {
                    $table->timestamp('traceability_done_at')->nullable()->after('traceability_done');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_dpr_lines')) {
            Schema::table('production_dpr_lines', function (Blueprint $table) {
                if (Schema::hasColumn('production_dpr_lines', 'traceability_done')) {
                    $table->dropColumn(['traceability_done', 'traceability_done_at']);
                }
            });
        }
    }
};
