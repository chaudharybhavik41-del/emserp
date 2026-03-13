<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_v2_cut_batches') && ! Schema::hasColumn('production_v2_cut_batches', 'store_issue_id')) {
            Schema::table('production_v2_cut_batches', function (Blueprint $table) {
                $table->foreignId('store_issue_id')
                    ->nullable()
                    ->after('dpr_id')
                    ->constrained('store_issues')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('production_remnants') && ! Schema::hasColumn('production_remnants', 'production_v2_cut_batch_id')) {
            Schema::table('production_remnants', function (Blueprint $table) {
                $table->foreignId('production_v2_cut_batch_id')
                    ->nullable()
                    ->after('production_dpr_line_id')
                    ->constrained('production_v2_cut_batches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_remnants') && Schema::hasColumn('production_remnants', 'production_v2_cut_batch_id')) {
            Schema::table('production_remnants', function (Blueprint $table) {
                $table->dropConstrainedForeignId('production_v2_cut_batch_id');
            });
        }

        if (Schema::hasTable('production_v2_cut_batches') && Schema::hasColumn('production_v2_cut_batches', 'store_issue_id')) {
            Schema::table('production_v2_cut_batches', function (Blueprint $table) {
                $table->dropConstrainedForeignId('store_issue_id');
            });
        }
    }
};
