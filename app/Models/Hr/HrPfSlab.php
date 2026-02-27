<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrPfSlab extends Model
{
    protected $table = 'hr_pf_slabs';

    protected $fillable = [
        'effective_from',
        'effective_to',
        'wage_ceiling',
        'employee_contribution_rate',
        'employer_pf_rate',
        'employer_eps_rate',
        'employer_edli_rate',
        'admin_charges_rate',
        'edli_admin_rate',
        'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'wage_ceiling' => 'decimal:2',
        'employee_contribution_rate' => 'decimal:2',
        'employer_pf_rate' => 'decimal:2',
        'employer_eps_rate' => 'decimal:2',
        'employer_edli_rate' => 'decimal:2',
        'admin_charges_rate' => 'decimal:2',
        'edli_admin_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
