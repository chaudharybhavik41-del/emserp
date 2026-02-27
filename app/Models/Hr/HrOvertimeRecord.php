<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrOvertimeRecord extends Model
{
    protected $table = 'hr_overtime_records';

    protected $fillable = [
        'ot_number',
        'hr_employee_id',
        'ot_date',
        'hr_attendance_id',
        'ot_start_time',
        'ot_end_time',
        'ot_hours',
        'approved_hours',
        'ot_type',
        'rate_multiplier',
        'hourly_rate',
        'ot_amount',
        'project_id',
        'work_description',
        'status',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'approval_remarks',
        'rejection_reason',
        'hr_payroll_id',
        'is_paid',
    ];

    protected $casts = [
        'ot_date' => 'date',
        'ot_start_time' => 'datetime',
        'ot_end_time' => 'datetime',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'ot_hours' => 'decimal:2',
        'approved_hours' => 'decimal:2',
        'rate_multiplier' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'ot_amount' => 'decimal:2',
        'is_paid' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(HrAttendance::class, 'hr_attendance_id');
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(HrPayroll::class, 'hr_payroll_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
