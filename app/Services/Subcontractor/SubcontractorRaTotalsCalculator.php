<?php

namespace App\Services\Subcontractor;

use App\Models\SubcontractorRaBill;

class SubcontractorRaTotalsCalculator
{
    public function calculateForBill(SubcontractorRaBill $raBill, iterable $lines, ?float $invoiceTotalInput = null): array
    {
        $computedLines = [];
        $previousAmount = 0.0;
        $currentAmount = 0.0;
        $lineNo = 1;

        foreach ($lines as $line) {
            $previousQty = (float) data_get($line, 'previous_qty', 0);
            $currentQty = (float) data_get($line, 'current_qty', 0);
            $rate = (float) data_get($line, 'rate', 0);
            $lineTotals = $this->calculateLineTotals($previousQty, $currentQty, $rate);

            $computedLines[] = [
                'model' => $line,
                'line_no' => $lineNo++,
                'cumulative_qty' => $lineTotals['cumulative_qty'],
                'previous_amount' => $lineTotals['previous_amount'],
                'current_amount' => $lineTotals['current_amount'],
                'cumulative_amount' => $lineTotals['cumulative_amount'],
            ];

            $previousAmount += $lineTotals['previous_amount'];
            $currentAmount += $lineTotals['current_amount'];
        }

        $previousAmount = round($previousAmount, 2);
        $currentAmount = round($currentAmount, 2);
        $grossAmount = round($previousAmount + $currentAmount, 2);

        $retentionPercent = (float) ($raBill->retention_percent ?? 0);
        $retentionAmount = $retentionPercent > 0
            ? round($currentAmount * ($retentionPercent / 100), 2)
            : 0.0;
        $securityDepositPercent = (float) ($raBill->security_deposit_percent ?? 0);
        $securityDepositAmount = $securityDepositPercent > 0
            ? round($currentAmount * ($securityDepositPercent / 100), 2)
            : 0.0;

        $advanceRecovery = round((float) ($raBill->advance_recovery ?? 0), 2);
        $otherDeductions = round((float) ($raBill->other_deductions ?? 0), 2);

        $netAmount = round($currentAmount - $retentionAmount - $securityDepositAmount - $advanceRecovery - $otherDeductions, 2);

        $cgstAmount = round($netAmount * (((float) ($raBill->cgst_rate ?? 0)) / 100), 2);
        $sgstAmount = round($netAmount * (((float) ($raBill->sgst_rate ?? 0)) / 100), 2);
        $igstAmount = round($netAmount * (((float) ($raBill->igst_rate ?? 0)) / 100), 2);
        $totalGst = round($cgstAmount + $sgstAmount + $igstAmount, 2);

        $tdsAmount = $this->calculateTdsAmount($netAmount, (float) ($raBill->tds_rate ?? 0));
        $calculatedTotal = round($netAmount + $totalGst - $tdsAmount, 2);
        $invoiceTotal = $invoiceTotalInput !== null
            ? round($invoiceTotalInput, 2)
            : round($calculatedTotal, 0);
        $roundOff = round($invoiceTotal - $calculatedTotal, 2);

        return [
            'lines' => $computedLines,
            'header' => [
                'previous_amount' => $previousAmount,
                'current_amount' => $currentAmount,
                'gross_amount' => $grossAmount,
                'retention_amount' => $retentionAmount,
                'security_deposit_amount' => $securityDepositAmount,
                'net_amount' => $netAmount,
                'cgst_amount' => $cgstAmount,
                'sgst_amount' => $sgstAmount,
                'igst_amount' => $igstAmount,
                'total_gst' => $totalGst,
                'tds_amount' => $tdsAmount,
                'round_off' => $roundOff,
                'total_amount' => $invoiceTotal,
            ],
            'calculated_total' => $calculatedTotal,
        ];
    }

    public function calculateTdsAmount(float $netAmount, float $tdsRate): float
    {
        if ($tdsRate <= 0 || $netAmount <= 0) {
            return 0.0;
        }

        return (float) round(max(0, ($netAmount * $tdsRate) / 100), 0);
    }

    protected function calculateLineTotals(float $previousQty, float $currentQty, float $rate): array
    {
        $cumulativeQty = $previousQty + $currentQty;

        return [
            'cumulative_qty' => $cumulativeQty,
            'previous_amount' => round($previousQty * $rate, 2),
            'current_amount' => round($currentQty * $rate, 2),
            'cumulative_amount' => round($cumulativeQty * $rate, 2),
        ];
    }
}
