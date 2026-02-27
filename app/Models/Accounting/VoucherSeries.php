<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherSeries extends Model
{
    protected $table = 'voucher_series';

    protected $fillable = [
        'company_id',
        'key',
        'name',
        'prefix',
        'use_financial_year',
        'separator',
        'pad_length',
        'is_active',
    ];

    protected $casts = [
        'company_id'          => 'integer',
        'use_financial_year'  => 'boolean',
        'pad_length'          => 'integer',
        'is_active'           => 'boolean',
    ];

    public function counters(): HasMany
    {
        return $this->hasMany(VoucherSeriesCounter::class, 'voucher_series_id');
    }
}
