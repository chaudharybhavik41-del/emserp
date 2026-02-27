<?php

namespace App\Enums;

enum BomItemMaterialSource: string
{
    case NEW_PURCHASE    = 'new_purchase';
    case REMNANT         = 'remnant';
    case CLIENT_SUPPLIED = 'client_supplied';
}
