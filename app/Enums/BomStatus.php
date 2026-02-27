<?php

namespace App\Enums;

enum BomStatus: string
{
    case DRAFT     = 'draft';
    case FINALIZED = 'finalized';
    case ACTIVE    = 'active';
    case CLOSED    = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT     => 'Draft',
            self::FINALIZED => 'Finalized',
            self::ACTIVE    => 'Active',
            self::CLOSED    => 'Closed',
        };
    }

    public static function options(): array
    {
        return [
            self::DRAFT->value     => self::DRAFT->label(),
            self::FINALIZED->value => self::FINALIZED->label(),
            self::ACTIVE->value    => self::ACTIVE->label(),
            self::CLOSED->value    => self::CLOSED->label(),
        ];
    }
}
