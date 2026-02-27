<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class HrShift extends Model
{
    use HasFactory;

    protected $table = 'hr_shifts';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'short_name',
        'description',
        'start_time',
        'end_time',
        'is_night_shift',
        'spans_next_day',
        'working_hours',
        'break_duration_minutes',
        'paid_break_minutes',
        'grace_period_minutes',
        'late_mark_after_minutes',
        'half_day_late_minutes',
        'absent_after_minutes',
        'early_going_grace_minutes',
        'half_day_early_minutes',
        'ot_applicable',
        'ot_start_after_minutes',
        'ot_rate_multiplier',
        'ot_rate_holiday_multiplier',
        'max_ot_hours_per_day',
        'min_ot_minutes',
        'is_flexible',
        'flex_start_from',
        'flex_start_to',
        'auto_punch_out_time',
        'auto_half_day_on_single_punch',
        'color_code',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'flex_start_from' => 'datetime:H:i',
        'flex_start_to' => 'datetime:H:i',
        'auto_punch_out_time' => 'datetime:H:i',
        'is_night_shift' => 'boolean',
        'spans_next_day' => 'boolean',
        'ot_applicable' => 'boolean',
        'is_flexible' => 'boolean',
        'auto_half_day_on_single_punch' => 'boolean',
        'is_active' => 'boolean',
        'working_hours' => 'decimal:2',
        'ot_rate_multiplier' => 'decimal:2',
        'ot_rate_holiday_multiplier' => 'decimal:2',
    ];

    // Relationships

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'default_shift_id');
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(HrShiftRoster::class, 'hr_shift_id');
    }

    public function dailyAssignments(): HasMany
    {
        return $this->hasMany(HrDailyShiftAssignment::class, 'hr_shift_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNightShifts($query)
    {
        return $query->where('is_night_shift', true);
    }

    // Methods

    public function getStartTimeForDate(Carbon $date): Carbon
    {
        return $date->copy()->setTimeFromTimeString($this->start_time->format('H:i:s'));
    }

    public function getEndTimeForDate(Carbon $date): Carbon
    {
        $endTime = $date->copy()->setTimeFromTimeString($this->end_time->format('H:i:s'));
        
        if ($this->spans_next_day) {
            $endTime->addDay();
        }
        
        return $endTime;
    }

    public function getGraceEndTime(Carbon $date): Carbon
    {
        return $this->getStartTimeForDate($date)->addMinutes($this->grace_period_minutes);
    }

    public function getLateMarkTime(Carbon $date): Carbon
    {
        return $this->getStartTimeForDate($date)->addMinutes($this->late_mark_after_minutes);
    }

    public function getHalfDayLateTime(Carbon $date): Carbon
    {
        return $this->getStartTimeForDate($date)->addMinutes($this->half_day_late_minutes);
    }

    public function getAbsentTime(Carbon $date): Carbon
    {
        return $this->getStartTimeForDate($date)->addMinutes($this->absent_after_minutes);
    }

    public function calculateLateMinutes(Carbon $inTime, Carbon $date): int
    {
        $shiftStart = $this->getStartTimeForDate($date);
        $graceEnd = $this->getGraceEndTime($date);
        
        if ($inTime->lte($graceEnd)) {
            return 0;
        }
        
        return $inTime->diffInMinutes($shiftStart);
    }

    public function calculateEarlyMinutes(Carbon $outTime, Carbon $date): int
    {
        $shiftEnd = $this->getEndTimeForDate($date);
        $earlyGraceStart = $shiftEnd->copy()->subMinutes($this->early_going_grace_minutes);
        
        if ($outTime->gte($earlyGraceStart)) {
            return 0;
        }
        
        return $shiftEnd->diffInMinutes($outTime);
    }

    public function calculateOvertimeMinutes(Carbon $outTime, Carbon $date): int
    {
        if (!$this->ot_applicable) {
            return 0;
        }
        
        $shiftEnd = $this->getEndTimeForDate($date);
        $otStartTime = $shiftEnd->copy()->addMinutes($this->ot_start_after_minutes);
        
        if ($outTime->lte($otStartTime)) {
            return 0;
        }
        
        $otMinutes = $outTime->diffInMinutes($shiftEnd);
        
        // Check minimum OT threshold
        if ($otMinutes < $this->min_ot_minutes) {
            return 0;
        }
        
        // Cap at maximum
        $maxOtMinutes = $this->max_ot_hours_per_day * 60;
        return min($otMinutes, $maxOtMinutes);
    }

    public function determineAttendanceStatus(
        ?Carbon $inTime, 
        ?Carbon $outTime, 
        Carbon $date
    ): array {
        // No punch = absent
        if (!$inTime && !$outTime) {
            return [
                'status' => 'absent',
                'late_minutes' => 0,
                'early_minutes' => 0,
                'working_hours' => 0,
                'ot_minutes' => 0,
            ];
        }
        
        // Single punch handling
        if ($inTime && !$outTime) {
            if ($this->auto_half_day_on_single_punch) {
                return [
                    'status' => 'half_day',
                    'late_minutes' => $this->calculateLateMinutes($inTime, $date),
                    'early_minutes' => 0,
                    'working_hours' => $this->working_hours / 2,
                    'ot_minutes' => 0,
                ];
            }
            return [
                'status' => 'present',
                'late_minutes' => $this->calculateLateMinutes($inTime, $date),
                'early_minutes' => 0,
                'working_hours' => $this->working_hours,
                'ot_minutes' => 0,
                'needs_regularization' => true,
            ];
        }
        
        $lateMinutes = $this->calculateLateMinutes($inTime, $date);
        $earlyMinutes = $this->calculateEarlyMinutes($outTime, $date);
        $workingMinutes = $outTime->diffInMinutes($inTime) - $this->break_duration_minutes;
        $workingHours = round($workingMinutes / 60, 2);
        $otMinutes = $this->calculateOvertimeMinutes($outTime, $date);
        
        // Determine status
        $status = 'present';
        
        if ($lateMinutes >= $this->absent_after_minutes) {
            $status = 'absent';
        } elseif ($lateMinutes >= $this->half_day_late_minutes || $earlyMinutes >= $this->half_day_early_minutes) {
            $status = 'half_day';
        } elseif ($lateMinutes > 0 && $earlyMinutes > 0) {
            $status = 'late_and_early';
        } elseif ($lateMinutes > 0) {
            $status = 'late';
        } elseif ($earlyMinutes > 0) {
            $status = 'early_leaving';
        }
        
        return [
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'early_minutes' => $earlyMinutes,
            'working_hours' => $workingHours,
            'ot_minutes' => $otMinutes,
        ];
    }

    public static function generateCode(): string
    {
        $lastShift = self::orderByDesc('id')->first();
        $nextNum = $lastShift ? ($lastShift->id + 1) : 1;
        return 'SH-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }
}
