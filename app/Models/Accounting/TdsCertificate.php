<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TdsCertificate extends Model
{
    protected $table = 'tds_certificates';

    protected $fillable = [
        'company_id',
        'direction',
        'voucher_id',
        'party_account_id',
        'tds_section',
        'tds_rate',
        'tds_amount',
        'certificate_no',
        'certificate_date',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tds_rate'         => 'float',
        'tds_amount'       => 'float',
        'certificate_date' => 'date',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function partyAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'party_account_id');
    }
}
