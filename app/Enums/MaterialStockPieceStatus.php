<?php

namespace App\Enums;

enum MaterialStockPieceStatus: string
{
    case AVAILABLE = 'available';
    case RESERVED  = 'reserved';
    case CONSUMED  = 'consumed';
    case SCRAP     = 'scrap';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available',
            self::RESERVED  => 'Reserved',
            self::CONSUMED  => 'Consumed',
            self::SCRAP     => 'Scrap',
        };
    }

    public static function options(): array
    {
        return [
            self::AVAILABLE->value => self::AVAILABLE->label(),
            self::RESERVED->value  => self::RESERVED->label(),
            self::CONSUMED->value  => self::CONSUMED->label(),
            self::SCRAP->value     => self::SCRAP->label(),
        ];
    }
}
