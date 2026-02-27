<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class HrEmployeeSalary extends Model
{
    protected $table = 'hr_employee_salaries';

    protected $fillable = [
        'hr_employee_id',
        'hr_salary_structure_id',
        'effective_from',
        'effective_to',
        'is_current',
        'annual_ctc',
        'monthly_ctc',
        'monthly_gross',
        'monthly_basic',
        'monthly_net',
        'revision_type',
        'increment_percent',
        'previous_ctc',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'annual_ctc' => 'decimal:2',
        'monthly_ctc' => 'decimal:2',
        'monthly_gross' => 'decimal:2',
        'monthly_basic' => 'decimal:2',
        'monthly_net' => 'decimal:2',
        'increment_percent' => 'decimal:2',
        'previous_ctc' => 'decimal:2',
        'is_current' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(HrSalaryStructure::class, 'hr_salary_structure_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function components(): HasMany
    {
        return $this->hasMany(HrEmployeeSalaryComponent::class, 'hr_employee_salary_id');
    }

    // ==================== SCOPES ====================

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeActiveOn($query, $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $date);
            });
    }

    // ==================== ACCESSORS ====================

    public function getFormattedGrossAttribute(): string
    {
        return '₹' . number_format((float) $this->monthly_gross, 0);
    }

    public function getFormattedNetAttribute(): string
    {
        return '₹' . number_format((float) $this->monthly_net, 0);
    }

    public function getFormattedCtcAttribute(): string
    {
        return '₹' . number_format((float) $this->annual_ctc, 0);
    }

    public function getTotalEarningsAttribute(): float
    {
        return (float) $this->monthly_gross;
    }

    public function getTotalEmployerContributionAttribute(): float
    {
        return max(0, ((float) $this->monthly_ctc) - ((float) $this->monthly_gross));
    }

    // ==================== METHODS ====================

    /**
     * Calculate daily salary
     */
    public function getDailySalary(int $workingDays = 26): float
    {
        return ((float) $this->monthly_gross) / max(1, $workingDays);
    }

    /**
     * Calculate hourly salary (assuming 8 hours/day)
     */
    public function getHourlySalary(int $workingDays = 26, int $hoursPerDay = 8): float
    {
        return $this->getDailySalary($workingDays) / $hoursPerDay;
    }

    /**
     * Mark as current and deactivate previous
     */
    public function markAsCurrent(): bool
    {
        // Deactivate all other salary records for this employee
        static::where('hr_employee_id', $this->hr_employee_id)
            ->where('id', '!=', $this->id)
            ->update(['is_current' => false]);

        $this->is_current = true;
        return $this->save();
    }

    // Backward-compatible virtual attributes expected by older controllers/views.
    public function getBasicAttribute()
    {
        return $this->monthly_basic;
    }

    public function getGrossSalaryAttribute()
    {
        return $this->monthly_gross;
    }

    public function getNetSalaryAttribute()
    {
        return $this->monthly_net;
    }

    public function getCtcAttribute()
    {
        return $this->annual_ctc;
    }
}
