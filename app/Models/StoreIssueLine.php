<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreIssueLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(StoreIssue::class, 'store_issue_id');
    }

    public function requisitionLine(): BelongsTo
    {
        return $this->belongsTo(StoreRequisitionLine::class, 'store_requisition_line_id');
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
