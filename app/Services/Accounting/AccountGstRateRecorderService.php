<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\GstAccountRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountGstRateRecorderService
{
    /**
     * Create / update GST history for an expense ledger (account).
     *
     * @param  Account     $account
     * @param  float|null  $gstRatePercent   Total GST % (e.g. 18)
     * @param  string|null $effectiveFrom    Date string (Y-m-d) or anything Carbon can parse
     * @param  string|null $hsnSacCode       Optional HSN / SAC code
     * @param  bool        $isReverseCharge  Whether this ledger is under RCM
     */
    public function syncForAccount(
        Account $account,
        ?float $gstRatePercent,
        ?string $effectiveFrom = null,
        ?string $hsnSacCode = null,
        bool $isReverseCharge = false
    ): void {
        if ($gstRatePercent === null) {
            // Nothing to record
            return;
        }

        $gstRatePercent = round($gstRatePercent, 2);

        // Default to "today" if user didn't pick a date
        $date = $effectiveFrom
            ? Carbon::parse($effectiveFrom)->startOfDay()
            : now()->startOfDay();

        DB::transaction(function () use ($account, $gstRatePercent, $date, $hsnSacCode, $isReverseCharge) {
            /** @var GstAccountRate|null $prev */
            $prev = GstAccountRate::where('account_id', $account->id)
                ->orderByDesc('effective_from')
                ->first();

            // If existing row for same date AND same rate + flags -> nothing to change
            if ($prev &&
                $prev->effective_from->equalTo($date) &&
                round($prev->igst_rate, 2) === $gstRatePercent &&
                (string) $prev->hsn_sac_code === (string) $hsnSacCode &&
                (bool) $prev->is_reverse_charge === (bool) $isReverseCharge
            ) {
                return;
            }

            // If previous row has same rate & flags and is before this date, also skip
            if ($prev &&
                $prev->effective_from->lessThan($date) &&
                round($prev->igst_rate, 2) === $gstRatePercent &&
                (string) $prev->hsn_sac_code === (string) $hsnSacCode &&
                (bool) $prev->is_reverse_charge === (bool) $isReverseCharge
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

            $half = $gstRatePercent / 2;

            GstAccountRate::create([
                'account_id'       => $account->id,
                'hsn_sac_code'     => $hsnSacCode,
                'effective_from'   => $date,
                'cgst_rate'        => $half,
                'sgst_rate'        => $half,
                'igst_rate'        => $gstRatePercent,
                'is_reverse_charge'=> $isReverseCharge,
            ]);
        });
    }
}
