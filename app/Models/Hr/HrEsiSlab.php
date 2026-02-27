<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrEsiSlab extends Model
{
    protected $table = 'hr_esi_slabs';

    protected $fillable = [
        'effective_from',
        'effective_to',
        'wage_ceiling',
        'employee_rate',
        'employer_rate',
        'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'wage_ceiling' => 'decimal:2',
        'employee_rate' => 'decimal:2',
        'employer_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
