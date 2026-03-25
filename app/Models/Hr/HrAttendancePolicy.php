<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrAttendancePolicy extends Model
{
    protected $table = 'hr_attendance_policies';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'effective_from',
        'grace_period_minutes',
        'late_deduction_per_instance',
        'max_late_instances_per_month',
        'late_deduction_from_leave',
        'half_day_after_late_minutes',
        'half_day_after_early_minutes',
        'min_hours_for_full_day',
        'min_hours_for_half_day',
        'absent_after_late_minutes',
        'mark_absent_on_no_punch',
        'ot_allowed',
        'ot_rate_multiplier',
        'ot_calculation_basis',
        'ot_min_minutes',
        'ot_max_hours_per_day',
        'ot_max_hours_per_month',
        'ot_needs_approval',
        'allow_week_off_work',
        'week_off_ot_multiplier',
        'holiday_ot_multiplier',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'grace_period_minutes' => 'integer',
        'late_deduction_per_instance' => 'integer',
        'max_late_instances_per_month' => 'integer',
        'late_deduction_from_leave' => 'decimal:2',
        'half_day_after_late_minutes' => 'integer',
        'half_day_after_early_minutes' => 'integer',
        'min_hours_for_full_day' => 'decimal:2',
        'min_hours_for_half_day' => 'decimal:2',
        'absent_after_late_minutes' => 'integer',
        'mark_absent_on_no_punch' => 'boolean',
        'ot_allowed' => 'boolean',
        'ot_rate_multiplier' => 'decimal:2',
        'ot_calculation_basis' => 'string',
        'ot_min_minutes' => 'integer',
        'ot_max_hours_per_day' => 'integer',
        'ot_max_hours_per_month' => 'integer',
        'ot_needs_approval' => 'boolean',
        'allow_week_off_work' => 'boolean',
        'week_off_ot_multiplier' => 'decimal:2',
        'holiday_ot_multiplier' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'hr_attendance_policy_id');
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
