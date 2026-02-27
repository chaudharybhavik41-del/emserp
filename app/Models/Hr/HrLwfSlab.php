<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrLwfSlab extends Model
{
    protected $table = 'hr_lwf_slabs';

    protected $fillable = [
        'state_code',
        'state_name',
        'effective_from',
        'effective_to',
        'employee_contribution',
        'employer_contribution',
        'frequency',
        'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'employee_contribution' => 'decimal:2',
        'employer_contribution' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
