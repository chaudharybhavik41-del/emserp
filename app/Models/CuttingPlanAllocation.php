<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuttingPlanAllocation extends Model
{
    protected $fillable = [
        'cutting_plan_plate_id',
        'bom_item_id',
        'quantity',
        'used_area_m2',
        'used_weight_kg',
        'notes',
    ];

    public function plate(): BelongsTo
    {
        return $this->belongsTo(CuttingPlanPlate::class, 'cutting_plan_plate_id');
    }

    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(BomItem::class);
    }
}
