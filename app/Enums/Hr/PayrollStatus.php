<?php

namespace App\Enums\Hr;

enum PayrollStatus: string
{
    case DRAFT = 'draft';
    case PROCESSED = 'processed';
    case APPROVED = 'approved';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case HOLD = 'hold';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PROCESSED => 'Processed',
            self::APPROVED => 'Approved',
            self::PAID => 'Paid',
            self::CANCELLED => 'Cancelled',
            self::HOLD => 'On Hold',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PROCESSED => 'info',
            self::APPROVED => 'primary',
            self::PAID => 'success',
            self::CANCELLED => 'dark',
            self::HOLD => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'bi-file-earmark',
            self::PROCESSED => 'bi-gear',
            self::APPROVED => 'bi-check2-square',
            self::PAID => 'bi-currency-rupee',
            self::CANCELLED => 'bi-x-circle',
            self::HOLD => 'bi-pause-circle',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::PROCESSED]);
    }

    public function canProcess(): bool
    {
        return $this === self::DRAFT;
    }

    public function canApprove(): bool
    {
        return $this === self::PROCESSED;
    }

    public function canPay(): bool
    {
        return $this === self::APPROVED;
    }

    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn($case) => $case->label(), self::cases())
        );
    }
}
