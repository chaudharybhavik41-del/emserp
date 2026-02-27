<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployeeQualification extends Model
{
    protected $table = 'hr_employee_qualifications';

    protected $fillable = [
        'hr_employee_id',
        'qualification_type',
        'degree_name',
        'specialization',
        'institution_name',
        'university_board',
        'year_of_passing',
        'percentage_cgpa',
        'grade_type',
        'roll_number',
        'certificate_path',
        'is_verified',
        'verified_by',
        'verified_at',
        'remarks',
    ];

    protected $casts = [
        'year_of_passing' => 'integer',
        'percentage_cgpa' => 'decimal:2',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
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

    public function scopeOfType($query, $type)
    {
        return $query->where('qualification_type', $type);
    }

    // ==================== ACCESSORS ====================

    public function getQualificationTypeLabelAttribute(): string
    {
        return match($this->qualification_type) {
            'ssc' => 'SSC / 10th',
            'hsc' => 'HSC / 12th',
            'diploma' => 'Diploma',
            'graduation' => 'Graduation',
            'post_graduation' => 'Post Graduation',
            'doctorate' => 'Doctorate (PhD)',
            'professional' => 'Professional Certification',
            'other' => 'Other',
            default => ucfirst($this->qualification_type ?? '-'),
        };
    }

    public function getFullQualificationAttribute(): string
    {
        $parts = [$this->degree_name];
        if ($this->specialization) {
            $parts[] = "({$this->specialization})";
        }
        return implode(' ', $parts);
    }

    // Backward-compatible aliases
    public function getQualificationNameAttribute()
    {
        return $this->degree_name;
    }

    public function setQualificationNameAttribute($value): void
    {
        $this->attributes['degree_name'] = $value;
    }

    public function getInstitutionAttribute()
    {
        return $this->institution_name;
    }

    public function setInstitutionAttribute($value): void
    {
        $this->attributes['institution_name'] = $value;
    }

    public function getBoardUniversityAttribute()
    {
        return $this->university_board;
    }

    public function setBoardUniversityAttribute($value): void
    {
        $this->attributes['university_board'] = $value;
    }

    public function getPercentageAttribute()
    {
        return $this->percentage_cgpa;
    }

    public function setPercentageAttribute($value): void
    {
        $this->attributes['percentage_cgpa'] = $value;
    }

    public function getDocumentPathAttribute()
    {
        return $this->certificate_path;
    }

    public function setDocumentPathAttribute($value): void
    {
        $this->attributes['certificate_path'] = $value;
    }
}
