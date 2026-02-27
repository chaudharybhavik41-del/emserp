<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Party;
use App\Support\MoneyHelper;

class GstHelper
{
    /**
     * Backward-compatible GST split helper.
     *
     * Some older controllers (e.g. PurchaseOrderController::storeFromRfq)
     * expect GstHelper::split($taxAmount) to return an array with
     * keys: cgst, sgst, igst.
     *
     * Default behaviour is INTRA-state: split tax amount into CGST + SGST.
     * Pass $gstType = 'inter' (or 'igst') if you want full IGST.
     */
    public static function split($taxAmount, ?string $gstType = 'intra'): array
    {
        $taxAmount = (float) ($taxAmount ?? 0);

        if ($taxAmount <= 0) {
            return ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
        }

        $gstType = $gstType ? strtolower(trim((string) $gstType)) : 'intra';

        // Do money math in paise to avoid float rounding drift.
        $taxPaise = MoneyHelper::toPaise($taxAmount);

        if (in_array($gstType, ['inter', 'igst'], true)) {
            return [
                'cgst' => 0.0,
                'sgst' => 0.0,
                'igst' => (float) MoneyHelper::fromPaise($taxPaise),
            ];
        }

        // Default: intra-state GST (CGST + SGST)
        [$cgstPaise, $sgstPaise] = MoneyHelper::splitTwo($taxPaise);

        return [
            'cgst' => (float) MoneyHelper::fromPaise($cgstPaise),
            'sgst' => (float) MoneyHelper::fromPaise($sgstPaise),
            'igst' => 0.0,
        ];
    }

    /**
     * Calculate GST split (CGST/SGST/IGST) for a taxable amount and total tax percent
     * based on company and vendor state codes.
     *
     * @return array [gstType, cgstPct, sgstPct, igstPct, cgstAmt, sgstAmt, igstAmt, taxAmount]
     */
    public static function calculateSplit(?Company $company, ?Party $vendor, float $taxable, float $taxPercent): array
    {
        $gstType    = self::determineGstType($company, $vendor);
        $taxPercent = $taxPercent > 0 ? $taxPercent : 0.0;
        $taxable    = $taxable > 0 ? $taxable : 0.0;

        // Do money math in paise to reduce float rounding drift.
        $taxablePaise = MoneyHelper::toPaise($taxable);
        $taxPaise     = (int) round(($taxablePaise * $taxPercent) / 100, 0);

        $taxAmount = (float) MoneyHelper::fromPaise($taxPaise);

        $cgstPct = $sgstPct = $igstPct = 0.0;
        $cgstAmt = $sgstAmt = $igstAmt = 0.0;

        if ($taxAmount <= 0 || $taxPercent <= 0 || ! $gstType) {
            return [$gstType, $cgstPct, $sgstPct, $igstPct, $cgstAmt, $sgstAmt, $igstAmt, $taxAmount];
        }

        if ($gstType === 'intra') {
            // CGST + SGST
            $halfPct = $taxPercent / 2.0;

            [$cgstPaise, $sgstPaise] = MoneyHelper::splitTwo($taxPaise);

            $cgstPct = $halfPct;
            $sgstPct = $halfPct;
            $cgstAmt = (float) MoneyHelper::fromPaise($cgstPaise);
            $sgstAmt = (float) MoneyHelper::fromPaise($sgstPaise);
        } elseif ($gstType === 'inter') {
            // IGST only
            $igstPct = $taxPercent;
            $igstAmt = (float) MoneyHelper::fromPaise($taxPaise);
        }

        return [$gstType, $cgstPct, $sgstPct, $igstPct, $cgstAmt, $sgstAmt, $igstAmt, $taxAmount];
    }

    /**
     * Determine GST type based on company & vendor state codes.
     * Returns 'intra', 'inter' or null if not determinable.
     */
    public static function determineGstType(?Company $company, ?Party $vendor): ?string
    {
        $companyCode = self::companyStateCode($company);
        $vendorCode  = self::vendorStateCode($vendor);

        if (! $companyCode || ! $vendorCode) {
            return null;
        }

        return $companyCode === $vendorCode ? 'intra' : 'inter';
    }

    public static function companyStateCode(?Company $company): ?string
    {
        if (! $company) {
            return null;
        }

        if (! empty($company->gst_number)) {
            $code = substr($company->gst_number, 0, 2);
            if (ctype_digit($code)) {
                return $code;
            }
        }

        return null;
    }

    public static function vendorStateCode(?Party $vendor): ?string
    {
        if (! $vendor) {
            return null;
        }

        if (! empty($vendor->gst_state_code)) {
            return $vendor->gst_state_code;
        }

        if (! empty($vendor->gstin)) {
            $code = substr($vendor->gstin, 0, 2);
            if (ctype_digit($code)) {
                return $code;
            }
        }

        return null;
    }
}
