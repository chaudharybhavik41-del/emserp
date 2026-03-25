<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Hr\HrProfessionalTaxSlab;
use App\Models\Hr\HrPfSlab;
use App\Models\Hr\HrPayroll;
use App\Models\Hr\HrLwfSlab;
use App\Models\Hr\HrTdsSlab;
use App\Models\Hr\HrTaxDeclaration;

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

    public function payrolls(): HasMany
    {
        return $this->hasMany(HrPayroll::class, 'hr_employee_salary_id');
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
        return round((float) $this->monthly_gross - (float) $this->total_deductions, 2);
    }

    public function getCtcAttribute()
    {
        return $this->annual_ctc;
    }

    public function getMonthlyCtcAttribute()
    {
        return (float) ($this->attributes['monthly_ctc'] ?? 0);
    }

    public function getAnnualCtcAttribute()
    {
        return (float) ($this->attributes['annual_ctc'] ?? 0);
    }

    public function getHraAttribute()
    {
        return $this->componentAmount('hra');
    }

    public function getDaAttribute()
    {
        return $this->componentAmount('da');
    }

    public function getSpecialAllowanceAttribute()
    {
        $amount = $this->componentAmount('special_allowance');

        if ($amount > 0) {
            return $amount;
        }

        if ($this->hasPersistedComponents()) {
            return 0.0;
        }

        $residual = (float) $this->monthly_gross
            - (float) $this->monthly_basic
            - (float) $this->hra
            - (float) $this->da
            - (float) $this->conveyance
            - (float) $this->medical
            - (float) $this->other_allowances;

        return max(0, round($residual, 2));
    }

    public function getConveyanceAttribute()
    {
        return $this->componentAmount('conveyance');
    }

    public function getMedicalAttribute()
    {
        return $this->componentAmount('medical');
    }

    public function getOtherAllowancesAttribute()
    {
        return $this->componentAmount('other_earning');
    }

    public function getPfEmployeeAttribute()
    {
        if (!($this->resolvedEmployee()?->pf_applicable ?? false)) {
            return 0.0;
        }

        $breakdown = $this->pfBreakdown();

        if ($breakdown['employee'] > 0) {
            return $breakdown['employee'];
        }

        return round(min((float) $this->monthly_basic * 0.12, 1800), 2);
    }

    public function getEsiEmployeeAttribute()
    {
        $amount = $this->componentAmount('esi_employee');

        if ($amount > 0) {
            return $amount;
        }

        if ($this->hasPersistedComponents() && $this->hasPersistedComponentFor('esi_employee')) {
            return 0.0;
        }

        if ($this->hasPersistedComponents() && !$this->hasStructureComponentFor('esi_employee')) {
            return 0.0;
        }

        if (!($this->resolvedEmployee()?->esi_applicable ?? false) || (float) $this->monthly_gross > 21000) {
            return 0.0;
        }

        return round((float) $this->monthly_gross * 0.0075, 2);
    }

    public function getProfessionalTaxAttribute()
    {
        $amount = $this->componentAmount('professional_tax');

        if ($amount > 0) {
            return $amount;
        }

        if ($this->hasPersistedComponents() && $this->hasPersistedComponentFor('professional_tax')) {
            return 0.0;
        }

        if ($this->hasPersistedComponents() && !$this->hasStructureComponentFor('professional_tax')) {
            return 0.0;
        }

        if (!($this->resolvedEmployee()?->pt_applicable ?? false)) {
            return 0.0;
        }

        return $this->fallbackProfessionalTax();
    }

    public function getTotalDeductionsAttribute()
    {
        return $this->pf_employee + $this->esi_employee + $this->professional_tax + $this->tds + $this->lwf_employee;
    }

    public function getPfEmployerAttribute()
    {
        if (!($this->resolvedEmployee()?->pf_applicable ?? false)) {
            return 0.0;
        }

        $breakdown = $this->pfBreakdown();

        if ($breakdown['employer_total'] > 0) {
            return $breakdown['employer_total'];
        }

        return round(min((float) $this->monthly_basic * 0.12, 1800), 2);
    }

    public function getEsiEmployerAttribute()
    {
        $amount = $this->componentAmount('esi_employer');

        if ($amount > 0) {
            return $amount;
        }

        if ($this->hasPersistedComponents() && $this->hasPersistedComponentFor('esi_employer')) {
            return 0.0;
        }

        if ($this->hasPersistedComponents() && !$this->hasStructureComponentFor('esi_employer')) {
            return 0.0;
        }

        if (!($this->resolvedEmployee()?->esi_applicable ?? false) || (float) $this->monthly_gross > 21000) {
            return 0.0;
        }

        return round((float) $this->monthly_gross * 0.0325, 2);
    }

    public function getPfApplicableAttribute()
    {
        return $this->pf_employee > 0
            || $this->pf_employer > 0
            || (bool) ($this->resolvedEmployee()?->pf_applicable ?? false);
    }

    public function getEsiApplicableAttribute()
    {
        return $this->esi_employee > 0
            || $this->esi_employer > 0
            || (bool) ($this->resolvedEmployee()?->esi_applicable ?? false);
    }

    public function getPtApplicableAttribute()
    {
        return $this->professional_tax > 0
            || (bool) ($this->resolvedEmployee()?->pt_applicable ?? false);
    }

    public function getTdsAttribute()
    {
        $amount = $this->componentAmount('tds');

        if ($amount > 0) {
            return $amount;
        }

        if (!($this->resolvedEmployee()?->tds_applicable ?? false)) {
            return 0.0;
        }

        return $this->fallbackTds();
    }

    public function getLwfEmployeeAttribute()
    {
        $amount = $this->componentAmount('lwf_employee');

        if ($amount > 0) {
            return $amount;
        }

        if (!($this->resolvedEmployee()?->lwf_applicable ?? false)) {
            return 0.0;
        }

        return $this->fallbackLwf()['employee'];
    }

    public function getLwfEmployerAttribute()
    {
        $amount = $this->componentAmount('lwf_employer');

        if ($amount > 0) {
            return $amount;
        }

        if (!($this->resolvedEmployee()?->lwf_applicable ?? false)) {
            return 0.0;
        }

        return $this->fallbackLwf()['employer'];
    }

    public function getRevisionReasonAttribute()
    {
        return $this->remarks;
    }

    protected function componentAmount(string $category): float
    {
        $component = $this->resolvedComponents()
            ->first(fn(HrEmployeeSalaryComponent $item) => $item->salaryComponent?->category === $category);

        if ($component) {
            return (float) ($component->monthly_amount ?? 0);
        }

        // Once employee-specific salary components are persisted, missing categories
        // should be treated as not applicable instead of falling back to structure defaults.
        if ($this->hasPersistedComponents()) {
            return 0.0;
        }

        return $this->fallbackComponentAmount($category);
    }

    protected function resolvedComponents(): Collection
    {
        if (!$this->relationLoaded('components')) {
            $this->load('components.salaryComponent');
        } elseif ($this->components->isNotEmpty() && !$this->components->first()->relationLoaded('salaryComponent')) {
            $this->load('components.salaryComponent');
        }

        return $this->components;
    }

    public function effectiveAmountForStructureComponent(HrSalaryComponent $component): float
    {
        $statutoryAmount = $this->statutoryComponentAmount($component);
        if ($statutoryAmount !== null) {
            return $statutoryAmount;
        }

        $persisted = $this->resolvedComponents()
            ->first(fn(HrEmployeeSalaryComponent $item) => (int) $item->hr_salary_component_id === (int) $component->id);

        if ($persisted) {
            return (float) ($persisted->monthly_amount ?? 0);
        }

        if ($this->hasPersistedComponents()) {
            return $this->calculateStructureComponentAmount($component);
        }

        foreach ($this->componentAliases() as $category => $aliases) {
            if ($component->category === $category || in_array($component->code, $aliases, true)) {
                return $this->componentAmount($category);
            }
        }

        return $this->calculateStructureComponentAmount($component);
    }

    protected function fallbackComponentAmount(string $category): float
    {
        return match ($category) {
            'basic' => (float) $this->monthly_basic,
            'hra', 'da', 'conveyance', 'medical', 'other_earning' => $this->fallbackFromStructure($category),
            'special_allowance' => $this->fallbackSpecialAllowance(),
            'pf_employee' => $this->fallbackFromStructure('pf_employee'),
            'pf_employer' => $this->fallbackFromStructure('pf_employer'),
            'esi_employee' => $this->fallbackFromStructure('esi_employee'),
            'esi_employer' => $this->fallbackFromStructure('esi_employer'),
            'professional_tax' => $this->fallbackFromStructure('professional_tax'),
            'tds' => $this->fallbackFromStructure('tds'),
            'lwf_employee' => $this->fallbackFromStructure('lwf_employee'),
            'lwf_employer' => $this->fallbackFromStructure('lwf_employer'),
            default => 0.0,
        };
    }

    protected function fallbackSpecialAllowance(): float
    {
        $configured = $this->fallbackFromStructure('special_allowance');

        if ($configured > 0) {
            return $configured;
        }

        $residual = (float) $this->monthly_gross
            - (float) $this->monthly_basic
            - $this->fallbackFromStructure('hra')
            - $this->fallbackFromStructure('da')
            - $this->fallbackFromStructure('conveyance')
            - $this->fallbackFromStructure('medical')
            - $this->fallbackFromStructure('other_earning');

        return max(0, round($residual, 2));
    }

    protected function fallbackProfessionalTax(): float
    {
        $employee = $this->resolvedEmployee();

        if (!$employee?->pt_state || (float) $this->monthly_gross <= 0) {
            return 0.0;
        }

        $effectiveDate = optional($this->effective_from)->format('Y-m-d') ?: now()->toDateString();
        $employeeState = mb_strtolower(trim((string) $employee->pt_state));
        $employeeGender = mb_strtolower((string) ($employee->gender ?? 'all'));

        $slab = HrProfessionalTaxSlab::query()
            ->where('is_active', true)
            ->where('effective_from', '<=', $effectiveDate)
            ->where(function ($query) use ($effectiveDate) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $effectiveDate);
            })
            ->where('salary_from', '<=', (float) $this->monthly_gross)
            ->where('salary_to', '>=', (float) $this->monthly_gross)
            ->get()
            ->first(function (HrProfessionalTaxSlab $slab) use ($employeeState, $employeeGender) {
                $slabStateCode = mb_strtolower(trim((string) $slab->state_code));
                $slabStateName = mb_strtolower(trim((string) $slab->state_name));
                $slabGender = mb_strtolower((string) ($slab->gender ?? 'all'));

                $stateMatches = $employeeState !== ''
                    && ($employeeState === $slabStateCode || $employeeState === $slabStateName);

                $genderMatches = $slabGender === 'all'
                    || $employeeGender === ''
                    || $employeeGender === $slabGender;

                return $stateMatches && $genderMatches;
            });

        return round((float) ($slab?->tax_amount ?? 0), 2);
    }

    protected function fallbackFromStructure(string $category): float
    {
        $structureComponent = $this->findStructureComponent($category);

        if (!$structureComponent) {
            return 0.0;
        }

        return $this->calculateStructureComponentAmount($structureComponent);
    }

    protected function findStructureComponent(string $category): ?HrSalaryComponent
    {
        $structureComponents = $this->resolvedStructureComponents();
        $aliases = $this->componentAliases()[$category] ?? [];

        return $structureComponents->first(function (HrSalaryComponent $component) use ($category, $aliases) {
            return $component->category === $category || in_array($component->code, $aliases, true);
        });
    }

    protected function resolvedStructureComponents(): Collection
    {
        if (!$this->relationLoaded('salaryStructure')) {
            $this->load('salaryStructure.components');
        } elseif ($this->salaryStructure && !$this->salaryStructure->relationLoaded('components')) {
            $this->salaryStructure->load('components');
        }

        return $this->salaryStructure?->components ?? new Collection();
    }

    protected function calculateStructureComponentAmount(HrSalaryComponent $component): float
    {
        $statutoryAmount = $this->statutoryComponentAmount($component);
        if ($statutoryAmount !== null) {
            return $statutoryAmount;
        }

        $calculationType = (string) ($component->pivot->calculation_type ?? $component->calculation_type ?? 'fixed');
        $value = (float) ($component->pivot->value ?? $component->default_value ?? 0);
        $percentage = (float) ($component->pivot->percentage ?? $component->percentage ?? 0);

        return match ($calculationType) {
            'fixed' => $component->category === 'basic' && $value <= 0 ? (float) $this->monthly_basic : $value,
            'percent_of_basic' => round((float) $this->monthly_basic * ($percentage / 100), 2),
            'percent_of_gross' => round((float) $this->monthly_gross * ($percentage / 100), 2),
            'percent_of_ctc' => round((float) $this->monthly_ctc * ($percentage / 100), 2),
            'slab_based' => $component->category === 'professional_tax' ? $this->fallbackProfessionalTax() : 0.0,
            default => 0.0,
        };
    }

    protected function hasPersistedComponents(): bool
    {
        return $this->resolvedComponents()->isNotEmpty();
    }

    protected function resolvedEmployee(): ?HrEmployee
    {
        if (!$this->relationLoaded('employee')) {
            $this->load('employee');
        }

        return $this->employee;
    }

    protected function componentAliases(): array
    {
        return [
            'basic' => ['BASIC'],
            'hra' => ['HRA'],
            'da' => ['DA'],
            'conveyance' => ['CONV'],
            'medical' => ['MED'],
            'special_allowance' => ['SPECIAL'],
            'other_earning' => ['OTHER', 'OTH'],
            'pf_employee' => ['PF_EE'],
            'pf_employer' => ['PF_ER'],
            'esi_employee' => ['ESI_EE'],
            'esi_employer' => ['ESI_ER'],
            'professional_tax' => ['PT'],
            'tds' => ['TDS'],
            'lwf_employee' => ['LWF_EE'],
            'lwf_employer' => ['LWF_ER'],
            'eps' => ['EPS'],
        ];
    }

    protected function hasPersistedComponentFor(string $category): bool
    {
        $aliases = $this->componentAliases()[$category] ?? [];

        return $this->resolvedComponents()->contains(function (HrEmployeeSalaryComponent $item) use ($category, $aliases) {
            return $item->salaryComponent
                && ($item->salaryComponent->category === $category || in_array($item->salaryComponent->code, $aliases, true));
        });
    }

    protected function hasStructureComponentFor(string $category): bool
    {
        return $this->findStructureComponent($category) !== null;
    }

    protected function statutoryComponentAmount(HrSalaryComponent $component): ?float
    {
        return match ($component->category) {
            'pf_employee' => ($this->resolvedEmployee()?->pf_applicable ?? false) ? $this->pfBreakdown()['employee'] : 0.0,
            'pf_employer' => ($this->resolvedEmployee()?->pf_applicable ?? false) ? $this->pfBreakdown()['employer_pf'] : 0.0,
            'eps' => ($this->resolvedEmployee()?->pf_applicable ?? false) ? $this->pfBreakdown()['eps'] : 0.0,
            'professional_tax' => ($this->resolvedEmployee()?->pt_applicable ?? false) ? $this->fallbackProfessionalTax() : 0.0,
            'tds' => ($this->resolvedEmployee()?->tds_applicable ?? false) ? $this->fallbackTds() : 0.0,
            'lwf_employee' => ($this->resolvedEmployee()?->lwf_applicable ?? false) ? $this->fallbackLwf()['employee'] : 0.0,
            'lwf_employer' => ($this->resolvedEmployee()?->lwf_applicable ?? false) ? $this->fallbackLwf()['employer'] : 0.0,
            default => null,
        };
    }

    protected function pfBreakdown(): array
    {
        $effectiveDate = optional($this->effective_from)->format('Y-m-d') ?: now()->toDateString();
        $slab = HrPfSlab::query()
            ->where('is_active', true)
            ->where('effective_from', '<=', $effectiveDate)
            ->where(function ($query) use ($effectiveDate) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $effectiveDate);
            })
            ->orderByDesc('effective_from')
            ->first();

        $basic = (float) $this->monthly_basic;
        if (!$slab || $basic <= 0) {
            return [
                'employee' => 0.0,
                'employer_pf' => 0.0,
                'eps' => 0.0,
                'employer_total' => 0.0,
            ];
        }

        $pfWages = min($basic, (float) $slab->wage_ceiling);
        $employee = round($pfWages * ((float) $slab->employee_contribution_rate / 100), 2);
        $employerPf = round($pfWages * ((float) $slab->employer_pf_rate / 100), 2);
        $eps = round($pfWages * ((float) $slab->employer_eps_rate / 100), 2);

        return [
            'employee' => $employee,
            'employer_pf' => $employerPf,
            'eps' => $eps,
            'employer_total' => round($employerPf + $eps, 2),
        ];
    }

    protected function fallbackTds(): float
    {
        $employee = $this->resolvedEmployee();
        if (!$employee) {
            return 0.0;
        }

        $financialYear = $this->financialYearForDate($this->effective_from ?? now());
        $declaration = HrTaxDeclaration::query()
            ->where('hr_employee_id', $employee->id)
            ->where('financial_year', $financialYear)
            ->first();

        $regime = (string) ($declaration?->tax_regime ?: $employee->tax_regime ?: 'new');
        $annualIncome = max(0, round((float) $this->monthly_gross * 12, 2));
        $totalExemption = (float) ($declaration?->total_verified ?? $declaration?->total_declared ?? $declaration?->total_exemption ?? 0);
        $taxableIncome = max(0, $annualIncome - $totalExemption);
        $annualTax = $this->estimateAnnualTaxFromSlabs($taxableIncome, $financialYear, $regime);

        return $annualTax > 0 ? round($annualTax / 12, 2) : 0.0;
    }

    protected function fallbackLwf(): array
    {
        $employee = $this->resolvedEmployee();
        if (!$employee?->pt_state) {
            return ['employee' => 0.0, 'employer' => 0.0];
        }

        $effectiveDate = optional($this->effective_from)->format('Y-m-d') ?: now()->toDateString();
        $employeeState = mb_strtolower(trim((string) $employee->pt_state));

        $slab = HrLwfSlab::query()
            ->where('is_active', true)
            ->where('effective_from', '<=', $effectiveDate)
            ->where(function ($query) use ($effectiveDate) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $effectiveDate);
            })
            ->get()
            ->first(function (HrLwfSlab $slab) use ($employeeState) {
                $slabStateCode = mb_strtolower(trim((string) $slab->state_code));
                $slabStateName = mb_strtolower(trim((string) $slab->state_name));

                return $employeeState !== ''
                    && ($employeeState === $slabStateCode || $employeeState === $slabStateName);
            });

        if (!$slab || !$this->isLwfApplicableForMonth($slab, (int) (($this->effective_from ?? now())->month))) {
            return ['employee' => 0.0, 'employer' => 0.0];
        }

        return [
            'employee' => round((float) $slab->employee_contribution, 2),
            'employer' => round((float) $slab->employer_contribution, 2),
        ];
    }

    protected function estimateAnnualTaxFromSlabs(float $taxableIncome, string $financialYear, string $regime): float
    {
        $slabs = HrTdsSlab::query()
            ->whereIn('financial_year', $this->financialYearAliases($financialYear))
            ->where('regime', $regime)
            ->where('category', 'general')
            ->where('is_active', true)
            ->orderBy('income_from')
            ->get();

        if ($slabs->isEmpty()) {
            return $this->estimateAnnualTaxFallback($taxableIncome, $regime);
        }

        $tax = 0.0;
        $cessPercent = 0.0;

        foreach ($slabs as $slab) {
            $slabStart = (float) $slab->income_from;
            $slabEnd = (float) $slab->income_to;
            if ($taxableIncome <= $slabStart) {
                continue;
            }

            $taxablePortion = min($taxableIncome, $slabEnd) - $slabStart;
            if ($taxablePortion <= 0) {
                continue;
            }

            $tax += $taxablePortion * ((float) $slab->tax_percent / 100);
            $tax += $taxablePortion * ((float) $slab->surcharge_percent / 100);
            $cessPercent = max($cessPercent, (float) $slab->cess_percent);
        }

        if ($tax <= 0) {
            return 0.0;
        }

        return round($tax * (1 + ($cessPercent / 100)), 2);
    }

    protected function estimateAnnualTaxFallback(float $taxableIncome, string $regime): float
    {
        if ($taxableIncome <= 300000) {
            return 0.0;
        }

        $tax = 0.0;
        $slabs = $regime === 'old'
            ? [[250000, 0], [500000, 5], [1000000, 20], [INF, 30]]
            : [[300000, 0], [700000, 5], [1000000, 10], [1200000, 15], [1500000, 20], [INF, 30]];

        $previous = 0.0;
        foreach ($slabs as [$limit, $rate]) {
            if ($taxableIncome <= $previous) {
                break;
            }

            $amount = min($taxableIncome, $limit) - $previous;
            if ($amount > 0) {
                $tax += $amount * ($rate / 100);
            }

            $previous = $limit;
        }

        return round($tax * 1.04, 2);
    }

    protected function financialYearForDate($date): string
    {
        $date = $date instanceof \Illuminate\Support\Carbon ? $date : now();
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
    }

    protected function financialYearAliases(string $financialYear): array
    {
        if (!preg_match('/^(\d{4})-(\d{2}|\d{4})$/', $financialYear, $matches)) {
            return [$financialYear];
        }

        $startYear = (int) $matches[1];
        $endYear = $matches[2];
        $endYearFull = strlen($endYear) === 2 ? (int) substr((string) ($startYear + 1), 0, 2) . $endYear : (int) $endYear;

        return array_values(array_unique([
            sprintf('%d-%02d', $startYear, $endYearFull % 100),
            sprintf('%d-%d', $startYear, $endYearFull),
        ]));
    }

    protected function isLwfApplicableForMonth(HrLwfSlab $slab, int $month): bool
    {
        return match ((string) $slab->frequency) {
            'monthly' => true,
            'half_yearly' => in_array($month, [6, 12], true),
            'annual' => $month === 12,
            default => false,
        };
    }
}
