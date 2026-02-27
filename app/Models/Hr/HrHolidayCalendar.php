<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrHolidayCalendar extends Model
{
    protected $table = 'hr_holiday_calendars';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'year',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function holidays(): HasMany
    {
        return $this->hasMany(HrHoliday::class, 'hr_holiday_calendar_id');
    }
}
