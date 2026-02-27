<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuttingPlan extends Model
{
    protected $fillable = [
        'project_id',
        'bom_id',
        'grade',
        'thickness_mm',
        'name',
        'status',
        'notes',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function plates(): HasMany
    {
        return $this->hasMany(CuttingPlanPlate::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasManyThrough(
            CuttingPlanAllocation::class,
            CuttingPlanPlate::class,
            'cutting_plan_id',
            'cutting_plan_plate_id'
        );
    }
}
