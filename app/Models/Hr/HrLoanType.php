<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrLoanType extends Model
{
    protected $table = 'hr_loan_types';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'max_amount',
        'max_tenure_months',
        'interest_rate',
        'interest_applicable',
        'min_service_months',
        'max_percent_of_salary',
        'is_active',
    ];

    protected $casts = [
        'max_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'max_percent_of_salary' => 'decimal:2',
        'interest_applicable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(HrEmployeeLoan::class, 'hr_loan_type_id');
    }
}
