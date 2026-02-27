<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveBalance extends Model
{
    protected $table = 'hr_leave_balances';

    protected $fillable = [
        'hr_employee_id',
        'hr_leave_type_id',
        'year',
        'opening_balance',
        'credited',
        'used',
        'pending',
        'adjusted',
        'lapsed',
        'encashed',
        'carry_forward',
        'closing_balance',
        'available_balance',
        'is_processed',
    ];

    protected $casts = [
        'year' => 'integer',
        'opening_balance' => 'decimal:2',
        'credited' => 'decimal:2',
        'used' => 'decimal:2',
        'pending' => 'decimal:2',
        'adjusted' => 'decimal:2',
        'lapsed' => 'decimal:2',
        'encashed' => 'decimal:2',
        'carry_forward' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'is_processed' => 'boolean',
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

    // ==================== SCOPES ====================

    public function scopeForYear($query, $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('hr_employee_id', $employeeId);
    }

    public function scopeForLeaveType($query, $leaveTypeId)
    {
        return $query->where('hr_leave_type_id', $leaveTypeId);
    }

    // ==================== ACCESSORS ====================

    public function getAvailableBalanceAttribute(): float
    {
        return (float) ($this->attributes['available_balance'] ?? ($this->opening_balance + $this->credited - $this->used - $this->adjusted - $this->lapsed - $this->encashed));
    }

    public function getTotalCreditedAttribute(): float
    {
        return $this->opening_balance + $this->credited;
    }

    public function getTotalUsedAttribute(): float
    {
        return $this->used + $this->adjusted + $this->lapsed + $this->encashed;
    }

    // ==================== METHODS ====================

    /**
     * Deduct leave from balance
     */
    public function deduct(float $days): bool
    {
        $this->used += $days;
        $this->closing_balance = $this->available_balance;
        $this->available_balance = max(0, $this->closing_balance);
        return $this->save();
    }

    /**
     * Credit leave to balance
     */
    public function credit(float $days): bool
    {
        $this->credited += $days;
        $this->closing_balance = $this->available_balance;
        $this->available_balance = max(0, $this->closing_balance);
        return $this->save();
    }

    /**
     * Adjust balance
     */
    public function adjust(float $days, string $type = 'deduction'): bool
    {
        if ($type === 'deduction') {
            $this->adjusted += $days;
        } else {
            $this->adjusted -= $days;
        }
        $this->closing_balance = $this->available_balance;
        $this->available_balance = max(0, $this->closing_balance);
        return $this->save();
    }

    /**
     * Get or create balance for employee and leave type
     */
    public static function getOrCreate(int $employeeId, int $leaveTypeId, int $year): self
    {
        return static::firstOrCreate(
            [
                'hr_employee_id' => $employeeId,
                'hr_leave_type_id' => $leaveTypeId,
                'year' => $year,
            ],
            [
                'opening_balance' => 0,
                'credited' => 0,
                'used' => 0,
                'pending' => 0,
                'adjusted' => 0,
                'lapsed' => 0,
                'encashed' => 0,
                'carry_forward' => 0,
                'closing_balance' => 0,
                'available_balance' => 0,
                'is_processed' => false,
            ]
        );
    }

    // Backward-compatible aliases
    public function getTakenAttribute()
    {
        return $this->used;
    }

    public function setTakenAttribute($value): void
    {
        $this->attributes['used'] = $value;
    }
}
