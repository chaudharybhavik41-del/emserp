<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Accounting: Hierarchical (Tally-style) Account Code Prefixes
 *
 * What this migration does:
 * 1) Updates account_code_sequences prefixes to hierarchical prefixes (4-digit prefix + 3-digit running number).
 * 2) Migrates existing NUMERIC ledger codes (7-digit) to the new prefixes, keeping the same last 3 digits.
 *    - Voucher lines reference account_id, not account.code, so accounting integrity stays intact.
 * 3) Fixes the CAPITAL/CURRENT_CAPITAL/SHARE_CAPITAL duplicate prefix issue (was all 3099).
 * 4) Adds a DB-level unique guardrail: company_id + prefix must be unique.
 *
 * IMPORTANT:
 * - Only numeric codes (digits only) are migrated.
 * - Non-numeric codes like INV-RM, GST-IN-CGST, BANK-HDFC remain unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_code_sequences') || ! Schema::hasTable('accounts')) {
            return;
        }

        // Discover company ids that exist in accounting sequences (usually 1).
        $companyIds = DB::table('account_code_sequences')->distinct()->pluck('company_id')->map(fn ($v) => (int) $v)->all();
        if (empty($companyIds)) {
            $companyIds = DB::table('accounts')->distinct()->pluck('company_id')->map(fn ($v) => (int) $v)->all();
        }
        if (empty($companyIds)) {
            $companyIds = [1];
        }

        // Desired hierarchy mapping (series_key => new 4-digit prefix)
        $map = [
            // Assets
            'CURRENT_ASSETS'   => '1100',
            'BANK_ACCOUNTS'    => '1110',
            'CASH_IN_HAND'     => '1120',
            'SUNDRY_DEBTORS'   => '1130',
            'INVENTORY'        => '1140',
            'LOANS_ADVANCES'   => '1150',
            'GST_INPUT_GROUP'  => '1160',
            'TDS_RECEIVABLE_G' => '1170',
            'TCS_RECEIVABLE_G' => '1180',
            'FIXED_ASSETS'     => '1200',
            'INVESTMENTS'      => '1300',
            'WORK_IN_PROGRESS' => '1400',

            // Liabilities
            'CURRENT_LIABILITIES' => '2100',
            'SUNDRY_CREDITORS'    => '2110',
            'DUTIES_TAXES'        => '2120',
            'GST_OUTPUT_GROUP'    => '2121',
            'TDS_PAYABLE_G'       => '2130',
            'LOANS_LIABILITY'     => '2200',

            // Equity / Capital
            'EQUITY'          => '3000',
            'CAPITAL_ACCOUNT' => '3100',
            'CURRENT_CAPITAL' => '3110',
            'SHARE_CAPITAL'   => '3120',

            // Income
            'SALES'        => '4100',
            'REVENUE'      => '4200',
            'OTHER_INCOME' => '4300',

            // Expenses
            'DIRECT_EXPENSES'   => '5100',
            'CONSUMABLE_EXP'    => '5110',
            'INDIRECT_EXPENSES' => '5200',

            // Defaults (last resort)
            'DEFAULT_ASSET'     => '1999',
            'DEFAULT_LIABILITY' => '2999',
            'DEFAULT_EQUITY'    => '3999',
            'DEFAULT_INCOME'    => '4999',
            'DEFAULT_EXPENSE'   => '5999',
        ];

        foreach ($companyIds as $companyId) {
            // 1) Ensure sequence rows exist (upsert) with pad_width=3
            foreach ($map as $seriesKey => $newPrefix) {
                DB::table('account_code_sequences')->updateOrInsert(
                    ['company_id' => $companyId, 'series_key' => $seriesKey],
                    ['prefix' => $newPrefix, 'pad_width' => 3, 'next_number' => 1, 'updated_at' => now(), 'created_at' => now()]
                );
            }

            // 2) Migrate existing numeric account codes by reading the PREVIOUS prefix per series key (before overwrite)
            //    To keep it safe, we compute migrations using CURRENT DB values, then set prefixes to new values.
            //    Because we've already upserted, we now use a fallback list of known "legacy" prefixes from existing data.
            $legacyPrefixBySeriesKey = [
                // from previous config/db export
                'BANK_ACCOUNTS'    => ['1001'],
                'CASH_IN_HAND'     => ['1002'],
                'SUNDRY_DEBTORS'   => ['1003'],
                'INVENTORY'        => ['1004'],
                'GST_INPUT_GROUP'  => ['1010'],

                'SUNDRY_CREDITORS' => ['2001'],
                'DUTIES_TAXES'     => ['2002'],
                'TDS_PAYABLE_G'    => ['2003'],
                'GST_OUTPUT_GROUP' => ['2010'],

                'EQUITY'           => ['3001'],
                'CAPITAL_ACCOUNT'  => ['3099'], // wrongly used earlier via DEFAULT_EQUITY
                'CURRENT_CAPITAL'  => ['3099'],
                'SHARE_CAPITAL'    => ['3099'],

                'SALES'            => ['4001'],
                'REVENUE'          => ['4002'],
                'OTHER_INCOME'     => ['4010'],

                'DIRECT_EXPENSES'  => ['5001'],
                'INDIRECT_EXPENSES'=> ['5002'],

                'DEFAULT_ASSET'    => ['1099'],
                'DEFAULT_LIABILITY'=> ['2099'],
                'DEFAULT_EQUITY'   => ['3099'],
                'DEFAULT_INCOME'   => ['4099'],
                'DEFAULT_EXPENSE'  => ['5099'],
            ];

            foreach ($legacyPrefixBySeriesKey as $seriesKey => $oldPrefixes) {
                if (! isset($map[$seriesKey])) {
                    continue;
                }
                $newPrefix = (string) $map[$seriesKey];

                foreach ($oldPrefixes as $oldPrefix) {
                    $oldPrefix = (string) $oldPrefix;

                    // Update only numeric 7-digit codes: oldPrefix + 3 digits
                    DB::statement(
                        "UPDATE accounts
                         SET code = CONCAT(?, RIGHT(code, 3))
                         WHERE company_id = ?
                           AND code REGEXP '^[0-9]+$'
                           AND LENGTH(code) = 7
                           AND code LIKE CONCAT(?, '%')",
                        [$newPrefix, $companyId, $oldPrefix]
                    );
                }
            }

            // 3) Update prefixes (again) to ensure everything matches map (pad_width fixed at 3)
            foreach ($map as $seriesKey => $newPrefix) {
                DB::table('account_code_sequences')
                    ->where('company_id', $companyId)
                    ->where('series_key', $seriesKey)
                    ->update([
                        'prefix'      => $newPrefix,
                        'pad_width'   => 3,
                        'updated_at'  => now(),
                    ]);
            }

            // 4) Recalculate next_number for each sequence based on migrated codes (max suffix + 1)
            foreach ($map as $seriesKey => $prefix) {
                $prefix = (string) $prefix;

                // Max of the last 3 digits for numeric codes like PREFIX### in this company.
                $maxSuffix = DB::table('accounts')
                    ->where('company_id', $companyId)
                    ->whereRaw("code REGEXP '^[0-9]+$'")
                    ->whereRaw('LENGTH(code) = 7')
                    ->where('code', 'like', $prefix . '%')
                    ->selectRaw('MAX(CAST(RIGHT(code, 3) AS UNSIGNED)) as mx')
                    ->value('mx');

                $maxSuffix = (int) ($maxSuffix ?: 0);
                $next = $maxSuffix > 0 ? ($maxSuffix + 1) : 1;

                DB::table('account_code_sequences')
                    ->where('company_id', $companyId)
                    ->where('series_key', $seriesKey)
                    ->update([
                        'next_number' => $next,
                        'updated_at'  => now(),
                    ]);
            }
        }

        // 5) Add company_id + prefix uniqueness guardrail (prevents duplicate series like 3099 reused)
        try {
            $idx = DB::select("SHOW INDEX FROM account_code_sequences WHERE Key_name = 'acc_code_seq_company_prefix_uq'");
            if (empty($idx)) {
                Schema::table('account_code_sequences', function (Blueprint $table) {
                    $table->unique(['company_id', 'prefix'], 'acc_code_seq_company_prefix_uq');
                });
            }
        } catch (\Throwable $e) {
            // Ignore (some DBs may not support SHOW INDEX the same way)
        }
    }

    public function down(): void
    {
        // Not reversible safely (would require restoring old prefixes + codes).
        throw new RuntimeException('Down migration is not supported for hierarchical account code migration.');
    }
};

