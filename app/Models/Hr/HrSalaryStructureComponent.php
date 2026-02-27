<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrSalaryStructureComponent extends Model
{
    protected $table = 'hr_salary_structure_components';

    protected $fillable = [
        'hr_salary_structure_id',
        'hr_salary_component_id',
        'calculation_type',
        'value',
        'percentage',
        'formula',
        'min_value',
        'max_value',
        'is_mandatory',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'percentage' => 'decimal:4',
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(HrSalaryStructure::class, 'hr_salary_structure_id');
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(HrSalaryComponent::class, 'hr_salary_component_id');
    }
}
