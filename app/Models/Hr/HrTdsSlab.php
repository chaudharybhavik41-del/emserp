<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrTdsSlab extends Model
{
    protected $table = 'hr_tds_slabs';

    protected $fillable = [
        'financial_year',
        'regime',
        'category',
        'income_from',
        'income_to',
        'tax_percent',
        'surcharge_percent',
        'cess_percent',
        'is_active',
    ];

    protected $casts = [
        'income_from' => 'decimal:2',
        'income_to' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'surcharge_percent' => 'decimal:2',
        'cess_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
