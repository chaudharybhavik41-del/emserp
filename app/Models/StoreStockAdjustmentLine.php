<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreStockAdjustmentLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StoreStockAdjustment::class, 'store_stock_adjustment_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StoreStockItem::class, 'store_stock_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
