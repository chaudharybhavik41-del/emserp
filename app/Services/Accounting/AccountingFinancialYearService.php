<?php

namespace App\Services\Accounting;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class AccountingFinancialYearService
{
    public function startMonth(): int
    {
        return max(1, min(12, (int) Config::get('accounting.financial_year.start_month', 4)));
    }

    public function rangeForDate(Carbon|string $date): array
    {
        $asOf = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse((string) $date)->startOfDay();
        $startMonth = $this->startMonth();
        $startYear = $asOf->month >= $startMonth ? $asOf->year : $asOf->year - 1;

        $start = Carbon::create($startYear, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addYear()->subDay()->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'label' => sprintf('%d-%02d', $startYear, ($startYear + 1) % 100),
        ];
    }

    public function currentRange(): array
    {
        return $this->rangeForDate(now());
    }

    public function previousRange(): array
    {
        return $this->rangeForDate($this->currentRange()['start']->copy()->subDay());
    }
}
