<?php

namespace App\Console\Commands;

use App\Models\Accounting\Account;
use App\Models\Party;
use App\Services\Accounting\PartyAccountService;
use Illuminate\Console\Command;

class SyncPartyAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example:
     *  php artisan accounting:sync-party-accounts --company=1
     */
    protected $signature = 'accounting:sync-party-accounts {--company=} {--chunk=200}';

    /**
     * The console command description.
     */
    protected $description = 'Create/sync debtor & creditor ledger accounts for all Parties.';

    public function handle(PartyAccountService $service): int
    {
        $companyId = (int) ($this->option('company') ?: config('accounting.default_company_id', 1));
        $chunkSize = max(1, (int) ($this->option('chunk') ?: 200));

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $this->info("Syncing party ledger accounts for company_id={$companyId} (chunk={$chunkSize})...");

        Party::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($parties) use ($service, $companyId, &$created, &$updated, &$skipped) {
                foreach ($parties as $party) {
                    $existing = Account::query()
                        ->where('company_id', $companyId)
                        ->where('related_model_type', Party::class)
                        ->where('related_model_id', $party->id)
                        ->first();

                    $account = $service->syncAccountForParty($party, $companyId);

                    if (! $account) {
                        $skipped++;
                        continue;
                    }

                    if (! $existing) {
                        $created++;
                    } else {
                        $updated++;
                    }
                }

                $this->line("Progress: created={$created}, updated={$updated}, skipped={$skipped}");
            });

        $this->newLine();
        $this->info("Done. created={$created}, updated={$updated}, skipped={$skipped}");

        return self::SUCCESS;
    }
}
