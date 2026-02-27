<?php

namespace App\Models;

use App\Models\Accounting\Voucher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialVendorReturn extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'return_date' => 'date',
    ];

    public function materialReceipt(): BelongsTo
    {
        return $this->belongsTo(MaterialReceipt::class, 'material_receipt_id');
    }

    public function toParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'to_party_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialVendorReturnLine::class, 'material_vendor_return_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
