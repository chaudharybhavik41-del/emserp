<?php

namespace App\Services\Accounting;

use App\Models\Item;
use App\Models\GstTaxRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GstRateRecorderService
{
    /**
     * Create / update GST history for an item.
     *
     * @param  Item         $item
     * @param  float|null   $gstRatePercent  Total GST % (e.g. 18)
     * @param  string|null  $effectiveFrom   Date string (Y-m-d) or anything Carbon can parse
     */
    public function syncForItem(Item $item, ?float $gstRatePercent, ?string $effectiveFrom = null): void
    {
        if ($gstRatePercent === null) {
            // Nothing to record
            return;
        }

        $gstRatePercent = round($gstRatePercent, 2);

        // Default to "today" if user didn't pick a date
        $date = $effectiveFrom
            ? Carbon::parse($effectiveFrom)->startOfDay()
            : now()->startOfDay();

        DB::transaction(function () use ($item, $gstRatePercent, $date) {
            /** @var GstTaxRate|null $prev */
            $prev = GstTaxRate::where('item_id', $item->id)
                ->orderByDesc('effective_from')
                ->first();

            // If existing row for same date and same rate, do nothing
            if ($prev &&
                $prev->effective_from->equalTo($date) &&
                round($prev->igst_rate, 2) === $gstRatePercent
            ) {
                return;
            }

            // If previous row has same rate and is before this date, we also skip
            if ($prev &&
                $prev->effective_from->lessThan($date) &&
                round($prev->igst_rate, 2) === $gstRatePercent
            ) {
                return;
            }

            // Close previous slab if overlapping / open-ended
            if ($prev &&
                ($prev->effective_to === null ||
                 $prev->effective_to->greaterThan($date->copy()->subDay()))
            ) {
                $prev->effective_to = $date->copy()->subDay();
                $prev->save();
            }

            // Split into CGST / SGST (half-half); IGST is full
            $half = $gstRatePercent / 2;

            GstTaxRate::create([
                'item_id'        => $item->id,
                'effective_from' => $date,
                'cgst_rate'      => $half,
                'sgst_rate'      => $half,
                'igst_rate'      => $gstRatePercent,
            ]);
        });
    }
}
