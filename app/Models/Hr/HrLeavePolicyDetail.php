<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeavePolicyDetail extends Model
{
    protected $table = 'hr_leave_policy_details';

    protected $fillable = [
        'hr_leave_policy_id',
        'hr_leave_type_id',
        'days_per_year',
        'max_carry_forward',
        'allow_encashment',
    ];

    protected $casts = [
        'days_per_year' => 'decimal:2',
        'max_carry_forward' => 'decimal:2',
        'allow_encashment' => 'boolean',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(HrLeavePolicy::class, 'hr_leave_policy_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrLeaveType::class, 'hr_leave_type_id');
    }

    // Backward-compatible aliases for existing views/forms.
    public function getAnnualEntitlementAttribute()
    {
        return $this->days_per_year;
    }

    public function setAnnualEntitlementAttribute($value): void
    {
        $this->attributes['days_per_year'] = $value;
    }

    public function getMonthlyAccrualAttribute()
    {
        return $this->days_per_year ? round(((float) $this->days_per_year) / 12, 2) : null;
    }

    public function setMonthlyAccrualAttribute($value): void
    {
        // Derived value; retained only for compatibility.
    }

    public function getMaxAccumulationAttribute()
    {
        return $this->max_carry_forward;
    }

    public function setMaxAccumulationAttribute($value): void
    {
        $this->attributes['max_carry_forward'] = $value;
    }
}
