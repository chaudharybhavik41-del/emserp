<?php

namespace App\Console\Commands;

use App\Services\Subcontractor\SubcontractorRaTotalsRepairService;
use Illuminate\Console\Command;

class RepairSubcontractorRaTotals extends Command
{
    protected $signature = 'subcontractor-ra:repair-totals
        {--apply : Persist the corrections}
        {--bill-id=* : Restrict to subcontractor RA bill ids}
        {--ra-number=* : Restrict to RA numbers}
        {--status=* : Restrict to bill statuses like approved or posted}';

    protected $description = 'Recalculate subcontractor RA bill totals and sync posted vouchers/TDS certificates.';

    public function handle(SubcontractorRaTotalsRepairService $service): int
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

        $this->info($apply ? 'Subcontractor RA totals repair applied.' : 'Subcontractor RA totals repair dry run.');
        $this->line('Affected subcontractor RA bills: ' . (int) ($summary['subcontractor_ra_rows'] ?? 0));

        if (! empty($summary['subcontractor_ra_numbers'])) {
            $this->line('RA bills: ' . implode(', ', $summary['subcontractor_ra_numbers']));
        }

        if ($apply) {
            $this->line('Applied RA bills: ' . (int) ($summary['applied_subcontractor_ra_count'] ?? 0));
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
