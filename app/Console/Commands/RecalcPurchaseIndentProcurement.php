<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PurchaseIndentProcurementService;

class RecalcPurchaseIndentProcurement extends Command
{
    protected $signature = 'purchase:recalc-indent {indentId? : Recalculate a single indent id (optional)}';
    protected $description = 'Recalculate Purchase Indent procurement_status and line totals from RFQs and POs';

    public function handle(PurchaseIndentProcurementService $svc): int
    {
        $indentId = $this->argument('indentId');

        if ($indentId) {
            $svc->recalcIndent((int) $indentId);
            $this->info("Recalculated indent: {$indentId}");
            return self::SUCCESS;
        }

        $this->info('Recalculating all indents...');
        $svc->recalcAll();
        $this->info('Done.');
        return self::SUCCESS;
    }
}
