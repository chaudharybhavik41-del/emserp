<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class HrPayrollPeriod extends Model
{
    protected $table = 'hr_payroll_periods';

    protected $fillable = [
        'company_id',
        'period_code',
        'name',
        'year',
        'month',
        'period_start',
        'period_end',
        'attendance_start',
        'attendance_end',
        'payment_date',
        'total_days',
        'working_days',
        'holidays',
        'week_offs',
        'status',
        'attendance_locked_at',
        'attendance_locked_by',
        'processed_at',
        'processed_by',
        'approved_at',
        'approved_by',
        'paid_at',
        'paid_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'attendance_start' => 'date',
        'attendance_end' => 'date',
        'payment_date' => 'date',
        'attendance_locked_at' => 'datetime',
        'processed_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'total_days' => 'integer',
        'working_days' => 'integer',
        'holidays' => 'integer',
        'week_offs' => 'integer',
    ];

    // ==================== RELATIONSHIPS ====================

    public function payrolls(): HasMany
    {
        return $this->hasMany(HrPayroll::class, 'hr_payroll_period_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(HrPayrollBatch::class, 'hr_payroll_period_id');
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    // ==================== SCOPES ====================

    public function scopeCurrent($query)
    {
        return $query->where('status', '!=', 'paid')
                     ->orderBy('period_start', 'desc');
    }

    public function scopeForYear($query, $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForMonth($query, $month, $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }

    // ==================== ACCESSORS ====================

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'secondary',
            'attendance_locked' => 'info',
            'processing' => 'warning',
            'processed' => 'primary',
            'approved' => 'success',
            'paid' => 'dark',
            'closed' => 'secondary',
            default => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft' => 'Draft',
            'attendance_locked' => 'Attendance Locked',
            'processing' => 'Processing',
            'processed' => 'Processed',
            'approved' => 'Approved',
            'paid' => 'Paid',
            'closed' => 'Closed',
            default => ucfirst($this->status),
        };
    }

    public function getDateRangeAttribute(): string
    {
        return $this->period_start->format('d M') . ' - ' . $this->period_end->format('d M Y');
    }

    public function getCanProcessAttribute(): bool
    {
        return in_array($this->status, ['draft', 'attendance_locked', 'processed']);
    }

    public function getCanApproveAttribute(): bool
    {
        return $this->status === 'processed';
    }

    public function getCanPayAttribute(): bool
    {
        return $this->status === 'approved';
    }

    // ==================== METHODS ====================

    public static function generatePeriodCode(int $month, int $year): string
    {
        $monthName = strtoupper(date('M', mktime(0, 0, 0, $month, 1)));
        return "PP-{$year}-{$monthName}";
    }

    public static function generatePeriodName(int $month, int $year): string
    {
        $monthName = date('F', mktime(0, 0, 0, $month, 1));
        return "{$monthName} {$year}";
    }

    public function lockAttendance(): bool
    {
        $this->update([
            'status' => 'attendance_locked',
            'attendance_locked_at' => now(),
            'attendance_locked_by' => auth()->id(),
        ]);

        return true;
    }

    public function markAsProcessed(): bool
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
            'processed_by' => auth()->id(),
        ]);

        return true;
    }

    public function markAsApproved(): bool
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return true;
    }

    public function markAsPaid(): bool
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'paid_by' => auth()->id(),
        ]);

        return true;
    }
}
