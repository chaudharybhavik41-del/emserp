<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;

class HrSalaryStructure extends Model
{
    protected $table = 'hr_salary_structures';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'base_type',
        'is_default',
        'applicable_employee_types',
        'applicable_grades',
        'payroll_frequency',
        'payment_day',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'applicable_employee_types' => 'array',
        'applicable_grades' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'payment_day' => 'integer',
    ];

    // ==================== RELATIONSHIPS ====================

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'hr_salary_structure_id');
    }

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(HrSalaryComponent::class, 'hr_salary_structure_components', 'hr_salary_structure_id', 'hr_salary_component_id')
            ->withPivot(['calculation_type', 'value', 'percentage', 'formula', 'min_value', 'max_value', 'is_mandatory', 'sort_order', 'is_active'])
            ->withTimestamps();
    }

    public function salaryComponents(): BelongsToMany
    {
        return $this->components();
    }

    public function structureComponents(): HasMany
    {
        return $this->hasMany(HrSalaryStructureComponent::class, 'hr_salary_structure_id');
    }

    public function employeeSalaries(): HasMany
    {
        return $this->hasMany(HrEmployeeSalary::class, 'hr_salary_structure_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    // ==================== ACCESSORS ====================

    public function getBaseTypeLabelAttribute(): string
    {
        return match($this->base_type) {
            'ctc' => 'Cost to Company (CTC)',
            'gross' => 'Gross Salary',
            'basic' => 'Basic Salary',
            default => ucfirst($this->base_type),
        };
    }

    public function getFrequencyLabelAttribute(): string
    {
        return match($this->payroll_frequency) {
            'monthly' => 'Monthly',
            'weekly' => 'Weekly',
            'biweekly' => 'Bi-Weekly',
            'daily' => 'Daily',
            default => ucfirst($this->payroll_frequency),
        };
    }

    // ==================== METHODS ====================

    /**
     * Check if structure is applicable for employee
     */
    public function isApplicableFor(HrEmployee $employee): bool
    {
        // Check employee type
        if ($this->applicable_employee_types && count($this->applicable_employee_types) > 0) {
            if (!in_array($employee->employment_type, $this->applicable_employee_types)) {
                return false;
            }
        }

        // Check grade
        if ($this->applicable_grades && count($this->applicable_grades) > 0) {
            if (!in_array($employee->hr_grade_id, $this->applicable_grades)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get earning components
     */
    public function getEarningComponents()
    {
        return $this->structureComponents()
            ->whereHas('salaryComponent', function ($q) {
                $q->where('component_type', 'earning');
            })
            ->with('salaryComponent')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get deduction components
     */
    public function getDeductionComponents()
    {
        return $this->structureComponents()
            ->whereHas('salaryComponent', function ($q) {
                $q->where('component_type', 'deduction');
            })
            ->with('salaryComponent')
            ->orderBy('sort_order')
            ->get();
    }
}
