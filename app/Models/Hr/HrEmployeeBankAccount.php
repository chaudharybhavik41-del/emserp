<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployeeBankAccount extends Model
{
    protected $table = 'hr_employee_bank_accounts';

    protected $fillable = [
        'hr_employee_id',
        'bank_name',
        'branch_name',
        'account_number',
        'ifsc_code',
        'account_holder_name',
        'account_type',
        'is_primary',
        'is_active',
        'cancelled_cheque_path',
        'is_verified',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
