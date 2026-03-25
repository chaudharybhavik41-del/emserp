<?php

namespace App\Console\Commands;

use App\Services\ClientBilling\ClientBillingTotalsRepairService;
use Illuminate\Console\Command;

class RepairClientBillingTotals extends Command
{
    protected $signature = 'client-billing:repair-totals
        {--apply : Persist the corrections}
        {--bill-id=* : Restrict to client bill ids}
        {--ra-number=* : Restrict to client billing numbers}
        {--status=* : Restrict to statuses like draft, approved, posted}';

    protected $description = 'Repair historical client billing TDS, round off, invoice total and receivable totals.';

    public function handle(ClientBillingTotalsRepairService $service): int
    {
        $filters = [
            'bill_ids' => (array) $this->option('bill-id'),
            'ra_numbers' => (array) $this->option('ra-number'),
            'statuses' => (array) $this->option('status'),
        ];

        $apply = (bool) $this->option('apply');
        $summary = $apply
            ? $service->apply($filters)
            : $service->dryRun($filters);

        $this->info($apply ? 'Client billing totals repair applied.' : 'Client billing totals repair dry run.');
        $this->line('Affected client bills: ' . (int) ($summary['client_bill_rows'] ?? 0));

        if (! empty($summary['client_bill_numbers'])) {
            $this->line('Client bills: ' . implode(', ', $summary['client_bill_numbers']));
        }

        if ($apply) {
            $this->line('Applied client bills: ' . (int) ($summary['applied_client_bill_count'] ?? 0));
        }

        if (! empty($summary['errors'])) {
            $this->newLine();
            $this->error('Errors:');
            foreach ($summary['errors'] as $error) {
                $this->line('- ' . $error);
            }

            return 1;
        }

        return 0;
    }
}
