<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployeeExperience extends Model
{
    protected $table = 'hr_employee_experiences';

    protected $fillable = [
        'hr_employee_id',
        'company_name',
        'designation',
        'department',
        'from_date',
        'to_date',
        'is_current',
        'experience_months',
        'location',
        'reporting_to',
        'job_responsibilities',
        'last_ctc',
        'reason_for_leaving',
        'reference_name',
        'reference_contact',
        'reference_email',
        'reference_verified',
        'relieving_letter_path',
        'experience_letter_path',
        'is_verified',
        'remarks',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'is_current' => 'boolean',
        'last_ctc' => 'decimal:2',
        'is_verified' => 'boolean',
        'reference_verified' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    // ==================== SCOPES ====================

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('from_date');
    }

    // ==================== ACCESSORS ====================

    public function getDurationAttribute(): string
    {
        $end = $this->to_date ?? now();
        $years = $this->from_date->diffInYears($end);
        $months = $this->from_date->diffInMonths($end) % 12;

        $parts = [];
        if ($years > 0) $parts[] = "{$years} year" . ($years > 1 ? 's' : '');
        if ($months > 0) $parts[] = "{$months} month" . ($months > 1 ? 's' : '');

        return implode(' ', $parts) ?: '< 1 month';
    }

    public function getDurationInMonthsAttribute(): int
    {
        $end = $this->to_date ?? now();
        return $this->from_date->diffInMonths($end);
    }

    public function getPeriodAttribute(): string
    {
        $from = $this->from_date->format('M Y');
        $to = $this->is_current ? 'Present' : ($this->to_date ? $this->to_date->format('M Y') : '-');
        return "{$from} - {$to}";
    }

    // Backward-compatible aliases for older controller/view code.
    public function getJobDescriptionAttribute()
    {
        return $this->job_responsibilities;
    }

    public function setJobDescriptionAttribute($value): void
    {
        $this->attributes['job_responsibilities'] = $value;
    }

    public function getDocumentPathAttribute()
    {
        return $this->experience_letter_path;
    }

    public function setDocumentPathAttribute($value): void
    {
        $this->attributes['experience_letter_path'] = $value;
    }
}
