<?php

namespace App\Models\Hr;

use App\Enums\Hr\PayrollStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrPayroll extends Model
{
    use HasFactory;

    protected $table = 'hr_payrolls';

    protected $fillable = [
        'payroll_number',
        'hr_payroll_period_id',
        'hr_payroll_batch_id',
        'hr_employee_id',
        'hr_employee_salary_id',
        'employee_code',
        'employee_name',
        'department_name',
        'designation_name',
        'bank_account',
        'bank_ifsc',
        'working_days',
        'present_days',
        'paid_days',
        'absent_days',
        'leave_days',
        'holidays',
        'week_offs',
        'half_days',
        'late_days',
        'ot_hours',
        'lop_days',
        'basic',
        'hra',
        'da',
        'special_allowance',
        'conveyance',
        'medical',
        'other_earnings',
        'ot_amount',
        'incentive',
        'bonus',
        'arrears',
        'reimbursements',
        'total_earnings',
        'gross_salary',
        'pf_employee',
        'esi_employee',
        'professional_tax',
        'tds',
        'lwf_employee',
        'loan_deduction',
        'advance_deduction',
        'other_deductions',
        'lop_deduction',
        'total_deductions',
        'net_pay',
        'round_off',
        'net_payable',
        'pf_employer',
        'eps_employer',
        'edli_employer',
        'pf_admin_charges',
        'esi_employer',
        'lwf_employer',
        'gratuity_provision',
        'total_employer_cost',
        'ctc',
        'status',
        'payment_mode',
        'payment_reference',
        'payment_date',
        'is_hold',
        'hold_reason',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'paid_days' => 'decimal:2',
        'half_days' => 'decimal:1',
        'ot_hours' => 'decimal:2',
        'basic' => 'decimal:2',
        'hra' => 'decimal:2',
        'da' => 'decimal:2',
        'special_allowance' => 'decimal:2',
        'conveyance' => 'decimal:2',
        'medical' => 'decimal:2',
        'other_earnings' => 'decimal:2',
        'ot_amount' => 'decimal:2',
        'incentive' => 'decimal:2',
        'bonus' => 'decimal:2',
        'arrears' => 'decimal:2',
        'reimbursements' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'pf_employee' => 'decimal:2',
        'esi_employee' => 'decimal:2',
        'professional_tax' => 'decimal:2',
        'tds' => 'decimal:2',
        'lwf_employee' => 'decimal:2',
        'loan_deduction' => 'decimal:2',
        'advance_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'lop_deduction' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'round_off' => 'decimal:2',
        'net_payable' => 'decimal:2',
        'pf_employer' => 'decimal:2',
        'eps_employer' => 'decimal:2',
        'edli_employer' => 'decimal:2',
        'pf_admin_charges' => 'decimal:2',
        'esi_employer' => 'decimal:2',
        'lwf_employer' => 'decimal:2',
        'gratuity_provision' => 'decimal:2',
        'total_employer_cost' => 'decimal:2',
        'ctc' => 'decimal:2',
        'is_hold' => 'boolean',
        'status' => PayrollStatus::class,
    ];

    // Relationships

    public function period(): BelongsTo
    {
        return $this->belongsTo(HrPayrollPeriod::class, 'hr_payroll_period_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(HrPayrollBatch::class, 'hr_payroll_batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function employeeSalary(): BelongsTo
    {
        return $this->belongsTo(HrEmployeeSalary::class, 'hr_employee_salary_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(HrPayrollComponent::class, 'hr_payroll_id')
            ->orderBy('sort_order');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(HrPayrollAdjustment::class, 'hr_payroll_id');
    }

    public function overtimeRecords(): HasMany
    {
        return $this->hasMany(HrOvertimeRecord::class, 'hr_payroll_id');
    }

    public function loanRepayments(): HasMany
    {
        return $this->hasMany(HrLoanRepayment::class, 'hr_payroll_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes

    public function scopeForPeriod($query, $periodId)
    {
        return $query->where('hr_payroll_period_id', $periodId);
    }

    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('hr_employee_id', $employeeId);
    }

    public function scopeByStatus($query, PayrollStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [PayrollStatus::DRAFT, PayrollStatus::PROCESSED]);
    }

    public function scopePaid($query)
    {
        return $query->where('status', PayrollStatus::PAID);
    }

    public function scopeOnHold($query)
    {
        return $query->where('is_hold', true);
    }

    // Accessors

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status->color();
    }

    public function getEarningsBreakdownAttribute(): array
    {
        return [
            'Basic' => $this->basic,
            'HRA' => $this->hra,
            'DA' => $this->da,
            'Special Allowance' => $this->special_allowance,
            'Conveyance' => $this->conveyance,
            'Medical' => $this->medical,
            'OT Amount' => $this->ot_amount,
            'Incentive' => $this->incentive,
            'Bonus' => $this->bonus,
            'Arrears' => $this->arrears,
            'Reimbursements' => $this->reimbursements,
            'Other Earnings' => $this->other_earnings,
        ];
    }

    public function getDeductionsBreakdownAttribute(): array
    {
        return [
            'PF (Employee)' => $this->pf_employee,
            'ESI (Employee)' => $this->esi_employee,
            'Professional Tax' => $this->professional_tax,
            'TDS' => $this->tds,
            'LWF (Employee)' => $this->lwf_employee,
            'Loan Recovery' => $this->loan_deduction,
            'Advance Recovery' => $this->advance_deduction,
            'LOP Deduction' => $this->lop_deduction,
            'Other Deductions' => $this->other_deductions,
        ];
    }

    public function getEmployerContributionsAttribute(): array
    {
        return [
            'PF (Employer)' => $this->pf_employer,
            'EPS' => $this->eps_employer,
            'EDLI' => $this->edli_employer,
            'PF Admin' => $this->pf_admin_charges,
            'ESI (Employer)' => $this->esi_employer,
            'LWF (Employer)' => $this->lwf_employer,
            'Gratuity Provision' => $this->gratuity_provision,
        ];
    }

    // Methods

    public function isEditable(): bool
    {
        return $this->status->canEdit() && !$this->is_hold;
    }

    public function canApprove(): bool
    {
        return $this->status->canApprove() && !$this->is_hold;
    }

    public function canPay(): bool
    {
        return $this->status->canPay() && !$this->is_hold;
    }

    public function hold(string $reason): void
    {
        $this->update([
            'is_hold' => true,
            'hold_reason' => $reason,
        ]);
    }

    public function release(): void
    {
        $this->update([
            'is_hold' => false,
            'hold_reason' => null,
        ]);
    }

    public function calculateTotals(): void
    {
        // Calculate total earnings
        $this->total_earnings = $this->basic + $this->hra + $this->da + 
            $this->special_allowance + $this->conveyance + $this->medical +
            $this->other_earnings + $this->ot_amount + $this->incentive +
            $this->bonus + $this->arrears + $this->reimbursements;
        
        $this->gross_salary = $this->total_earnings;
        
        // Calculate total deductions
        $this->total_deductions = $this->pf_employee + $this->esi_employee +
            $this->professional_tax + $this->tds + $this->lwf_employee +
            $this->loan_deduction + $this->advance_deduction +
            $this->other_deductions + $this->lop_deduction;
        
        // Calculate net pay
        $this->net_pay = $this->gross_salary - $this->total_deductions;
        
        // Round off
        $this->round_off = round($this->net_pay) - $this->net_pay;
        $this->net_payable = round($this->net_pay);
        
        // Calculate employer costs
        $this->total_employer_cost = $this->pf_employer + $this->eps_employer +
            $this->edli_employer + $this->pf_admin_charges + $this->esi_employer +
            $this->lwf_employer + $this->gratuity_provision;
        
        // CTC
        $this->ctc = $this->gross_salary + $this->total_employer_cost;
    }

    public static function generateNumber(int $periodId): string
    {
        $period = HrPayrollPeriod::find($periodId);
        $prefix = 'PAY-' . $period->year . str_pad($period->month, 2, '0', STR_PAD_LEFT) . '-';
        
        $lastPayroll = self::where('payroll_number', 'like', $prefix . '%')
            ->orderByDesc('payroll_number')
            ->first();
        
        if ($lastPayroll) {
            $lastNum = (int) substr($lastPayroll->payroll_number, -4);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }
        
        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
}
