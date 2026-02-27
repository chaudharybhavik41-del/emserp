<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class HrLeavePolicy extends Model
{
    protected $table = 'hr_leave_policies';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'leave_year_type',
        'year_start_date',
        'allow_leave_in_probation',
        'probation_allowed_leave_types',
        'allow_backdated_application',
        'max_backdate_days',
        'allow_future_application',
        'max_future_days',
        'sandwich_rule_enabled',
        'sandwich_min_gap_days',
        'approval_levels',
        'skip_level_on_absence',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'year_start_date' => 'date',
        'probation_allowed_leave_types' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'allow_leave_in_probation' => 'boolean',
        'allow_backdated_application' => 'boolean',
        'allow_future_application' => 'boolean',
        'sandwich_rule_enabled' => 'boolean',
        'skip_level_on_absence' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(HrLeavePolicyDetail::class, 'hr_leave_policy_id');
    }

    public function entitlements(): HasMany
    {
        return $this->details();
    }

    public function leaveTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            HrLeaveType::class,
            'hr_leave_policy_details',
            'hr_leave_policy_id',
            'hr_leave_type_id'
        )->withPivot([
            'days_allowed',
            'carry_forward_allowed',
            'max_carry_forward_days',
            'encashment_allowed',
            'max_encashment_days',
            'is_active',
        ])->withTimestamps();
    }

    public function leaveType(): HasOneThrough
    {
        return $this->hasOneThrough(
            HrLeaveType::class,
            HrLeavePolicyDetail::class,
            'hr_leave_policy_id',
            'id',
            'id',
            'hr_leave_type_id'
        );
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'hr_leave_policy_id');
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

    public function scopeEffective($query, $date = null)
    {
        return $query;
    }

    // ==================== ACCESSORS ====================

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        return 'Active';
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Active' => 'success',
            'Inactive' => 'secondary',
            'Upcoming' => 'info',
            'Expired' => 'warning',
            default => 'secondary',
        };
    }

    // ==================== METHODS ====================

    /**
     * Check if policy is applicable for an employee
     */
    public function isApplicableFor(HrEmployee $employee): bool
    {
        return true;
    }

    /**
     * Get leave entitlement for a leave type
     */
    public function getEntitlementFor(int $leaveTypeId): ?object
    {
        return $this->details()->where('hr_leave_type_id', $leaveTypeId)->first();
    }

    /**
     * Get default policy
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)
                     ->where('is_active', true)
                     ->first();
    }

    // Backward-compatible aliases used by older controller/view code.
    public function getEffectiveFromAttribute()
    {
        return $this->year_start_date;
    }

    public function setEffectiveFromAttribute($value): void
    {
        $this->attributes['year_start_date'] = $value;
    }

    public function getApplicableEmployeeTypesAttribute()
    {
        return null;
    }
}
