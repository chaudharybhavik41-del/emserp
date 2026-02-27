<?php

namespace App\ReportsHub;

use App\ReportsHub\Contracts\Report;

class ReportRegistry
{
    /**
     * Register report classes here (one class = one report).
     *
     * @var array<int, class-string<Report>>
     */
    protected array $reportClasses = [
        \App\ReportsHub\Reports\PartyMasterReport::class,
        \App\ReportsHub\Reports\ProjectMasterReport::class,
        \App\ReportsHub\Reports\ItemMasterReport::class,
        \App\ReportsHub\Reports\PurchaseRfqRegisterReport::class,
        \App\ReportsHub\Reports\PurchaseIndentRegisterReport::class,
        \App\ReportsHub\Reports\PurchaseOrderRegisterReport::class,
        \App\ReportsHub\Reports\PurchaseBillRegisterReport::class,
        \App\ReportsHub\Reports\PurchaseDebitNoteRegisterReport::class,
        \App\ReportsHub\Reports\MaterialReceiptRegisterReport::class,
        \App\ReportsHub\Reports\MaterialVendorReturnRegisterReport::class,
        \App\ReportsHub\Reports\StoreStockOnHandReport::class,
        \App\ReportsHub\Reports\StoreRequisitionRegisterReport::class,
        \App\ReportsHub\Reports\StoreIssueRegisterReport::class,
        \App\ReportsHub\Reports\StoreReturnRegisterReport::class,
        \App\ReportsHub\Reports\StoreStockAdjustmentRegisterReport::class,
        \App\ReportsHub\Reports\StoreReorderLevelReport::class,
        \App\ReportsHub\Reports\ClientRaBillRegisterReport::class,
        \App\ReportsHub\Reports\SubcontractorRaBillRegisterReport::class,
        \App\ReportsHub\Reports\ProductionBillRegisterReport::class,
        \App\ReportsHub\Reports\SalesCreditNoteRegisterReport::class,
        \App\ReportsHub\Reports\VoucherRegisterReport::class,
        \App\ReportsHub\Reports\AccountLedgerReport::class,
        \App\ReportsHub\Reports\TrialBalanceReport::class,
        \App\ReportsHub\Reports\BomRegisterReport::class,
        \App\ReportsHub\Reports\CuttingPlanRegisterReport::class,
        \App\ReportsHub\Reports\SectionPlanRegisterReport::class,
        \App\ReportsHub\Reports\ProductionPlanRegisterReport::class,
        \App\ReportsHub\Reports\ProductionDprRegisterReport::class,
        \App\ReportsHub\Reports\EmployeeRegisterReport::class,
        \App\ReportsHub\Reports\AttendanceRegisterReport::class,
        \App\ReportsHub\Reports\PayrollSummaryReport::class,
    ];

    /** @var array<string, Report>|null */
    protected ?array $cache = null;

    /**
     * @return array<string, Report> key => instance
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $out = [];
        foreach ($this->reportClasses as $class) {
            /** @var Report $instance */
            $instance = app($class);
            $out[$instance->key()] = $instance;
        }

        // sort by module + name (stable)
        uasort($out, function (Report $a, Report $b) {
            $m = strcmp($a->module(), $b->module());
            if ($m !== 0) return $m;
            return strcmp($a->name(), $b->name());
        });

        return $this->cache = $out;
    }

    /**
     * @return array<string, array<int, Report>> module => [reports...]
     */
    public function grouped(): array
    {
        $grouped = [];
        foreach ($this->all() as $report) {
            $grouped[$report->module()][] = $report;
        }
        ksort($grouped);
        return $grouped;
    }

    public function find(string $key): ?Report
    {
        return $this->all()[$key] ?? null;
    }
}

