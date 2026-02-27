<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuttingPlanPlate extends Model
{
    protected $fillable = [
        'cutting_plan_id',
        'material_stock_piece_id',
        'plate_label',
        'thickness_mm',
        'width_mm',
        'length_mm',
        'gross_area_m2',
        'gross_weight_kg',
        'source_type',
        'remarks',
    ];

    public function cuttingPlan(): BelongsTo
    {
        return $this->belongsTo(CuttingPlan::class);
    }

    public function materialStockPiece(): BelongsTo
    {
        return $this->belongsTo(MaterialStockPiece::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CuttingPlanAllocation::class);
    }

    public function getAreaM2Attribute(): ?float
    {
        if ($this->width_mm && $this->length_mm) {
            return round(($this->width_mm / 1000) * ($this->length_mm / 1000), 4);
        }

        return null;
    }
}
