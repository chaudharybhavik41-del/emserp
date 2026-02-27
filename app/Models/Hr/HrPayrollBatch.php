<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrPayrollBatch extends Model
{
    protected $table = 'hr_payroll_batches';

    protected $fillable = [
        'batch_number',
        'hr_payroll_period_id',
        'name',
        'description',
        'batch_type',
        'department_ids',
        'employee_ids',
        'employee_types',
        'total_employees',
        'processed_employees',
        'error_employees',
        'total_gross',
        'total_deductions',
        'total_net_pay',
        'status',
        'processing_log',
        'error_log',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'department_ids' => 'array',
        'employee_ids' => 'array',
        'employee_types' => 'array',
        'approved_at' => 'datetime',
        'total_gross' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net_pay' => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(HrPayrollPeriod::class, 'hr_payroll_period_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(HrPayroll::class, 'hr_payroll_batch_id');
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
