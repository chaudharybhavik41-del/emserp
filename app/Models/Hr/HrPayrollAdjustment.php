<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollAdjustment extends Model
{
    protected $table = 'hr_payroll_adjustments';

    protected $fillable = [
        'hr_payroll_id',
        'hr_salary_component_id',
        'description',
        'adjustment_type',
        'amount',
        'reason',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(HrPayroll::class, 'hr_payroll_id');
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(HrSalaryComponent::class, 'hr_salary_component_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
