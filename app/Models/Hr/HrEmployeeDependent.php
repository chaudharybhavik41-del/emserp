<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployeeDependent extends Model
{
    protected $table = 'hr_employee_dependents';

    protected $fillable = [
        'hr_employee_id',
        'name',
        'relationship',
        'date_of_birth',
        'gender',
        'aadhar_number',
        'phone',
        'address',
        'is_dependent_for_insurance',
        'is_emergency_contact',
        'is_nominee',
        'nomination_percentage',
        'occupation',
        'is_disabled',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_dependent_for_insurance' => 'boolean',
        'is_emergency_contact' => 'boolean',
        'is_nominee' => 'boolean',
        'nomination_percentage' => 'decimal:2',
        'is_disabled' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }
}
