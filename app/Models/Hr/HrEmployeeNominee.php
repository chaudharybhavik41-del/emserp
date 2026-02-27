<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployeeNominee extends Model
{
    protected $table = 'hr_employee_nominees';

    protected $fillable = [
        'hr_employee_id',
        'nomination_for',
        'name',
        'relationship',
        'date_of_birth',
        'address',
        'aadhar_number',
        'share_percentage',
        'is_minor',
        'guardian_name',
        'guardian_relationship',
        'guardian_address',
        'effective_from',
        'is_active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'share_percentage' => 'decimal:2',
        'is_minor' => 'boolean',
        'effective_from' => 'date',
        'is_active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
