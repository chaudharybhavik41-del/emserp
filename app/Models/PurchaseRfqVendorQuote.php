<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRfqVendorQuote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_rfq_vendor_id',
        'purchase_rfq_item_id',

        'revision_no',
        'is_active',

        'vendor_quote_no',
        'vendor_quote_date',
        'valid_till',

        'rate',
        'discount_percent',
        'tax_percent',

        'payment_terms_days',
        'delivery_days',
        'freight_terms',

        'remarks',

        'revised_at',
        'revised_by',
        'cancelled_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'vendor_quote_date' => 'date',
        'valid_till' => 'date',
        'revised_at' => 'datetime',
        'cancelled_at' => 'datetime',

        'rate' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'tax_percent' => 'decimal:2',

        'payment_terms_days' => 'integer',
        'delivery_days' => 'integer',
        'revision_no' => 'integer',
    ];

    public function rfqVendor(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfqVendor::class, 'purchase_rfq_vendor_id');
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfqItem::class, 'purchase_rfq_item_id');
    }
}
