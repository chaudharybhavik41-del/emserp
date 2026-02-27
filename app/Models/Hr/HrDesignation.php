<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Department;

class HrDesignation extends Model
{
    protected $table = 'hr_designations';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'short_name',
        'description',
        'department_id',
        'hr_grade_id',
        'level',
        'min_salary',
        'max_salary',
        'is_supervisory',
        'is_managerial',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'level' => 'integer',
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
        'is_supervisory' => 'boolean',
        'is_managerial' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ==================== RELATIONSHIPS ====================

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(HrGrade::class, 'hr_grade_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'hr_designation_id');
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

    public function scopeManagerial($query)
    {
        return $query->where('is_managerial', true);
    }

    public function scopeSupervisory($query)
    {
        return $query->where('is_supervisory', true);
    }

    // ==================== ACCESSORS ====================

    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    public function getSalaryRangeAttribute(): string
    {
        if ($this->min_salary && $this->max_salary) {
            return '₹' . number_format($this->min_salary) . ' - ₹' . number_format($this->max_salary);
        }
        return '-';
    }
}
