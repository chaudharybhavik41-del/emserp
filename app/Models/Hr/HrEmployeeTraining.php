<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployeeTraining extends Model
{
    protected $table = 'hr_employee_trainings';

    protected $fillable = [
        'hr_employee_id',
        'training_name',
        'training_type',
        'trainer_name',
        'training_provider',
        'start_date',
        'end_date',
        'duration_hours',
        'location',
        'cost',
        'cost_borne_by_company',
        'status',
        'score',
        'grade',
        'certificate_path',
        'feedback',
        'remarks',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_hours' => 'decimal:2',
        'cost' => 'decimal:2',
        'cost_borne_by_company' => 'boolean',
        'score' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
