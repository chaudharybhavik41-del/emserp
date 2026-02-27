<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrGrade extends Model
{
    protected $table = 'hr_grades';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'level',
        'min_basic',
        'max_basic',
        'min_gross',
        'max_gross',
        'hra_percent',
        'transport_allowance',
        'special_allowance_percent',
        'annual_leave_days',
        'sick_leave_days',
        'casual_leave_days',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'level' => 'integer',
        'min_basic' => 'decimal:2',
        'max_basic' => 'decimal:2',
        'min_gross' => 'decimal:2',
        'max_gross' => 'decimal:2',
        'hra_percent' => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'special_allowance_percent' => 'decimal:2',
        'annual_leave_days' => 'integer',
        'sick_leave_days' => 'integer',
        'casual_leave_days' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ==================== RELATIONSHIPS ====================

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'hr_grade_id');
    }

    public function designations(): HasMany
    {
        return $this->hasMany(HrDesignation::class, 'hr_grade_id');
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('level')->orderBy('sort_order')->orderBy('name');
    }

    // ==================== ACCESSORS ====================

    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    public function getBasicRangeAttribute(): string
    {
        if ($this->min_basic && $this->max_basic) {
            return '₹' . number_format($this->min_basic) . ' - ₹' . number_format($this->max_basic);
        }
        return '-';
    }

    public function getGrossRangeAttribute(): string
    {
        if ($this->min_gross && $this->max_gross) {
            return '₹' . number_format($this->min_gross) . ' - ₹' . number_format($this->max_gross);
        }
        return '-';
    }

    public function getTotalLeaveDaysAttribute(): int
    {
        return $this->annual_leave_days + $this->sick_leave_days + $this->casual_leave_days;
    }
}
