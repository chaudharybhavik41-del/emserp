<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrLeaveType extends Model
{
    protected $table = 'hr_leave_types';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'short_name',
        'description',
        'default_days_per_year',
        'is_paid',
        'is_encashable',
        'is_carry_forward',
        'max_carry_forward_days',
        'max_accumulation_days',
        'credit_type',
        'monthly_credit',
        'prorate_on_joining',
        'min_days_per_application',
        'max_days_per_application',
        'advance_notice_days',
        'max_instances_per_month',
        'allow_half_day',
        'allow_negative_balance',
        'negative_balance_limit',
        'document_required',
        'document_required_after_days',
        'include_weekends',
        'include_holidays',
        'applicable_employee_types',
        'applicable_genders',
        'applicable_after_months',
        'color_code',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'default_days_per_year' => 'decimal:1',
        'max_carry_forward_days' => 'decimal:1',
        'max_accumulation_days' => 'decimal:1',
        'monthly_credit' => 'decimal:2',
        'min_days_per_application' => 'integer',
        'max_days_per_application' => 'decimal:1',
        'advance_notice_days' => 'integer',
        'max_instances_per_month' => 'integer',
        'negative_balance_limit' => 'decimal:1',
        'document_required_after_days' => 'integer',
        'applicable_after_months' => 'integer',
        'is_paid' => 'boolean',
        'is_encashable' => 'boolean',
        'is_carry_forward' => 'boolean',
        'prorate_on_joining' => 'boolean',
        'allow_half_day' => 'boolean',
        'allow_negative_balance' => 'boolean',
        'document_required' => 'boolean',
        'include_weekends' => 'boolean',
        'include_holidays' => 'boolean',
        'is_active' => 'boolean',
        'applicable_employee_types' => 'array',
        'applicable_genders' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function applications(): HasMany
    {
        return $this->hasMany(HrLeaveApplication::class, 'hr_leave_type_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(HrLeaveBalance::class, 'hr_leave_type_id');
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopeEncashable($query)
    {
        return $query->where('is_encashable', true);
    }

    // ==================== ACCESSORS ====================

    public function getDisplayNameAttribute(): string
    {
        return $this->short_name ?: $this->code;
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    // ==================== METHODS ====================

    /**
     * Check if leave type is applicable for an employee
     */
    public function isApplicableFor(HrEmployee $employee): bool
    {
        // Check gender applicability
        if ($this->applicable_genders) {
            if (!in_array($employee->gender, $this->applicable_genders)) {
                return false;
            }
        }

        // Check employee type applicability
        if ($this->applicable_employee_types) {
            if (!in_array($employee->employment_type, $this->applicable_employee_types)) {
                return false;
            }
        }

        // Check service period requirement
        if ($this->applicable_after_months > 0) {
            $serviceMonths = $employee->date_of_joining->diffInMonths(now());
            if ($serviceMonths < $this->applicable_after_months) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate prorated days for new joiner
     */
    public function calculateProratedDays(\Carbon\Carbon $joiningDate, int $year): float
    {
        if (!$this->prorate_on_joining) {
            return $this->default_days_per_year;
        }

        $yearStart = \Carbon\Carbon::create($year, 1, 1);
        $yearEnd = \Carbon\Carbon::create($year, 12, 31);

        // If joining date is before year start, give full days
        if ($joiningDate < $yearStart) {
            return $this->default_days_per_year;
        }

        // If joining date is after year end, give 0 days
        if ($joiningDate > $yearEnd) {
            return 0;
        }

        // Calculate prorated days
        $remainingDays = $joiningDate->diffInDays($yearEnd) + 1;
        $totalDays = $yearStart->diffInDays($yearEnd) + 1;

        return round(($this->default_days_per_year * $remainingDays) / $totalDays, 1);
    }
}
