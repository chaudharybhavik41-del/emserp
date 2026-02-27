<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrEmployeeLoan extends Model
{
    protected $table = 'hr_employee_loans';

    protected $fillable = [
        'loan_number',
        'hr_employee_id',
        'hr_loan_type_id',
        'application_date',
        'applied_amount',
        'approved_amount',
        'disbursed_amount',
        'tenure_months',
        'interest_rate',
        'emi_amount',
        'approved_date',
        'disbursement_date',
        'emi_start_date',
        'emi_end_date',
        'principal_outstanding',
        'interest_outstanding',
        'total_outstanding',
        'total_recovered',
        'emis_paid',
        'emis_pending',
        'status',
        'rejection_reason',
        'approved_by',
        'approval_remarks',
        'purpose',
        'created_by',
    ];

    protected $casts = [
        'application_date' => 'date',
        'approved_date' => 'date',
        'disbursement_date' => 'date',
        'emi_start_date' => 'date',
        'emi_end_date' => 'date',
        'applied_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'disbursed_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'emi_amount' => 'decimal:2',
        'principal_outstanding' => 'decimal:2',
        'interest_outstanding' => 'decimal:2',
        'total_outstanding' => 'decimal:2',
        'total_recovered' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(HrLoanType::class, 'hr_loan_type_id');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(HrLoanRepayment::class, 'hr_employee_loan_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber(): string
    {
        $prefix = 'LOAN-' . now()->format('Ym') . '-';

        $last = static::query()
            ->where('loan_number', 'like', $prefix . '%')
            ->orderByDesc('loan_number')
            ->first();

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last->loan_number, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
