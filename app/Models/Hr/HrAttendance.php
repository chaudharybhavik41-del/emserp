<?php

namespace App\Models\Hr;

use App\Enums\Hr\AttendanceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class HrAttendance extends Model
{
    use HasFactory;

    protected $table = 'hr_attendances';

    protected $fillable = [
        'hr_employee_id',
        'attendance_date',
        'hr_shift_id',
        'first_in',
        'last_out',
        'break_start',
        'break_end',
        'total_hours',
        'working_hours',
        'break_hours',
        'late_minutes',
        'early_leaving_minutes',
        'status',
        'day_type',
        'is_week_off',
        'is_holiday',
        'hr_holiday_id',
        'hr_leave_application_id',
        'ot_hours',
        'ot_hours_approved',
        'ot_status',
        'ot_approved_by',
        'ot_approved_at',
        'is_regularized',
        'regularization_reason',
        'regularized_by',
        'regularized_at',
        'original_first_in',
        'original_last_out',
        'original_status',
        'is_manual_entry',
        'remarks',
        'is_processed',
        'is_locked',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'first_in' => 'datetime',
        'last_out' => 'datetime',
        'break_start' => 'datetime',
        'break_end' => 'datetime',
        'original_first_in' => 'datetime',
        'original_last_out' => 'datetime',
        'ot_approved_at' => 'datetime',
        'regularized_at' => 'datetime',
        'total_hours' => 'decimal:2',
        'working_hours' => 'decimal:2',
        'break_hours' => 'decimal:2',
        'ot_hours' => 'decimal:2',
        'ot_hours_approved' => 'decimal:2',
        'is_week_off' => 'boolean',
        'is_holiday' => 'boolean',
        'is_regularized' => 'boolean',
        'is_manual_entry' => 'boolean',
        'is_processed' => 'boolean',
        'is_locked' => 'boolean',
        'status' => AttendanceStatus::class,
    ];

    // Relationships

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(HrShift::class, 'hr_shift_id');
    }

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(HrHoliday::class, 'hr_holiday_id');
    }

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(HrLeaveApplication::class, 'hr_leave_application_id');
    }

    public function otApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ot_approved_by');
    }

    public function regularizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regularized_by');
    }

    public function punches(): HasMany
    {
        return $this->hasMany(HrAttendancePunch::class, 'hr_employee_id', 'hr_employee_id')
            ->whereDate('punch_time', $this->attendance_date);
    }

    public function overtimeRecord(): HasOne
    {
        return $this->hasOne(HrOvertimeRecord::class, 'hr_attendance_id');
    }

    public function regularizationRequests(): HasMany
    {
        return $this->hasMany(HrAttendanceRegularization::class, 'hr_attendance_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('hr_employee_id', $employeeId);
    }

    public function scopeForDateRange($query, Carbon $from, Carbon $to)
    {
        return $query->whereBetween('attendance_date', [$from, $to]);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('attendance_date', $year)
            ->whereMonth('attendance_date', $month);
    }

    public function scopePresent($query)
    {
        return $query->where('status', AttendanceStatus::PRESENT);
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', AttendanceStatus::ABSENT);
    }

    public function scopeWithOt($query)
    {
        return $query->where('ot_hours', '>', 0);
    }

    public function scopePendingOtApproval($query)
    {
        return $query->where('ot_status', 'pending');
    }

    public function scopeNotLocked($query)
    {
        return $query->where('is_locked', false);
    }

    // Accessors

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status->color();
    }

    public function getStatusCodeAttribute(): string
    {
        return $this->status->shortCode();
    }

    public function getFormattedInTimeAttribute(): ?string
    {
        return $this->first_in?->format('h:i A');
    }

    public function getFormattedOutTimeAttribute(): ?string
    {
        return $this->last_out?->format('h:i A');
    }

    public function getPaidDaysAttribute(): float
    {
        return match ($this->status) {
            AttendanceStatus::PRESENT, AttendanceStatus::LATE, 
            AttendanceStatus::EARLY_LEAVING, AttendanceStatus::ON_DUTY,
            AttendanceStatus::WEEKLY_OFF, AttendanceStatus::HOLIDAY,
            AttendanceStatus::COMP_OFF => 1.0,
            AttendanceStatus::HALF_DAY => 0.5,
            AttendanceStatus::LATE_AND_EARLY => 1.0, // May have deductions
            AttendanceStatus::LEAVE => $this->leaveApplication?->leaveType?->is_paid ? 1.0 : 0.0,
            AttendanceStatus::ABSENT => 0.0,
            default => 0.0,
        };
    }

    // Methods

    public function isEditable(): bool
    {
        return !$this->is_locked;
    }

    public function isPaidDay(): bool
    {
        return $this->paid_days > 0;
    }

    public function hasOvertime(): bool
    {
        return $this->ot_hours > 0;
    }

    public function isOtApproved(): bool
    {
        return $this->ot_status === 'approved';
    }

    public function needsRegularization(): bool
    {
        // Single punch or missing punch
        return ($this->first_in && !$this->last_out) || 
               (!$this->first_in && $this->last_out);
    }

    public function approve_overtime(User $approver, ?float $approvedHours = null): void
    {
        $this->update([
            'ot_hours_approved' => $approvedHours ?? $this->ot_hours,
            'ot_status' => 'approved',
            'ot_approved_by' => $approver->id,
            'ot_approved_at' => now(),
        ]);
    }

    public function reject_overtime(User $approver): void
    {
        $this->update([
            'ot_hours_approved' => 0,
            'ot_status' => 'rejected',
            'ot_approved_by' => $approver->id,
            'ot_approved_at' => now(),
        ]);
    }

    public function regularize(
        ?Carbon $newInTime, 
        ?Carbon $newOutTime, 
        string $reason, 
        User $regularizer
    ): void {
        // Store original values
        $this->original_first_in = $this->original_first_in ?? $this->first_in;
        $this->original_last_out = $this->original_last_out ?? $this->last_out;
        $this->original_status = $this->original_status ?? $this->status->value;
        
        // Update with new values
        $this->first_in = $newInTime;
        $this->last_out = $newOutTime;
        $this->is_regularized = true;
        $this->regularization_reason = $reason;
        $this->regularized_by = $regularizer->id;
        $this->regularized_at = now();
        
        // Recalculate
        $this->recalculate();
        
        $this->save();
    }

    public function recalculate(): void
    {
        if (!$this->shift) {
            return;
        }
        
        $result = $this->shift->determineAttendanceStatus(
            $this->first_in,
            $this->last_out,
            $this->attendance_date
        );
        
        $this->status = AttendanceStatus::from($result['status']);
        $this->late_minutes = $result['late_minutes'];
        $this->early_leaving_minutes = $result['early_minutes'];
        $this->working_hours = $result['working_hours'];
        $this->ot_hours = round($result['ot_minutes'] / 60, 2);
        
        if ($this->first_in && $this->last_out) {
            $this->total_hours = round($this->last_out->diffInMinutes($this->first_in) / 60, 2);
        }
    }

    public static function getOrCreateForDate(int $employeeId, Carbon $date): self
    {
        return self::firstOrCreate(
            [
                'hr_employee_id' => $employeeId,
                'attendance_date' => $date->toDateString(),
            ],
            [
                'status' => AttendanceStatus::ABSENT,
                'day_type' => 'working',
            ]
        );
    }
}
