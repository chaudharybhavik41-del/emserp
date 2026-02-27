<?php

namespace App\Enums;

enum BomItemMaterialCategory: string
{
    case FABRICATED_ASSEMBLY = 'fabricated_assembly';
    case STEEL_PLATE         = 'steel_plate';
    case STEEL_SECTION       = 'steel_section';
    case CONSUMABLE          = 'consumable';
    case BOUGHT_OUT          = 'bought_out';
}
