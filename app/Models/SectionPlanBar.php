<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionPlanBar extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_plan_id',
        'length_mm',
        'quantity',
        'remarks',
    ];

    public function plan()
    {
        return $this->belongsTo(SectionPlan::class, 'section_plan_id');
    }

    public function getTotalLengthMmAttribute(): int
    {
        return (int) $this->length_mm * (int) $this->quantity;
    }

    public function getTotalLengthMetersAttribute(): float
    {
        return round($this->total_length_mm / 1000, 3);
    }
}
