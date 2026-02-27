<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialVendorReturnLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'returned_qty_pcs'   => 'integer',
        'returned_weight_kg' => 'float',
    ];

    public function vendorReturn(): BelongsTo
    {
        return $this->belongsTo(MaterialVendorReturn::class, 'material_vendor_return_id');
    }

    public function materialReceiptLine(): BelongsTo
    {
        return $this->belongsTo(MaterialReceiptLine::class, 'material_receipt_line_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StoreStockItem::class, 'store_stock_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
