<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $rows = DB::select('PRAGMA index_list("' . str_replace('"', '""', $table) . '")');

            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

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
        if (! Schema::hasTable('voucher_lines')) {
            return;
        }

        if (! Schema::hasColumn('voucher_lines', 'machine_id')) {
            Schema::table('voucher_lines', function (Blueprint $table) {
                $table->unsignedBigInteger('machine_id')->nullable()->after('cost_center_id');
            });

            if (Schema::hasTable('machines')) {
                Schema::table('voucher_lines', function (Blueprint $table) {
                    $table->foreign('machine_id')->references('id')->on('machines')->nullOnDelete();
                });
            }
        }

        if (! $this->indexExists('voucher_lines', 'voucher_lines_machine_id_idx')) {
            Schema::table('voucher_lines', function (Blueprint $table) {
                $table->index('machine_id', 'voucher_lines_machine_id_idx');
            });
        }

        if (! $this->indexExists('voucher_lines', 'voucher_lines_machine_account_idx')) {
            Schema::table('voucher_lines', function (Blueprint $table) {
                $table->index(['machine_id', 'account_id'], 'voucher_lines_machine_account_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('voucher_lines')) {
            return;
        }

        if ($this->indexExists('voucher_lines', 'voucher_lines_machine_account_idx')) {
            Schema::table('voucher_lines', function (Blueprint $table) {
                $table->dropIndex('voucher_lines_machine_account_idx');
            });
        }

        if ($this->indexExists('voucher_lines', 'voucher_lines_machine_id_idx')) {
            Schema::table('voucher_lines', function (Blueprint $table) {
                $table->dropIndex('voucher_lines_machine_id_idx');
            });
        }

        if (Schema::hasColumn('voucher_lines', 'machine_id')) {
            Schema::table('voucher_lines', function (Blueprint $table) {
                try {
                    $table->dropForeign(['machine_id']);
                } catch (\Throwable $e) {
                    // ignore
                }

                $table->dropColumn('machine_id');
            });
        }
    }
};
