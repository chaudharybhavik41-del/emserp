<?php

namespace App\Models\Production;

use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionDprLine extends Model
{
    use HasFactory;

    protected $table = 'production_dpr_lines';

    protected $fillable = [
        'production_dpr_id',
        'production_plan_item_id',
        'production_plan_item_activity_id',
        'production_assembly_id',
        'is_completed',
        'remarks',
        'qty',
        'qty_uom_id',
        'minutes_spent',
        'efficiency_metric',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'qty' => 'decimal:3',
        'minutes_spent' => 'decimal:2',
        'efficiency_metric' => 'decimal:3',
    ];

    public function dpr()
    {
        return $this->belongsTo(ProductionDpr::class, 'production_dpr_id');
    }

    public function planItem()
    {
        return $this->belongsTo(ProductionPlanItem::class, 'production_plan_item_id');
    }

    public function planItemActivity()
    {
        return $this->belongsTo(ProductionPlanItemActivity::class, 'production_plan_item_activity_id');
    }

    public function qtyUom()
    {
        return $this->belongsTo(Uom::class, 'qty_uom_id');
    }
}
