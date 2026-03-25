<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class HrWeeklyOffPattern extends Model
{
    protected $table = 'hr_weekly_off_patterns';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'sunday_off',
        'monday_off',
        'tuesday_off',
        'wednesday_off',
        'thursday_off',
        'friday_off',
        'saturday_off',
        'saturday_pattern',
        'is_active',
    ];

    protected $casts = [
        'sunday_off' => 'boolean',
        'monday_off' => 'boolean',
        'tuesday_off' => 'boolean',
        'wednesday_off' => 'boolean',
        'thursday_off' => 'boolean',
        'friday_off' => 'boolean',
        'saturday_off' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function rosters(): HasMany
    {
        return $this->hasMany(HrShiftRoster::class, 'hr_weekly_off_pattern_id');
    }

    public function isWeeklyOffForDate(CarbonInterface $date): bool
    {
        $dayOfWeek = $date->dayOfWeek;

        return match ($dayOfWeek) {
            Carbon::SUNDAY => (bool) $this->sunday_off,
            Carbon::MONDAY => (bool) $this->monday_off,
            Carbon::TUESDAY => (bool) $this->tuesday_off,
            Carbon::WEDNESDAY => (bool) $this->wednesday_off,
            Carbon::THURSDAY => (bool) $this->thursday_off,
            Carbon::FRIDAY => (bool) $this->friday_off,
            Carbon::SATURDAY => $this->isSaturdayOffForDate($date),
            default => false,
        };
    }

    private function isSaturdayOffForDate(CarbonInterface $date): bool
    {
        if (! $this->saturday_off) {
            return false;
        }

        $weekOfMonth = intdiv(((int) $date->day) - 1, 7) + 1;

        return match ($this->saturday_pattern) {
            'all_working' => false,
            'all_off' => true,
            'alternate' => $weekOfMonth % 2 === 1,
            'first_third_off' => in_array($weekOfMonth, [1, 3], true),
            'second_fourth_off' => in_array($weekOfMonth, [2, 4], true),
            default => true,
        };
    }
}
