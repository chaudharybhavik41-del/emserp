<?php

namespace App\Enums\Hr;

enum AttendanceStatus: string
{
    case PRESENT = 'present';
    case ABSENT = 'absent';
    case HALF_DAY = 'half_day';
    case WEEKLY_OFF = 'weekly_off';
    case HOLIDAY = 'holiday';
    case LEAVE = 'leave';
    case ON_DUTY = 'on_duty';
    case COMP_OFF = 'comp_off';
    case LATE = 'late';
    case EARLY_LEAVING = 'early_leaving';
    case LATE_AND_EARLY = 'late_and_early';

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Present',
            self::ABSENT => 'Absent',
            self::HALF_DAY => 'Half Day',
            self::WEEKLY_OFF => 'Weekly Off',
            self::HOLIDAY => 'Holiday',
            self::LEAVE => 'Leave',
            self::ON_DUTY => 'On Duty',
            self::COMP_OFF => 'Comp Off',
            self::LATE => 'Late',
            self::EARLY_LEAVING => 'Early Leaving',
            self::LATE_AND_EARLY => 'Late & Early',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PRESENT => 'success',
            self::ABSENT => 'danger',
            self::HALF_DAY => 'warning',
            self::WEEKLY_OFF => 'secondary',
            self::HOLIDAY => 'info',
            self::LEAVE => 'primary',
            self::ON_DUTY => 'success',
            self::COMP_OFF => 'info',
            self::LATE => 'warning',
            self::EARLY_LEAVING => 'warning',
            self::LATE_AND_EARLY => 'danger',
        };
    }

    public function shortCode(): string
    {
        return match ($this) {
            self::PRESENT => 'P',
            self::ABSENT => 'A',
            self::HALF_DAY => 'HD',
            self::WEEKLY_OFF => 'WO',
            self::HOLIDAY => 'H',
            self::LEAVE => 'L',
            self::ON_DUTY => 'OD',
            self::COMP_OFF => 'CO',
            self::LATE => 'LT',
            self::EARLY_LEAVING => 'EL',
            self::LATE_AND_EARLY => 'LE',
        };
    }

    public function isPaidDay(): bool
    {
        return match ($this) {
            self::PRESENT, self::WEEKLY_OFF, self::HOLIDAY, 
            self::ON_DUTY, self::COMP_OFF => true,
            self::HALF_DAY => true, // 0.5 paid
            self::LEAVE => true, // Depends on leave type
            self::LATE, self::EARLY_LEAVING, self::LATE_AND_EARLY => true, // Usually paid with deductions
            self::ABSENT => false,
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn($case) => $case->label(), self::cases())
        );
    }
}
