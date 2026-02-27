<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrSalaryAdvance extends Model
{
    protected $table = 'hr_salary_advances';

    protected $fillable = [
        'advance_number',
        'hr_employee_id',
        'application_date',
        'requested_amount',
        'approved_amount',
        'disbursed_amount',
        'purpose',
        'recovery_months',
        'monthly_deduction',
        'recovered_amount',
        'balance_amount',
        'recovery_start_date',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'created_by',
    ];

    protected $casts = [
        'application_date' => 'date',
        'recovery_start_date' => 'date',
        'approved_at' => 'datetime',
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'disbursed_amount' => 'decimal:2',
        'monthly_deduction' => 'decimal:2',
        'recovered_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
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
        $prefix = 'ADV-' . now()->format('Ym') . '-';

        $last = static::query()
            ->where('advance_number', 'like', $prefix . '%')
            ->orderByDesc('advance_number')
            ->first();

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last->advance_number, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
