<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\AccountCodeSequence;
use Illuminate\Database\Seeder;

class AccountingLedgerNumericSequenceSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = (int) config('accounting.default_company_id', 1);

        $map = (array) config('accounting.ledger_code_prefix_by_series_key', []);
        foreach ($map as $seriesKey => $cfg) {
            $prefix = (string) ($cfg['prefix'] ?? '');
            $pad    = (int) ($cfg['pad'] ?? 3);

            if ($prefix === '') continue;

            // Find highest existing code starting with prefix and numeric
            $maxExisting = Account::query()
                ->where('company_id', $companyId)
                ->where('code', 'like', $prefix . '%')
                ->pluck('code')
                ->filter(fn($c) => preg_match('/^\d+$/', (string)$c))
                ->map(function ($c) use ($prefix) {
                    return (int) substr((string)$c, strlen($prefix)); // suffix number
                })
                ->max();

            $next = $maxExisting ? ((int)$maxExisting + 1) : (int) ($cfg['start'] ?? 1);

            AccountCodeSequence::updateOrCreate(
                ['company_id' => $companyId, 'series_key' => $seriesKey],
                ['prefix' => $prefix, 'pad_width' => $pad, 'next_number' => $next]
            );
        }

        // Nature defaults
        $natureDefaults = (array) config('accounting.ledger_code_prefix_by_nature', []);
        foreach ($natureDefaults as $nature => $cfg) {
            $seriesKey = 'DEFAULT_' . strtoupper($nature);
            $prefix = (string) ($cfg['prefix'] ?? '');
            $pad    = (int) ($cfg['pad'] ?? 3);
            $start  = (int) ($cfg['start'] ?? 1);

            if ($prefix === '') continue;

            AccountCodeSequence::updateOrCreate(
                ['company_id' => $companyId, 'series_key' => $seriesKey],
                ['prefix' => $prefix, 'pad_width' => $pad, 'next_number' => $start]
            );
        }
    }
}
