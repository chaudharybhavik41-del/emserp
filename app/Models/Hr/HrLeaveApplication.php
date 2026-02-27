<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Enums\Hr\LeaveStatus;
use Carbon\Carbon;

class HrLeaveApplication extends Model
{
    protected $table = 'hr_leave_applications';

    protected $fillable = [
        'application_number',
        'hr_employee_id',
        'hr_leave_type_id',
        'from_date',
        'to_date',
        'total_days',
        'is_half_day',
        'half_day_type',
        'half_day_date',
        'reason',
        'document_path',
        'contact_during_leave',
        'address_during_leave',
        'handover_to',
        'handover_notes',
        'status',
        'current_approval_level',
        'approved_by',
        'approved_at',
        'approval_remarks',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'balance_before',
        'balance_after',
        'created_by',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'half_day_date' => 'date',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_days' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'is_half_day' => 'boolean',
        'status' => LeaveStatus::class,
    ];

    // ==================== RELATIONSHIPS ====================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrLeaveType::class, 'hr_leave_type_id');
    }

    public function handoverEmployee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'handover_to');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Alias: approvedBy (used by newer controllers/views)
     */
    public function approvedBy(): BelongsTo
    {
        return $this->approvedByUser();
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Alias: cancelledBy (used by newer controllers/views)
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->cancelledByUser();
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Alias: createdBy (used by newer controllers/views)
     */
    public function createdBy(): BelongsTo
    {
        return $this->createdByUser();
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(HrLeaveApprovalLog::class, 'hr_leave_application_id');
    }

    // ==================== SCOPES ====================

    public function scopePending($query)
    {
        return $query->where('status', LeaveStatus::Pending);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', LeaveStatus::Approved);
    }

    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('hr_employee_id', $employeeId);
    }

    public function scopeForYear($query, $year)
    {
        return $query->whereYear('from_date', $year);
    }

    public function scopeForDateRange($query, $fromDate, $toDate)
    {
        return $query->where(function ($q) use ($fromDate, $toDate) {
            $q->whereBetween('from_date', [$fromDate, $toDate])
              ->orWhereBetween('to_date', [$fromDate, $toDate])
              ->orWhere(function ($q2) use ($fromDate, $toDate) {
                  $q2->where('from_date', '<=', $fromDate)
                     ->where('to_date', '>=', $toDate);
              });
        });
    }

    public function scopeOnDate($query, $date)
    {
        return $query->where('from_date', '<=', $date)
                     ->where('to_date', '>=', $date)
                     ->where('status', LeaveStatus::Approved);
    }

    // ==================== ACCESSORS ====================

    public function getDurationTextAttribute(): string
    {
        if ($this->is_half_day) {
            $session = $this->half_day_type === 'first_half' ? '1st Half' : '2nd Half';
            return "Half Day ({$session})";
        }

        if ($this->from_date->equalTo($this->to_date)) {
            return '1 Day';
        }

        return $this->total_days . ' Days';
    }

    public function getPeriodTextAttribute(): string
    {
        if ($this->from_date->equalTo($this->to_date)) {
            return $this->from_date->format('d M Y');
        }

        return $this->from_date->format('d M') . ' - ' . $this->to_date->format('d M Y');
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, [LeaveStatus::Pending, LeaveStatus::Approved]);
    }

    public function getCanCancelAttribute(): bool
    {
        // Can cancel if pending or approved and leave hasn't started
        if ($this->status === LeaveStatus::Pending) {
            return true;
        }

        if ($this->status === LeaveStatus::Approved && $this->from_date > now()) {
            return true;
        }

        return false;
    }

    // ==================== METHODS ====================

    /**
     * Generate unique application number
     */
    public static function generateApplicationNumber(): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        
        $lastApplication = static::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastApplication && preg_match('/LA(\d{4})(\d{2})(\d{4})/', $lastApplication->application_number, $matches)) {
            $sequence = intval($matches[3]) + 1;
        } else {
            $sequence = 1;
        }

        return 'LA' . $year . $month . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Check if leave overlaps with existing applications
     */
    public function hasOverlap(): bool
    {
        return static::where('hr_employee_id', $this->hr_employee_id)
            ->where('id', '!=', $this->id ?? 0)
            ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->where(function ($query) {
                $query->whereBetween('from_date', [$this->from_date, $this->to_date])
                      ->orWhereBetween('to_date', [$this->from_date, $this->to_date])
                      ->orWhere(function ($q) {
                          $q->where('from_date', '<=', $this->from_date)
                            ->where('to_date', '>=', $this->to_date);
                      });
            })
            ->exists();
    }

    /**
     * Approve the leave application
     */
    public function approve(int $userId, ?string $remarks = null): bool
    {
        $this->update([
            'status' => LeaveStatus::Approved,
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_remarks' => $remarks,
        ]);

        // Create approval log
        $this->approvalLogs()->create([
            'approval_level' => $this->current_approval_level,
            'approver_id' => $userId,
            'action' => 'approved',
            'remarks' => $remarks,
        ]);

        return true;
    }

    /**
     * Reject the leave application
     */
    public function reject(int $userId, string $reason): bool
    {
        $this->update([
            'status' => LeaveStatus::Rejected,
            'rejection_reason' => $reason,
        ]);

        // Create approval log
        $this->approvalLogs()->create([
            'approval_level' => $this->current_approval_level,
            'approver_id' => $userId,
            'action' => 'rejected',
            'remarks' => $reason,
        ]);

        return true;
    }

    /**
     * Cancel the leave application
     */
    public function cancel(int $userId, string $reason): bool
    {
        $wasApproved = $this->status === LeaveStatus::Approved;

        $this->update([
            'status' => LeaveStatus::Cancelled,
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $wasApproved; // Return true if balance needs to be restored
    }
}
