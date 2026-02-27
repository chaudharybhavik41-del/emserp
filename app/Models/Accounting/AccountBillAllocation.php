<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * AccountBillAllocation
 *
 * Stores bill-wise allocations against a VoucherLine.
 *
 * Example:
 *  - Payment voucher paying a supplier against Purchase Bills
 *  - Receipt voucher receiving from a client against RA/Invoice bills
 *  - On-Account (unallocated) receipts stored as synthetic bill_type
 */
class AccountBillAllocation extends Model
{
    use HasFactory;

    protected $table = 'account_bill_allocations';

    protected $fillable = [
        'company_id',
        'voucher_id',
        'voucher_line_id',
        'account_id',
        'bill_type',
        'bill_id',
        'mode',
        'amount',
        'allocation_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocation_date' => 'date',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function voucherLine(): BelongsTo
    {
        return $this->belongsTo(VoucherLine::class, 'voucher_line_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Polymorphic bill reference.
     *
     * bill_type contains the model FQCN (e.g. App\Models\PurchaseBill::class)
     * bill_id contains the primary key.
     */
    public function bill(): MorphTo
    {
        return $this->morphTo(null, 'bill_type', 'bill_id');
    }
}
