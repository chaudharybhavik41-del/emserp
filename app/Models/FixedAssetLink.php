<?php

namespace App\Models;

use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'voucher_id',
        'voucher_line_id',
        'source_type',
        'source_id',
        'source_line_id',
    ];

    protected $casts = [
        'machine_id' => 'integer',
        'voucher_id' => 'integer',
        'voucher_line_id' => 'integer',
        'source_id' => 'integer',
        'source_line_id' => 'integer',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function voucherLine(): BelongsTo
    {
        return $this->belongsTo(VoucherLine::class, 'voucher_line_id');
    }
}
