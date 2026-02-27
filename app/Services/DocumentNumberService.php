<?php

namespace App\Services;

use App\Models\MaterialReceipt;
use App\Models\MaterialVendorReturn;
use App\Models\StoreRequisition;
use App\Models\StoreIssue;
use App\Models\StoreReturn;
use App\Models\StoreStockAdjustment;

class DocumentNumberService
{
    /**
     * Format helper: PREFIX-YY-XXXX where YY is current year (2 digits)
     * and XXXX is the model's primary key, zero-padded to 4 digits.
     */
    protected function format(string $prefix, int $id): string
    {
        $yearShort = now()->format('y');

        return sprintf('%s-%s-%04d', $prefix, $yearShort, $id);
    }

    public function materialReceipt(MaterialReceipt $receipt): string
    {
        return $this->format('GRN', (int) $receipt->id);
    }

    public function storeRequisition(StoreRequisition $requisition): string
    {
        return $this->format('SR', (int) $requisition->id);
    }

    public function storeIssue(StoreIssue $issue): string
    {
        return $this->format('ISS', (int) $issue->id);
    }

    public function storeReturn(StoreReturn $return): string
    {
        return $this->format('RTN', (int) $return->id);
    }

    public function materialVendorReturn(MaterialVendorReturn $vendorReturn): string
    {
        return $this->format('VRET', (int) $vendorReturn->id);
    }

    public function stockAdjustment(StoreStockAdjustment $adjustment): string
    {
        return $this->format('STAD', (int) $adjustment->id);
    }
}

