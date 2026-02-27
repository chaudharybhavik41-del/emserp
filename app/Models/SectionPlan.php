<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'bom_id',
        'item_id',
        'section_profile',
        'grade',
        'name',
        'remarks',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function bars()
    {
        return $this->hasMany(SectionPlanBar::class);
    }

    public function getTotalPlannedLengthMmAttribute(): int
    {
        return (int) $this->bars
            ->map(fn (SectionPlanBar $bar) => (int) $bar->length_mm * (int) $bar->quantity)
            ->sum();
    }

    public function getTotalPlannedLengthMetersAttribute(): float
    {
        return round($this->total_planned_length_mm / 1000, 3);
    }

    public function getTotalPlannedWeightKgAttribute(): ?float
    {
        if (! $this->item || ! $this->item->weight_per_meter) {
            return null;
        }

        return round($this->total_planned_length_meters * $this->item->weight_per_meter, 3);
    }
}
