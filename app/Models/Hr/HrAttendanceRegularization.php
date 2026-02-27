<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAttendanceRegularization extends Model
{
    protected $table = 'hr_attendance_regularizations';

    protected $fillable = [
        'request_number',
        'hr_employee_id',
        'hr_attendance_id',
        'attendance_date',
        'original_in_time',
        'original_out_time',
        'original_status',
        'requested_in_time',
        'requested_out_time',
        'requested_status',
        'regularization_type',
        'reason',
        'supporting_document_path',
        'status',
        'approved_by',
        'approved_at',
        'approval_remarks',
        'created_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'original_in_time' => 'datetime',
        'original_out_time' => 'datetime',
        'requested_in_time' => 'datetime',
        'requested_out_time' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(HrAttendance::class, 'hr_attendance_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber(): string
    {
        $prefix = 'REG-' . now()->format('Ym') . '-';

        $last = static::query()
            ->where('request_number', 'like', $prefix . '%')
            ->orderByDesc('request_number')
            ->first();

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last->request_number, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
