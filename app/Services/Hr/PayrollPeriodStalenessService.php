<?php

namespace App\Services\Hr;

use App\Models\Hr\HrPayrollPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class PayrollPeriodStalenessService
{
    public function markPeriodsOverlappingRange(CarbonInterface|string $start, CarbonInterface|string $end, string $reason): int
    {
        $rangeStart = $start instanceof CarbonInterface ? $start->copy()->startOfDay() : Carbon::parse($start)->startOfDay();
        $rangeEnd = $end instanceof CarbonInterface ? $end->copy()->startOfDay() : Carbon::parse($end)->startOfDay();

        if ($rangeEnd->lessThan($rangeStart)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd, $rangeStart];
        }

        return HrPayrollPeriod::query()
            ->whereNotIn('status', ['paid', 'closed'])
            ->whereDate('attendance_start', '<=', $rangeEnd->toDateString())
            ->whereDate('attendance_end', '>=', $rangeStart->toDateString())
            ->update([
                'source_data_changed_at' => now(),
                'source_data_change_reason' => $reason,
            ]);
    }

    public function markPeriodsFromDate(CarbonInterface|string $start, string $reason): int
    {
        return $this->markPeriodsOverlappingRange($start, Carbon::create(2099, 12, 31), $reason);
    }

    public function clear(HrPayrollPeriod $period): void
    {
        $period->forceFill([
            'source_data_changed_at' => null,
            'source_data_change_reason' => null,
        ])->save();
    }
}
