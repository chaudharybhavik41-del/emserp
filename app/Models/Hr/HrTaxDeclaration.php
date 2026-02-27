<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrTaxDeclaration extends Model
{
    protected $table = 'hr_tax_declarations';

    protected $fillable = [
        'hr_employee_id',
        'financial_year',
        'tax_regime',
        'status',
        'submitted_at',
        'verified_by',
        'verified_at',
        'total_declared',
        'total_verified',
        'total_exemption',
        'taxable_income',
        'tax_liability',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'total_declared' => 'decimal:2',
        'total_verified' => 'decimal:2',
        'total_exemption' => 'decimal:2',
        'taxable_income' => 'decimal:2',
        'tax_liability' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(HrTaxDeclarationDetail::class, 'hr_tax_declaration_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function recalculateTotals(): void
    {
        $totals = $this->details()
            ->selectRaw('COALESCE(SUM(declared_amount),0) as declared_total, COALESCE(SUM(verified_amount),0) as verified_total')
            ->first();

        $this->total_declared = (float) ($totals?->declared_total ?? 0);
        $this->total_verified = (float) ($totals?->verified_total ?? 0);
        $this->total_exemption = $this->total_verified;
        $this->save();
    }
}
