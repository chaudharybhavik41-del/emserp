<?php

namespace App\Models\Accounting;

use App\Models\ClientRaBill;
use App\Models\Party;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesCreditNote extends Model
{
    protected $table = 'sales_credit_notes';

    protected $fillable = [
        'company_id',
        'client_id',
        'client_ra_bill_id',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'client_id');
    }

    public function clientRaBill(): BelongsTo
    {
        return $this->belongsTo(ClientRaBill::class, 'client_ra_bill_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesCreditNoteLine::class, 'sales_credit_note_id')->orderBy('line_no');
    }
}
