<?php

namespace App\Models\ProductionV2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionCuttingPlanPlate extends Model
{
    use HasFactory;

    protected $table = 'production_v2_cutting_plan_plates';

    protected $fillable = [
        'cutting_plan_id',
        'plate_ref',
        'planned_width_mm',
        'planned_length_mm',
        'planned_qty',
        'remarks',
    ];

    protected $casts = [
        'planned_width_mm' => 'decimal:3',
        'planned_length_mm' => 'decimal:3',
        'planned_qty' => 'decimal:3',
    ];

    public function cuttingPlan()
    {
        return $this->belongsTo(ProductionCuttingPlan::class, 'cutting_plan_id');
    }

    public function allocations()
    {
        return $this->hasMany(ProductionCuttingPlanAllocation::class, 'cutting_plan_plate_id')
            ->with('partDefinition')
            ->orderBy('id');
    }
}
