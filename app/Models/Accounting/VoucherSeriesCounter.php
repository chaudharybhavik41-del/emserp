<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherSeriesCounter extends Model
{
    protected $table = 'voucher_series_counters';

    protected $fillable = [
        'voucher_series_id',
        'fy_code',
        'next_number',
    ];

    protected $casts = [
        'voucher_series_id' => 'integer',
        'next_number'       => 'integer',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(VoucherSeries::class, 'voucher_series_id');
    }
}
