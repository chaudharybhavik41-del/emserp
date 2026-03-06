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
        'fixed_asset_id',
        'voucher_id',
        'voucher_line_id',
        'link_type',
    ];

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function voucherLine(): BelongsTo
    {
        return $this->belongsTo(VoucherLine::class);
    }
}
