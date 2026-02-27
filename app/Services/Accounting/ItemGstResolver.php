<?php

namespace App\Services\Accounting;

use App\Models\GstTaxRate;
use App\Models\Item;
use Carbon\Carbon;

class ItemGstResolver
{
    /**
     * Resolve GST slab for an item on a given transaction date.
     *
     * @param  Item              $item
     * @param  \DateTime|string $date
     * @return GstTaxRate|null
     */
    public function getRateForItemOnDate(Item $item, $date): ?GstTaxRate
    {
        $date = Carbon::parse($date)->startOfDay();

        return $item->gstTaxRates()
            ->where('effective_from', '<=', $date->toDateString())
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();
    }
}
