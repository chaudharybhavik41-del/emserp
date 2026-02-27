<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VoucherLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'line_no',
        'account_id',
        'cost_center_id',
        'description',
        'debit',
        'credit',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'debit'  => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Bill-wise allocations tagged to this voucher line.
     *
     * Used in Phase 2 for supplier payments against Purchase Bills.
     */
    public function billAllocations(): HasMany
    {
        return $this->hasMany(AccountBillAllocation::class, 'voucher_line_id');
    }
}
