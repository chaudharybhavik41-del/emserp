<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enforce: one ledger account per related model per company
        // (e.g., one Party ledger per Party).
        Schema::table('accounts', function (Blueprint $table) {
            // Guard: older DBs might not have these columns
            if (Schema::hasColumn('accounts', 'related_model_type') && Schema::hasColumn('accounts', 'related_model_id')) {
                $table->unique(
                    ['company_id', 'related_model_type', 'related_model_id'],
                    'accounts_company_related_model_unique'
                );
            }
        });

        // Helpful indexes for reporting (safe additions)
        Schema::table('vouchers', function (Blueprint $table) {
            if (! $this->indexExists('vouchers', 'vouchers_company_date_type_idx')) {
                $table->index(['company_id', 'voucher_date', 'voucher_type'], 'vouchers_company_date_type_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if ($this->indexExists('vouchers', 'vouchers_company_date_type_idx')) {
                $table->dropIndex('vouchers_company_date_type_idx');
            }
        });

        Schema::table('accounts', function (Blueprint $table) {
            // Drop only if it exists
            try {
                $table->dropUnique('accounts_company_related_model_unique');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    /**
     * Best-effort index existence check.
     *
     * Laravel schema builder doesn't expose a clean cross-DB way.
     * This helper avoids migration failures when re-running in mixed environments.
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaManager = $connection->getDoctrineSchemaManager();
            $indexes = $schemaManager->listTableIndexes($table);

            return array_key_exists($indexName, $indexes);
        } catch (\Throwable $e) {
            // If we can't detect, assume it doesn't exist and let the migration attempt.
            return false;
        }
    }
};
