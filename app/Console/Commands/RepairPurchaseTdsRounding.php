<?php

namespace App\Console\Commands;

use App\Services\Purchase\PurchaseTdsRoundingRepairService;
use Illuminate\Console\Command;

class RepairPurchaseTdsRounding extends Command
{
    protected $signature = 'purchase:repair-tds-rounding
        {--apply : Persist the corrections}
        {--bill-id=* : Restrict to purchase bill ids}
        {--bill-number=* : Restrict to purchase bill numbers}';

    protected $description = 'Round old purchase bill TDS amounts to whole rupees and sync posted voucher lines.';

    public function handle(PurchaseTdsRoundingRepairService $service): int
    {
        $filters = [
            'bill_ids' => (array) $this->option('bill-id'),
            'bill_numbers' => (array) $this->option('bill-number'),
        ];

        $apply = (bool) $this->option('apply');
        $summary = $apply
            ? $service->apply($filters)
            : $service->dryRun($filters);

        $this->info($apply ? 'TDS rounding repair applied.' : 'TDS rounding repair dry run.');
        $this->line('Affected purchase bills: ' . (int) ($summary['purchase_bill_rows'] ?? 0));

        if (! empty($summary['purchase_bill_numbers'])) {
            $this->line('Purchase bills: ' . implode(', ', $summary['purchase_bill_numbers']));
        }

        if ($apply) {
            $this->line('Applied purchase bills: ' . (int) ($summary['applied_purchase_bill_count'] ?? 0));
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
