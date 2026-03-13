<?php

namespace App\Models\ProductionV2;

use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionFitupConsumption extends Model
{
    use HasFactory;

    protected $table = 'production_v2_fitup_consumptions';

    protected $fillable = [
        'fitup_id',
        'assembly_id',
        'wip_item_id',
        'consumed_qty',
        'part_definition_id',
        'uom_id',
        'observed_dimension_text',
        'specified_dimension_text',
        'dimension_ok',
        'plate_number_snapshot',
        'heat_number_snapshot',
        'remarks',
    ];

    protected $casts = [
        'consumed_qty' => 'decimal:3',
        'dimension_ok' => 'boolean',
    ];

    public function fitup()
    {
        return $this->belongsTo(ProductionFitup::class, 'fitup_id');
    }

    public function assembly()
    {
        return $this->belongsTo(ProductionAssembly::class, 'assembly_id');
    }

    public function wipItem()
    {
        return $this->belongsTo(ProductionWipItem::class, 'wip_item_id');
    }

    public function partDefinition()
    {
        return $this->belongsTo(ProductionPartDefinition::class, 'part_definition_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }
}
