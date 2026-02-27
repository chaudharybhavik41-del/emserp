<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLoanRepayment extends Model
{
    protected $table = 'hr_loan_repayments';

    protected $fillable = [
        'hr_employee_loan_id',
        'installment_no',
        'due_date',
        'principal_amount',
        'interest_amount',
        'emi_amount',
        'opening_balance',
        'closing_balance',
        'paid_amount',
        'paid_date',
        'status',
        'hr_payroll_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'emi_amount' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(HrEmployeeLoan::class, 'hr_employee_loan_id');
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(HrPayroll::class, 'hr_payroll_id');
    }
}
