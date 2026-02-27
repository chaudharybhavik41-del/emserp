<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\GstAccountRate;
use Carbon\Carbon;

class AccountGstResolver
{
    /**
     * Resolve GST slab for a ledger (expense account) on a given transaction date.
     *
     * @param  Account           $account
     * @param  \DateTime|string $date
     * @return GstAccountRate|null
     */
    public function getRateForAccountOnDate(Account $account, $date): ?GstAccountRate
    {
        $date = Carbon::parse($date)->startOfDay();

        return GstAccountRate::where('account_id', $account->id)
            ->where('effective_from', '<=', $date->toDateString())
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();
    }
}
