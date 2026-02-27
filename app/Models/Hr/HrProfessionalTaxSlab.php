<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrProfessionalTaxSlab extends Model
{
    protected $table = 'hr_professional_tax_slabs';

    protected $fillable = [
        'state_code',
        'state_name',
        'effective_from',
        'effective_to',
        'salary_from',
        'salary_to',
        'tax_amount',
        'frequency',
        'gender',
        'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'salary_from' => 'decimal:2',
        'salary_to' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
