<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRfqVendor extends Model
{
    use HasFactory, SoftDeletes;
protected $fillable = [
        'purchase_rfq_id',
        'vendor_party_id',
        'status',
        'email',
        'contact_name',
        'contact_phone',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfq::class, 'purchase_rfq_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'vendor_party_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(PurchaseRfqVendorQuote::class, 'purchase_rfq_vendor_id');
    }
}