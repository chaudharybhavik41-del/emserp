<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrCandidate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_candidates';

    protected $fillable = [
        'company_id',
        'candidate_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'alternate_phone',
        'current_location',
        'position_applied',
        'current_company',
        'current_designation',
        'total_experience_months',
        'notice_period_days',
        'current_ctc',
        'expected_ctc',
        'source',
        'status',
        'interview_date',
        'skills',
        'remarks',
        'resume_path',
        'resume_file_name',
        'resume_file_size',
        'resume_mime_type',
        'converted_hr_employee_id',
        'converted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'interview_date' => 'date',
        'converted_at' => 'datetime',
        'current_ctc' => 'decimal:2',
        'expected_ctc' => 'decimal:2',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getExperienceLabelAttribute(): string
    {
        $months = (int) ($this->total_experience_months ?? 0);
        $yearsPart = intdiv($months, 12);
        $monthsPart = $months % 12;

        if ($yearsPart === 0 && $monthsPart === 0) {
            return 'Fresher';
        }

        $parts = [];

        if ($yearsPart > 0) {
            $parts[] = $yearsPart.' yr'.($yearsPart === 1 ? '' : 's');
        }

        if ($monthsPart > 0) {
            $parts[] = $monthsPart.' mo';
        }

        return implode(' ', $parts);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function convertedEmployee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'converted_hr_employee_id');
    }

    public function getIsConvertedAttribute(): bool
    {
        return ! is_null($this->converted_hr_employee_id);
    }

    public static function statusOptions(): array
    {
        return [
            'new' => 'New',
            'screening' => 'Screening',
            'shortlisted' => 'Shortlisted',
            'interview_scheduled' => 'Interview Scheduled',
            'interviewed' => 'Interviewed',
            'offered' => 'Offered',
            'joined' => 'Joined',
            'on_hold' => 'On Hold',
            'rejected' => 'Rejected',
        ];
    }

    public static function generateCandidateCode(): string
    {
        $year = now()->format('Y');
        $prefix = 'CAN-'.$year.'-';

        $lastCode = static::query()
            ->where('candidate_code', 'like', $prefix.'%')
            ->orderByDesc('candidate_code')
            ->value('candidate_code');

        $lastSequence = 0;

        if (is_string($lastCode) && preg_match('/(\d+)$/', $lastCode, $matches) === 1) {
            $lastSequence = (int) $matches[1];
        }

        return $prefix.str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }
}
