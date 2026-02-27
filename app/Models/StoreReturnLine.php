<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreReturnLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function storeReturn(): BelongsTo
    {
        return $this->belongsTo(StoreReturn::class, 'store_return_id');
    }

    public function issueLine(): BelongsTo
    {
        return $this->belongsTo(StoreIssueLine::class, 'store_issue_line_id');
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
}
