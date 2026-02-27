<?php

namespace App\Services\Accounting;

use App\Models\PurchaseBill;
use App\Models\PurchaseBillLine;

class PurchaseBillTaxCalculator
{
    public function __construct(
        protected ItemGstResolver $itemGstResolver,
        protected AccountGstResolver $accountGstResolver,
    ) {
    }

    /**
     * Recalculate GST for all lines and update bill header totals.
     *
     * This is called BEFORE posting (and before PurchaseBillPostingService),
     * typically in PurchaseBillController@store/update.
     */
    public function recalculate(PurchaseBill $bill): void
    {
        $bill->loadMissing('lines.item', 'lines.account');

        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;
        $totalTax  = 0.0;
        $totalAmount = 0.0;

        foreach ($bill->lines as $line) {
            /** @var PurchaseBillLine $line */
            $basic    = (float) $line->basic_amount;
            $discount = (float) $line->discount_amount;
            $taxable  = max(0, $basic - $discount);

            $cgstRate = 0.0;
            $sgstRate = 0.0;
            $igstRate = 0.0;

            // Decide GST source: item-based or account-based
            if ($line->item) {
                $rateRow = $this->itemGstResolver->getRateForItemOnDate(
                    $line->item,
                    $bill->bill_date
                );
            } elseif ($line->account) {
                $rateRow = $this->accountGstResolver->getRateForAccountOnDate(
                    $line->account,
                    $bill->bill_date
                );
            } else {
                $rateRow = null;
            }

            if ($rateRow) {
                $cgstRate = (float) $rateRow->cgst_rate;
                $sgstRate = (float) $rateRow->sgst_rate;
                $igstRate = (float) $rateRow->igst_rate;
            }

            $cgstAmount = round($taxable * $cgstRate / 100, 2);
            $sgstAmount = round($taxable * $sgstRate / 100, 2);
            $igstAmount = round($taxable * $igstRate / 100, 2);

            // Persist on line (assumes you already have these columns; if names differ, adjust)
            $line->cgst_rate   = $cgstRate;
            $line->sgst_rate   = $sgstRate;
            $line->igst_rate   = $igstRate;
            $line->cgst_amount = $cgstAmount;
            $line->sgst_amount = $sgstAmount;
            $line->igst_amount = $igstAmount;
            $line->taxable_amount = $taxable;
            $line->save();

            $totalCgst  += $cgstAmount;
            $totalSgst  += $sgstAmount;
            $totalIgst  += $igstAmount;
            $totalTax   += ($cgstAmount + $sgstAmount + $igstAmount);
            $totalAmount += $taxable + $cgstAmount + $sgstAmount + $igstAmount;
        }

        // Update bill header totals (field names based on your existing code)
        $bill->total_cgst   = round($totalCgst, 2);
        $bill->total_sgst   = round($totalSgst, 2);
        $bill->total_igst   = round($totalIgst, 2);
        $bill->total_tax    = round($totalTax, 2);
        $bill->total_amount = round($totalAmount, 2);

        // TDS/TCS header amounts are still controlled by bill form logic for now.
        $bill->save();
    }
}
