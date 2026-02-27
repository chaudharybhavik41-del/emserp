<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel 12 / Illuminate Database no longer exposes getDoctrineSchemaManager()
     * unless doctrine/dbal is installed. This migration intentionally avoids Doctrine
     * and uses information_schema to check index existence.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::connection()->getDatabaseName();

        $rows = DB::select(
            'SELECT 1
               FROM information_schema.statistics
              WHERE table_schema = ?
                AND table_name = ?
                AND index_name = ?
              LIMIT 1',
            [$database, $table, $indexName]
        );

        return ! empty($rows);
    }

    public function up(): void
    {
        // Vouchers: company + status + date helps many report queries.
        if (Schema::hasTable('vouchers') && ! $this->indexExists('vouchers', 'vouchers_company_status_date_idx')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->index(['company_id', 'status', 'voucher_date'], 'vouchers_company_status_date_idx');
            });
        }

        // Voucher lines: enforce unique line numbers per voucher.
        if (Schema::hasTable('voucher_lines') && ! $this->indexExists('voucher_lines', 'voucher_lines_voucher_line_no_unique')) {
            Schema::table('voucher_lines', function (Blueprint $table) {
                $table->unique(['voucher_id', 'line_no'], 'voucher_lines_voucher_line_no_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('voucher_lines') && $this->indexExists('voucher_lines', 'voucher_lines_voucher_line_no_unique')) {
            Schema::table('voucher_lines', function (Blueprint $table) {
                $table->dropUnique('voucher_lines_voucher_line_no_unique');
            });
        }

        if (Schema::hasTable('vouchers') && $this->indexExists('vouchers', 'vouchers_company_status_date_idx')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropIndex('vouchers_company_status_date_idx');
            });
        }
    }
};
