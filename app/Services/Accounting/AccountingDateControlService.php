<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountingPeriodLock;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AccountingDateControlService
{
    public function __construct(
        protected AccountingFinancialYearService $financialYearService
    ) {
    }

    public function assertDateOpenForValidation(
        Carbon|string $date,
        int $companyId,
        string $field = 'voucher_date',
        ?string $label = null
    ): void {
        $violations = $this->violations($date, $companyId, $label);
        if (empty($violations)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => implode(' ', $violations),
        ]);
    }

    public function assertDateOpenForRuntime(
        Carbon|string $date,
        int $companyId,
        ?string $label = null
    ): void {
        $violations = $this->violations($date, $companyId, $label);
        if (empty($violations)) {
            return;
        }

        throw new RuntimeException(implode(' ', $violations));
    }

    protected function violations(Carbon|string $date, int $companyId, ?string $label = null): array
    {
        $asOf = $date instanceof Carbon
            ? $date->copy()->startOfDay()
            : Carbon::parse((string) $date)->startOfDay();

        $messages = [];
        $periodLock = $this->activeLockForDate($companyId, $asOf);
        if ($periodLock) {
            $messages[] = trim(
                ($label ?: 'Accounting date')
                . ' falls in locked period '
                . $periodLock->from_date?->format('d-m-Y')
                . ' to '
                . $periodLock->to_date?->format('d-m-Y')
                . ($periodLock->title ? ' (' . $periodLock->title . ')' : '')
                . '.'
                . ($periodLock->reason ? ' ' . $periodLock->reason : '')
            );
        }

        $maxBackdateDays = Config::get('accounting.date_controls.max_backdate_days');
        if (is_numeric($maxBackdateDays) && (int) $maxBackdateDays >= 0) {
            $cutoff = now()->startOfDay()->subDays((int) $maxBackdateDays);
            if ($asOf->lt($cutoff)) {
                $messages[] = trim(
                    ($label ?: 'Accounting date')
                    . ' is older than the allowed back-date window of '
                    . (int) $maxBackdateDays
                    . ' day(s). Earliest allowed date is '
                    . $cutoff->format('d-m-Y')
                    . '.'
                );
            }
        }

        $maxFutureDays = Config::get('accounting.date_controls.max_future_days');
        if (is_numeric($maxFutureDays) && (int) $maxFutureDays >= 0) {
            $futureCutoff = now()->startOfDay()->addDays((int) $maxFutureDays);
            if ($asOf->gt($futureCutoff)) {
                $messages[] = trim(
                    ($label ?: 'Accounting date')
                    . ' is later than the allowed future-date window of '
                    . (int) $maxFutureDays
                    . ' day(s). Latest allowed date is '
                    . $futureCutoff->format('d-m-Y')
                    . '.'
                );
            }
        }

        if ($this->configEnabled(Config::get('accounting.date_controls.enforce_current_financial_year'))) {
            $currentFinancialYear = $this->financialYearService->currentRange();
            if ($asOf->lt($currentFinancialYear['start']) || $asOf->gt($currentFinancialYear['end'])) {
                $messages[] = trim(
                    ($label ?: 'Accounting date')
                    . ' falls outside the active financial year '
                    . $currentFinancialYear['label']
                    . ' ('
                    . $currentFinancialYear['start']->format('d-m-Y')
                    . ' to '
                    . $currentFinancialYear['end']->format('d-m-Y')
                    . ').'
                );
            }
        }

        return $messages;
    }

    public function activeLockForDate(int $companyId, Carbon|string $date): ?AccountingPeriodLock
    {
        $asOf = $date instanceof Carbon
            ? $date->copy()->startOfDay()
            : Carbon::parse((string) $date)->startOfDay();

        return AccountingPeriodLock::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('from_date', '<=', $asOf->toDateString())
            ->whereDate('to_date', '>=', $asOf->toDateString())
            ->orderByDesc('to_date')
            ->orderByDesc('id')
            ->first();
    }

    protected function configEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
