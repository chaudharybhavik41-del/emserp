<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployeeSalaryComponent extends Model
{
    protected $table = 'hr_employee_salary_components';

    protected $fillable = [
        'hr_employee_salary_id',
        'hr_salary_component_id',
        'monthly_amount',
        'annual_amount',
        'calculation_type',
        'percentage',
    ];

    protected $casts = [
        'monthly_amount' => 'decimal:2',
        'annual_amount' => 'decimal:2',
        'percentage' => 'decimal:4',
    ];

    public function employeeSalary(): BelongsTo
    {
        return $this->belongsTo(HrEmployeeSalary::class, 'hr_employee_salary_id');
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(HrSalaryComponent::class, 'hr_salary_component_id');
    }
}
