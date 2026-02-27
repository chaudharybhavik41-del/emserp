<?php

namespace App\Models\Production;

use App\Models\StoreStockItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionRemnant extends Model
{
    use HasFactory;

    protected $table = 'production_remnants';

    protected $fillable = [
        'project_id',
        'production_plan_id',
        'production_dpr_line_id',
        'mother_stock_item_id',
        'remnant_stock_item_id',
        'thickness_mm',
        'width_mm',
        'length_mm',
        'weight_kg',
        'is_usable',
        'status',
        'remarks',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:3',
        'is_usable' => 'boolean',
    ];

    public function motherStock()
    {
        return $this->belongsTo(StoreStockItem::class, 'mother_stock_item_id');
    }

    public function remnantStock()
    {
        return $this->belongsTo(StoreStockItem::class, 'remnant_stock_item_id');
    }
}
