<?php

namespace App\Models\Hr;

use App\Enums\Hr\EmployeeStatus;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class HrEmployee extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_employees';

    protected $fillable = [
        'company_id',
        'user_id',
        'employee_code',
        'biometric_id',
        'card_number',
        'first_name',
        'middle_name',
        'last_name',
        'father_name',
        'mother_name',
        'spouse_name',
        'date_of_birth',
        'gender',
        'marital_status',
        'blood_group',
        'nationality',
        'religion',
        'caste_category',
        'personal_email',
        'official_email',
        'personal_mobile',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'present_address',
        'present_city',
        'present_state',
        'present_pincode',
        'permanent_address',
        'permanent_city',
        'permanent_state',
        'permanent_pincode',
        'address_same_as_present',
        'pan_number',
        'aadhar_number',
        'passport_number',
        'passport_expiry',
        'driving_license',
        'dl_expiry',
        'voter_id',
        'date_of_joining',
        'confirmation_date',
        'date_of_leaving',
        'leaving_reason',
        'employment_type',
        'employee_category',
        'probation_period_months',
        'notice_period_days',
        'department_id',
        'hr_designation_id',
        'hr_grade_id',
        'reporting_to',
        'cost_center',
        'work_location_id',
        'default_shift_id',
        'hr_attendance_policy_id',
        'hr_leave_policy_id',
        'overtime_applicable',
        'attendance_mode',
        'hr_salary_structure_id',
        'payment_mode',
        'pf_applicable',
        'pf_number',
        'pf_join_date',
        'eps_applicable',
        'esi_applicable',
        'esi_number',
        'esi_join_date',
        'pt_applicable',
        'pt_state',
        'lwf_applicable',
        'tds_applicable',
        'tax_regime',
        'bank_name',
        'bank_branch',
        'bank_account_number',
        'bank_ifsc',
        'bank_account_type',
        'gratuity_applicable',
        'health_insurance_enrolled',
        'health_insurance_policy_no',
        'sum_insured',
        'highest_qualification',
        'specialization',
        'total_experience_months',
        'status',
        'is_active',
        'photo_path',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'passport_expiry' => 'date',
        'dl_expiry' => 'date',
        'date_of_joining' => 'date',
        'confirmation_date' => 'date',
        'date_of_leaving' => 'date',
        'pf_join_date' => 'date',
        'esi_join_date' => 'date',
        'address_same_as_present' => 'boolean',
        'overtime_applicable' => 'boolean',
        'pf_applicable' => 'boolean',
        'eps_applicable' => 'boolean',
        'esi_applicable' => 'boolean',
        'pt_applicable' => 'boolean',
        'lwf_applicable' => 'boolean',
        'tds_applicable' => 'boolean',
        'gratuity_applicable' => 'boolean',
        'health_insurance_enrolled' => 'boolean',
        'is_active' => 'boolean',
        'sum_insured' => 'decimal:2',
        'status' => EmployeeStatus::class,
    ];

    // Accessors
    
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->employee_code} - {$this->full_name}";
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? Carbon::parse($this->date_of_birth)->age : null;
    }

    public function getServiceYearsAttribute(): float
    {
        $endDate = $this->date_of_leaving ?? now();
        return round($this->date_of_joining->diffInMonths($endDate) / 12, 1);
    }

    public function getServiceMonthsAttribute(): int
    {
        $endDate = $this->date_of_leaving ?? now();
        return $this->date_of_joining->diffInMonths($endDate);
    }

    public function getIsOnProbationAttribute(): bool
    {
        if ($this->confirmation_date) {
            return false;
        }
        
        $probationEnd = $this->date_of_joining->addMonths($this->probation_period_months);
        return now()->lt($probationEnd);
    }

    public function getProbationEndDateAttribute(): ?Carbon
    {
        if ($this->confirmation_date) {
            return null;
        }
        return $this->date_of_joining->addMonths($this->probation_period_months);
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(HrDesignation::class, 'hr_designation_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(HrGrade::class, 'hr_grade_id');
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporting_to');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'reporting_to');
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(HrWorkLocation::class, 'work_location_id');
    }

    public function defaultShift(): BelongsTo
    {
        return $this->belongsTo(HrShift::class, 'default_shift_id');
    }

    public function attendancePolicy(): BelongsTo
    {
        return $this->belongsTo(HrAttendancePolicy::class, 'hr_attendance_policy_id');
    }

    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(HrLeavePolicy::class, 'hr_leave_policy_id');
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(HrSalaryStructure::class, 'hr_salary_structure_id');
    }

    public function currentSalary(): HasOne
    {
        return $this->hasOne(HrEmployeeSalary::class, 'hr_employee_id')
            ->where('is_current', true)
            ->latest('effective_from');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(HrEmployeeSalary::class, 'hr_employee_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(HrAttendance::class, 'hr_employee_id');
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(HrLeaveBalance::class, 'hr_employee_id');
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(HrLeaveApplication::class, 'hr_employee_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(HrPayroll::class, 'hr_employee_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(HrEmployeeLoan::class, 'hr_employee_id');
    }

    public function advances(): HasMany
    {
        return $this->hasMany(HrSalaryAdvance::class, 'hr_employee_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(HrEmployeeDocument::class, 'hr_employee_id');
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(HrEmployeeQualification::class, 'hr_employee_id');
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(HrEmployeeExperience::class, 'hr_employee_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(HrEmployeeDependent::class, 'hr_employee_id');
    }

    public function nominees(): HasMany
    {
        return $this->hasMany(HrEmployeeNominee::class, 'hr_employee_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(HrEmployeeBankAccount::class, 'hr_employee_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(HrEmployeeAsset::class, 'hr_employee_id');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(HrEmployeeTraining::class, 'hr_employee_id');
    }

    public function shiftRosters(): HasMany
    {
        return $this->hasMany(HrShiftRoster::class, 'hr_employee_id');
    }

    public function overtimeRecords(): HasMany
    {
        return $this->hasMany(HrOvertimeRecord::class, 'hr_employee_id');
    }

    public function taxDeclarations(): HasMany
    {
        return $this->hasMany(HrTaxDeclaration::class, 'hr_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', EmployeeStatus::ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where(function ($q) {
            $q->where('is_active', false)
                ->orWhere('status', '!=', EmployeeStatus::ACTIVE);
        });
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeByDesignation($query, $designationId)
    {
        return $query->where('hr_designation_id', $designationId);
    }

    public function scopeByEmploymentType($query, $type)
    {
        return $query->where('employment_type', $type);
    }

    public function scopeOnProbation($query)
    {
        return $query->whereNull('confirmation_date')
            ->whereRaw('DATE_ADD(date_of_joining, INTERVAL probation_period_months MONTH) > NOW()');
    }

    public function scopeConfirmed($query)
    {
        return $query->whereNotNull('confirmation_date');
    }

    public function scopePfApplicable($query)
    {
        return $query->where('pf_applicable', true);
    }

    public function scopeEsiApplicable($query)
    {
        return $query->where('esi_applicable', true);
    }

    // Methods

    public static function generateEmployeeCode(): string
    {
        $year = now()->format('Y');
        $prefix = "EMP-{$year}-";
        
        $lastEmployee = self::where('employee_code', 'like', $prefix . '%')
            ->orderByDesc('employee_code')
            ->first();
        
        if ($lastEmployee) {
            $lastNum = (int) substr($lastEmployee->employee_code, -4);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }
        
        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }

    public function getLeaveBalance(int $leaveTypeId, ?int $year = null): float
    {
        $year = $year ?? now()->year;
        
        $balance = $this->leaveBalances()
            ->where('hr_leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();
        
        return $balance ? $balance->available_balance : 0;
    }

    public function getCurrentShift(?Carbon $date = null): ?HrShift
    {
        $date = $date ?? now();
        
        // Check daily assignment first
        $dailyAssignment = HrDailyShiftAssignment::where('hr_employee_id', $this->id)
            ->where('date', $date->toDateString())
            ->first();
        
        if ($dailyAssignment && $dailyAssignment->hr_shift_id) {
            return $dailyAssignment->shift;
        }
        
        // Check roster
        $roster = $this->shiftRosters()
            ->where('is_current', true)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->first();
        
        if ($roster) {
            return $roster->shift;
        }
        
        // Return default shift
        return $this->defaultShift;
    }

    public function getPerDaySalary(): float
    {
        $currentSalary = $this->currentSalary;
        if (!$currentSalary) {
            return 0;
        }
        
        return round($currentSalary->monthly_gross / 30, 2);
    }

    public function getHourlyRate(): float
    {
        return round($this->getPerDaySalary() / 8, 2);
    }
}
