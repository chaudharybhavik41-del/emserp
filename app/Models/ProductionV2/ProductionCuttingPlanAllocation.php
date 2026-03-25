<?php

namespace App\Models\ProductionV2;

use App\Models\StoreStockItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionCuttingPlanAllocation extends Model
{
    use HasFactory;

    protected $table = 'production_v2_cutting_plan_allocations';

    protected $fillable = [
        'cutting_plan_id',
        'cutting_plan_plate_id',
        'part_definition_id',
        'planned_qty',
        'planned_blank_ref',
        'planned_blank_width_mm',
        'planned_blank_length_mm',
        'mother_stock_item_id',
        'cut_size_text',
        'cut_width_mm',
        'cut_length_mm',
        'thickness_mm',
        'allocation_group',
        'remarks',
    ];

    protected $casts = [
        'planned_qty' => 'decimal:3',
        'planned_blank_width_mm' => 'decimal:3',
        'planned_blank_length_mm' => 'decimal:3',
        'cut_width_mm' => 'decimal:3',
        'cut_length_mm' => 'decimal:3',
        'thickness_mm' => 'decimal:3',
    ];

    public function cuttingPlan()
    {
        return $this->belongsTo(ProductionCuttingPlan::class, 'cutting_plan_id');
    }

    public function plannedPlate()
    {
        return $this->belongsTo(ProductionCuttingPlanPlate::class, 'cutting_plan_plate_id');
    }

    public function partDefinition()
    {
        return $this->belongsTo(ProductionPartDefinition::class, 'part_definition_id');
    }

    public function motherStock()
    {
        return $this->belongsTo(StoreStockItem::class, 'mother_stock_item_id');
    }
}
