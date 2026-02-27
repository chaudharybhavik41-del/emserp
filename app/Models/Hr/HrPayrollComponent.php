<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollComponent extends Model
{
    protected $table = 'hr_payroll_components';

    protected $fillable = [
        'hr_payroll_id',
        'hr_salary_component_id',
        'component_code',
        'component_name',
        'component_type',
        'base_amount',
        'calculated_amount',
        'adjusted_amount',
        'final_amount',
        'calculation_notes',
        'sort_order',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'calculated_amount' => 'decimal:2',
        'adjusted_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(HrPayroll::class, 'hr_payroll_id');
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(HrSalaryComponent::class, 'hr_salary_component_id');
    }
}
