<?php

namespace App\Models\Accounting;

use App\Models\Party;
use App\Models\PurchaseBill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseDebitNote extends Model
{
    protected $table = 'purchase_debit_notes';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_bill_id',
        'note_number',
        'note_date',
        'reference',
        'remarks',
        'total_basic',
        'total_cgst',
        'total_sgst',
        'total_igst',
        'total_tax',
        'total_amount',
        'voucher_id',
        'status',
        'created_by',
        'updated_by',
        'posted_by',
        'posted_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'note_date' => 'date',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_id');
    }

    public function purchaseBill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseDebitNoteLine::class, 'purchase_debit_note_id')->orderBy('line_no');
    }
}
