<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatePassLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_returnable'       => 'boolean',
        'expected_return_date'=> 'date',
        'returned_on'         => 'date',
    ];

    public function gatePass(): BelongsTo
    {
        return $this->belongsTo(GatePass::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function storeIssueLine(): BelongsTo
    {
        return $this->belongsTo(StoreIssueLine::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StoreStockItem::class, 'store_stock_item_id');
    }
}
