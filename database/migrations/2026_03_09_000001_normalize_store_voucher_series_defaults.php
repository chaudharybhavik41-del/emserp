<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('voucher_series') || ! Schema::hasTable('companies')) {
            return;
        }

        $definitions = [
            'fuel_issue' => [
                'name' => 'Fuel Issue',
                'prefix' => 'FUEL',
                'legacy_prefix' => 'FUEL',
            ],
            'store_return' => [
                'name' => 'Store Return',
                'prefix' => 'RTN',
                'legacy_prefix' => 'STORE_RETU',
            ],
            'stock_adjustment' => [
                'name' => 'Stock Adjustment',
                'prefix' => 'STAD',
                'legacy_prefix' => 'STOCK_ADJU',
            ],
        ];

        $companyIds = DB::table('companies')->pluck('id');
        $now = now();

        foreach ($companyIds as $companyId) {
            foreach ($definitions as $key => $definition) {
                $series = DB::table('voucher_series')
                    ->where('company_id', $companyId)
                    ->where('key', $key)
                    ->first();

                if (! $series) {
                    $prefixInUse = DB::table('voucher_series')
                        ->where('company_id', $companyId)
                        ->where('prefix', $definition['prefix'])
                        ->exists();

                    if ($prefixInUse) {
                        continue;
                    }

                    DB::table('voucher_series')->insert([
                        'company_id' => $companyId,
                        'key' => $key,
                        'name' => $definition['name'],
                        'prefix' => $definition['prefix'],
                        'use_financial_year' => 0,
                        'separator' => '-',
                        'pad_length' => 6,
                        'is_active' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                $hasCounters = Schema::hasTable('voucher_series_counters')
                    && DB::table('voucher_series_counters')
                        ->where('voucher_series_id', $series->id)
                        ->exists();

                $hasVouchers = DB::table('vouchers')
                    ->where('company_id', $companyId)
                    ->where('voucher_type', $key)
                    ->exists();

                if ($hasCounters || $hasVouchers) {
                    continue;
                }

                $prefixConflict = DB::table('voucher_series')
                    ->where('company_id', $companyId)
                    ->where('prefix', $definition['prefix'])
                    ->where('id', '!=', $series->id)
                    ->exists();

                $updates = [
                    'name' => $definition['name'],
                    'use_financial_year' => 0,
                    'separator' => '-',
                    'pad_length' => 6,
                    'updated_at' => $now,
                ];

                if (! $prefixConflict && in_array((string) $series->prefix, [$definition['legacy_prefix'], $definition['prefix']], true)) {
                    $updates['prefix'] = $definition['prefix'];
                }

                DB::table('voucher_series')
                    ->where('id', $series->id)
                    ->update($updates);
            }
        }
    }

    public function down(): void
    {
        // Intentionally no-op. Reverting series formatting can break existing voucher references.
    }
};
