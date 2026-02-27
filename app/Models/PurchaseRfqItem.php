<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRfqItem extends Model
{
    use HasFactory, SoftDeletes;
protected $fillable = [
    'purchase_rfq_id',
    'item_id',
    'line_no',

    // Geometry & qty
    'length_mm',
    'width_mm',
    'thickness_mm',
    'weight_per_meter_kg',
    'qty_pcs',

    // Qty in UOM (kg / m / etc)
    'quantity',
    'uom_id',

    'grade',

    'purchase_indent_item_id',
    'selected_vendor_id',
    'selected_quote_id',
    'description',
	];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'quantity' => 'float',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfq::class, 'purchase_rfq_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function vendorQuotes(): HasMany
    {
        return $this->hasMany(PurchaseRfqVendorQuote::class, 'purchase_rfq_item_id');
    }

    public function selectedVendor(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfqVendor::class, 'selected_vendor_id');
    }

    public function selectedQuote(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfqVendorQuote::class, 'selected_quote_id');
    }
}