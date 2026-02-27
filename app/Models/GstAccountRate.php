<?php

namespace App\Models;

use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GstAccountRate extends Model
{
    use HasFactory;

    /**
     * Columns:
     * - id
     * - account_id (FK to accounts.id)
     * - hsn_sac_code (nullable)
     * - effective_from (date)
     * - effective_to (date, nullable)
     * - cgst_rate, sgst_rate, igst_rate (decimal 5,2)
     * - is_reverse_charge (bool)
     */
    protected $fillable = [
        'account_id',
        'hsn_sac_code',
        'effective_from',
        'effective_to',
        'cgst_rate',
        'sgst_rate',
        'igst_rate',
        'is_reverse_charge',
    ];

    protected $casts = [
        'effective_from'   => 'date',
        'effective_to'     => 'date',
        'cgst_rate'        => 'float',
        'sgst_rate'        => 'float',
        'igst_rate'        => 'float',
        'is_reverse_charge'=> 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
