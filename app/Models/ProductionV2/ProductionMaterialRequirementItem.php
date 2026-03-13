<?php

namespace App\Models\ProductionV2;

use App\Models\Item;
use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionMaterialRequirementItem extends Model
{
    use HasFactory;

    protected $table = 'production_v2_material_requirement_items';

    protected $fillable = [
        'material_requirement_id',
        'material_item_id',
        'material_category',
        'material_grade',
        'thickness_mm',
        'width_mm',
        'length_mm',
        'profile_text',
        'part_revision_root_ids_json',
        'required_qty',
        'uom_id',
        'required_weight_kg',
        'planned_cut_qty_snapshot',
        'remarks',
    ];

    protected $casts = [
        'thickness_mm' => 'decimal:3',
        'width_mm' => 'decimal:3',
        'length_mm' => 'decimal:3',
        'part_revision_root_ids_json' => 'array',
        'required_qty' => 'decimal:3',
        'required_weight_kg' => 'decimal:3',
        'planned_cut_qty_snapshot' => 'decimal:3',
    ];

    public function materialRequirement()
    {
        return $this->belongsTo(ProductionMaterialRequirement::class, 'material_requirement_id');
    }

    public function materialItem()
    {
        return $this->belongsTo(Item::class, 'material_item_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }
}
