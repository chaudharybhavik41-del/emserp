<?php

namespace App\Models\Production;

use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionDispatchLine extends Model
{
    use HasFactory;

    protected $table = 'production_dispatch_lines';

    protected $fillable = [
        'production_dispatch_id',
        'production_plan_item_id',
        'qty',
        'uom_id',
        'weight_kg',
        'remarks',
        'source_meta',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'weight_kg' => 'decimal:3',
        'source_meta' => 'array',
    ];

    public function dispatch()
    {
        return $this->belongsTo(ProductionDispatch::class, 'production_dispatch_id');
    }

    public function planItem()
    {
        return $this->belongsTo(ProductionPlanItem::class, 'production_plan_item_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }
}
