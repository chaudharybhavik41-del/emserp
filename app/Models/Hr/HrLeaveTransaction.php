<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveTransaction extends Model
{
    protected $table = 'hr_leave_transactions';

    protected $fillable = [
        'hr_employee_id',
        'hr_leave_type_id',
        'hr_leave_balance_id',
        'transaction_date',
        'transaction_type',
        'days',
        'balance_after',
        'reference_type',
        'reference_id',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'days' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrLeaveType::class, 'hr_leave_type_id');
    }

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(HrLeaveBalance::class, 'hr_leave_balance_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
