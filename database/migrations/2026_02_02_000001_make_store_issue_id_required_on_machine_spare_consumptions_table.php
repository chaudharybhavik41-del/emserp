<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // store_issue_id was nullable with FK ON DELETE SET NULL.
        // We now enforce Store Issue as mandatory for spare consumption costing/reporting.

        Schema::table('machine_spare_consumptions', function (Blueprint $table) {
            $table->dropForeign(['store_issue_id']);
        });

        DB::statement('ALTER TABLE machine_spare_consumptions MODIFY store_issue_id BIGINT UNSIGNED NOT NULL');

        Schema::table('machine_spare_consumptions', function (Blueprint $table) {
            $table->foreign('store_issue_id')
                ->references('id')
                ->on('store_issues')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('machine_spare_consumptions', function (Blueprint $table) {
            $table->dropForeign(['store_issue_id']);
        });

        DB::statement('ALTER TABLE machine_spare_consumptions MODIFY store_issue_id BIGINT UNSIGNED NULL');

        Schema::table('machine_spare_consumptions', function (Blueprint $table) {
            $table->foreign('store_issue_id')
                ->references('id')
                ->on('store_issues')
                ->onDelete('set null');
        });
    }
};
