<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'purchase_rfq_item_id',
        'purchase_rfq_vendor_id',
        'purchase_indent_item_id',
        'item_id',
        'line_no',
        'length_mm',
        'width_mm',
        'thickness_mm',
        'weight_per_meter_kg',
        'qty_pcs',
        'quantity',
        'uom_id',
        'grade',
        'description',
        'rate',
        'discount_percent',
        'tax_percent',
        'gst_type',
        'cgst_percent',
        'sgst_percent',
        'igst_percent',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'amount',
        'net_amount',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfqItem::class, 'purchase_rfq_item_id');
    }

    public function rfqVendor(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfqVendor::class, 'purchase_rfq_vendor_id');
    }

    public function indentItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseIndentItem::class, 'purchase_indent_item_id');
    }
}