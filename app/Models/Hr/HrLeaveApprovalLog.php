<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveApprovalLog extends Model
{
    protected $table = 'hr_leave_approval_logs';

    protected $fillable = [
        'hr_leave_application_id',
        'approval_level',
        'approver_id',
        'action',
        'remarks',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(HrLeaveApplication::class, 'hr_leave_application_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
