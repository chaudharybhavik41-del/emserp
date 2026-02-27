<?php

namespace App\Enums\Hr;

enum EmployeeStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case RESIGNED = 'resigned';
    case TERMINATED = 'terminated';
    case ABSCONDED = 'absconded';
    case RETIRED = 'retired';
    case DECEASED = 'deceased';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::RESIGNED => 'Resigned',
            self::TERMINATED => 'Terminated',
            self::ABSCONDED => 'Absconded',
            self::RETIRED => 'Retired',
            self::DECEASED => 'Deceased',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
            self::RESIGNED => 'warning',
            self::TERMINATED => 'danger',
            self::ABSCONDED => 'danger',
            self::RETIRED => 'info',
            self::DECEASED => 'dark',
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
