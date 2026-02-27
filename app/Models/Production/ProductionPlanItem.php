<?php

namespace App\Models\Production;

use App\Models\BomItem;
use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionPlanItem extends Model
{
    use HasFactory;

    protected $table = 'production_plan_items';

    protected $fillable = [
        'production_plan_id',
        'bom_item_id',
        'item_type',
        'item_code',
        'description',
        'assembly_mark',
        'assembly_type',
        'level',
        'sequence_no',
        'planned_qty',
        'uom_id',
        'planned_weight_kg',
        'unit_area_m2',
        'unit_cut_length_m',
        'unit_weld_length_m',
        'status',
    ];

    protected $casts = [
        'level' => 'integer',
        'sequence_no' => 'integer',
        'planned_qty' => 'decimal:3',
        'planned_weight_kg' => 'decimal:3',
        'unit_area_m2' => 'decimal:4',
        'unit_cut_length_m' => 'decimal:4',
        'unit_weld_length_m' => 'decimal:4',
    ];

    public function plan()
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function bomItem()
    {
        return $this->belongsTo(BomItem::class, 'bom_item_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function activities()
    {
        return $this->hasMany(ProductionPlanItemActivity::class, 'production_plan_item_id')
            ->orderBy('sequence_no');
    }
}
