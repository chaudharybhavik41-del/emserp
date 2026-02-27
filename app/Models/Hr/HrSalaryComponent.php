<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HrSalaryComponent extends Model
{
    protected $table = 'hr_salary_components';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'short_name',
        'description',
        'component_type',      // FIXED: was 'type'
        'category',
        'calculation_type',
        'default_value',       // FIXED: was 'calculation_value'
        'percentage',          // ADDED
        'formula',
        'is_statutory',
        'affects_pf',
        'affects_esi',
        'affects_gratuity',
        'is_taxable',
        'is_part_of_ctc',
        'is_part_of_gross',
        'show_in_payslip',
        'show_if_zero',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'default_value' => 'decimal:2',
        'percentage' => 'decimal:4',
        'is_statutory' => 'boolean',
        'affects_pf' => 'boolean',
        'affects_esi' => 'boolean',
        'affects_gratuity' => 'boolean',
        'is_taxable' => 'boolean',
        'is_part_of_ctc' => 'boolean',
        'is_part_of_gross' => 'boolean',
        'show_in_payslip' => 'boolean',
        'show_if_zero' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ==================== RELATIONSHIPS ====================

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function salaryStructures(): BelongsToMany
    {
        return $this->belongsToMany(
            HrSalaryStructure::class, 
            'hr_salary_structure_components',
            'hr_salary_component_id',
            'hr_salary_structure_id'
        )
        ->withPivot(['calculation_type', 'calculation_value', 'percentage', 'based_on', 'is_active'])
        ->withTimestamps();
    }

    // Alias for backward compatibility
    public function structures(): BelongsToMany
    {
        return $this->salaryStructures();
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

    public function scopeEarnings($query)
    {
        return $query->where('component_type', 'earning');  // FIXED
    }

    public function scopeDeductions($query)
    {
        return $query->where('component_type', 'deduction');  // FIXED
    }

    public function scopeEmployerContributions($query)
    {
        return $query->where('component_type', 'employer_contribution');
    }

    public function scopeStatutory($query)
    {
        return $query->where('is_statutory', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('component_type', $type);
    }

    // ==================== ACCESSORS ====================

    public function getTypeLabelAttribute(): string
    {
        return match($this->component_type) {  // FIXED
            'earning' => 'Earning',
            'deduction' => 'Deduction',
            'employer_contribution' => 'Employer Contribution',
            default => ucfirst($this->component_type ?? '-'),
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match($this->component_type) {  // FIXED
            'earning' => 'success',
            'deduction' => 'danger',
            'employer_contribution' => 'info',
            default => 'secondary',
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match($this->category) {
            'basic' => 'Basic',
            'hra' => 'HRA',
            'da' => 'DA',
            'conveyance' => 'Conveyance',
            'medical' => 'Medical',
            'special_allowance' => 'Special Allowance',
            'overtime' => 'Overtime',
            'incentive' => 'Incentive',
            'bonus' => 'Bonus',
            'pf' => 'Provident Fund',
            'esi' => 'ESI',
            'professional_tax' => 'Professional Tax',
            'tds' => 'TDS',
            'loan' => 'Loan',
            'advance' => 'Advance',
            'gratuity' => 'Gratuity',
            'other' => 'Other',
            default => ucfirst(str_replace('_', ' ', $this->category ?? '-')),
        };
    }

    public function getCalculationTypeLabelAttribute(): string
    {
        return match($this->calculation_type) {
            'fixed' => 'Fixed Amount',
            'percent_of_basic' => '% of Basic',
            'percent_of_gross' => '% of Gross',
            'percent_of_ctc' => '% of CTC',
            'attendance_based' => 'Attendance Based',
            'slab_based' => 'Slab Based',
            'formula' => 'Formula',
            default => ucfirst(str_replace('_', ' ', $this->calculation_type ?? '-')),
        };
    }

    // ==================== METHODS ====================

    /**
     * Calculate component value based on salary parameters
     */
    public function calculateValue(float $basic, float $gross = 0, float $ctc = 0): float
    {
        return match($this->calculation_type) {
            'fixed' => (float) $this->default_value,
            'percent_of_basic' => $basic * ((float) $this->percentage / 100),
            'percent_of_gross' => $gross * ((float) $this->percentage / 100),
            'percent_of_ctc' => $ctc * ((float) $this->percentage / 100),
            'formula' => $this->evaluateFormula($basic, $gross, $ctc),
            default => 0,
        };
    }

    /**
     * Evaluate formula
     */
    protected function evaluateFormula(float $basic, float $gross, float $ctc): float
    {
        if (empty($this->formula)) {
            return 0;
        }

        // Replace variables in formula
        $formula = str_replace(
            ['BASIC', 'GROSS', 'CTC'],
            [$basic, $gross, $ctc],
            strtoupper($this->formula)
        );

        // Safe evaluation (only for simple math expressions)
        try {
            // Remove any non-math characters for safety
            if (preg_match('/^[\d\s\+\-\*\/\(\)\.]+$/', $formula)) {
                return (float) eval("return {$formula};");
            }
        } catch (\Throwable $e) {
            // Return 0 if formula evaluation fails
        }

        return 0;
    }

    /**
     * Get component type options for dropdowns
     */
    public static function getComponentTypeOptions(): array
    {
        return [
            'earning' => 'Earning',
            'deduction' => 'Deduction',
            'employer_contribution' => 'Employer Contribution',
        ];
    }

    /**
     * Get calculation type options for dropdowns
     */
    public static function getCalculationTypeOptions(): array
    {
        return [
            'fixed' => 'Fixed Amount',
            'percent_of_basic' => 'Percentage of Basic',
            'percent_of_gross' => 'Percentage of Gross',
            'percent_of_ctc' => 'Percentage of CTC',
            'attendance_based' => 'Attendance Based',
            'slab_based' => 'Slab Based',
            'formula' => 'Formula',
        ];
    }

    /**
     * Get category options for dropdowns
     */
    public static function getCategoryOptions(): array
    {
        return [
            'basic' => 'Basic',
            'hra' => 'HRA',
            'da' => 'DA',
            'conveyance' => 'Conveyance',
            'medical' => 'Medical',
            'special_allowance' => 'Special Allowance',
            'overtime' => 'Overtime',
            'incentive' => 'Incentive',
            'bonus' => 'Bonus',
            'pf' => 'Provident Fund',
            'esi' => 'ESI',
            'professional_tax' => 'Professional Tax',
            'tds' => 'TDS',
            'loan' => 'Loan',
            'advance' => 'Advance',
            'gratuity' => 'Gratuity',
            'other' => 'Other',
        ];
    }
}
