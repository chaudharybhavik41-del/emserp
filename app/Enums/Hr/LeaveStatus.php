<?php

namespace App\Enums\Hr;

enum LeaveStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case RECALLED = 'recalled';
    case PARTIALLY_APPROVED = 'partially_approved';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
            self::RECALLED => 'Recalled',
            self::PARTIALLY_APPROVED => 'Partially Approved',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::CANCELLED => 'dark',
            self::RECALLED => 'info',
            self::PARTIALLY_APPROVED => 'primary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'bi-file-earmark',
            self::PENDING => 'bi-hourglass-split',
            self::APPROVED => 'bi-check-circle',
            self::REJECTED => 'bi-x-circle',
            self::CANCELLED => 'bi-slash-circle',
            self::RECALLED => 'bi-arrow-counterclockwise',
            self::PARTIALLY_APPROVED => 'bi-check-circle-fill',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING]);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING, self::APPROVED]);
    }

    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn($case) => $case->label(), self::cases())
        );
    }
}
