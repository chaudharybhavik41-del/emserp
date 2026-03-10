<?php

namespace App\Console\Commands;

use App\Services\Purchase\PurchaseGstSplitRepairService;
use Illuminate\Console\Command;

class RepairPurchaseGstSplit extends Command
{
    protected $signature = 'purchase:repair-gst-split
        {--apply : Persist the corrections}
        {--bill-id=* : Restrict to purchase bill ids}
        {--bill-number=* : Restrict to purchase bill numbers}
        {--po-id=* : Restrict to purchase order ids}
        {--po-code=* : Restrict to purchase order codes}';

    protected $description = 'Correct old intra-state GST split rows where CGST and SGST differ by one paisa.';

    public function handle(PurchaseGstSplitRepairService $service): int
    {
        $filters = [
            'bill_ids' => (array) $this->option('bill-id'),
            'bill_numbers' => (array) $this->option('bill-number'),
            'po_ids' => (array) $this->option('po-id'),
            'po_codes' => (array) $this->option('po-code'),
        ];

        $apply = (bool) $this->option('apply');
        $summary = $apply
            ? $service->apply($filters)
            : $service->dryRun($filters);

        $this->info($apply ? 'GST split repair applied.' : 'GST split repair dry run.');
        $this->line('Purchase order item rows: ' . $summary['purchase_order_item_rows']);
        $this->line('Purchase bill item rows: ' . $summary['purchase_bill_line_rows']);
        $this->line('Purchase bill expense rows: ' . $summary['purchase_bill_expense_rows']);
        $this->line('Affected purchase orders: ' . count($summary['purchase_order_ids']));
        $this->line('Affected purchase bills: ' . count($summary['purchase_bill_ids']));

        if (! empty($summary['purchase_order_codes'])) {
            $this->line('Purchase orders: ' . implode(', ', $summary['purchase_order_codes']));
        }
        if (! empty($summary['purchase_bill_numbers'])) {
            $this->line('Purchase bills: ' . implode(', ', $summary['purchase_bill_numbers']));
        }

        if ($apply) {
            $this->line('Applied purchase orders: ' . (int) ($summary['applied_purchase_order_count'] ?? 0));
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
