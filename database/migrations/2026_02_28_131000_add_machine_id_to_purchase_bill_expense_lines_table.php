<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
        if (! Schema::hasTable('purchase_bill_expense_lines')) {
            return;
        }

        if (! Schema::hasColumn('purchase_bill_expense_lines', 'machine_id')) {
            Schema::table('purchase_bill_expense_lines', function (Blueprint $table) {
                $table->unsignedBigInteger('machine_id')->nullable()->after('project_id');
            });

            if (Schema::hasTable('machines')) {
                Schema::table('purchase_bill_expense_lines', function (Blueprint $table) {
                    $table->foreign('machine_id')->references('id')->on('machines')->nullOnDelete();
                });
            }
        }

        if (! $this->indexExists('purchase_bill_expense_lines', 'pb_exp_lines_machine_idx')) {
            Schema::table('purchase_bill_expense_lines', function (Blueprint $table) {
                $table->index('machine_id', 'pb_exp_lines_machine_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_bill_expense_lines')) {
            return;
        }

        if ($this->indexExists('purchase_bill_expense_lines', 'pb_exp_lines_machine_idx')) {
            Schema::table('purchase_bill_expense_lines', function (Blueprint $table) {
                $table->dropIndex('pb_exp_lines_machine_idx');
            });
        }

        if (Schema::hasColumn('purchase_bill_expense_lines', 'machine_id')) {
            Schema::table('purchase_bill_expense_lines', function (Blueprint $table) {
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
