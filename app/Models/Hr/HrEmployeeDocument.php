<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class HrEmployeeDocument extends Model
{
    protected $table = 'hr_employee_documents';

    protected $fillable = [
        'hr_employee_id',
        'document_type',
        'document_number',
        'document_name',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'issuing_authority',
        'issue_date',
        'expiry_date',
        'is_verified',
        'verified_by',
        'verified_at',
        'remarks',
        'uploaded_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'file_size' => 'integer',
    ];

    // ==================== RELATIONSHIPS ====================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ==================== SCOPES ====================

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now())
            ->where('expiry_date', '<=', now()->addDays($days));
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    // ==================== ACCESSORS ====================

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expiry_date && 
               $this->expiry_date->isFuture() && 
               $this->expiry_date->diffInDays(now()) <= 30;
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        return $this->expiry_date ? now()->diffInDays($this->expiry_date, false) : null;
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    public function getDocumentTypeLabelsAttribute(): array
    {
        return [
            'aadhar' => 'Aadhar Card',
            'pan' => 'PAN Card',
            'passport' => 'Passport',
            'voter_id' => 'Voter ID',
            'driving_license' => 'Driving License',
            'education' => 'Education Certificate',
            'experience' => 'Experience Letter',
            'offer_letter' => 'Offer Letter',
            'relieving_letter' => 'Relieving Letter',
            'payslip' => 'Payslip',
            'bank_statement' => 'Bank Statement',
            'address_proof' => 'Address Proof',
            'photo' => 'Photograph',
            'signature' => 'Signature',
            'other' => 'Other',
        ];
    }

    public function getDocumentTypeLabelAttribute(): string
    {
        return $this->document_type_labels[$this->document_type] ?? $this->document_type;
    }

    // ==================== METHODS ====================

    public function verify(int $verifiedBy): bool
    {
        $this->is_verified = true;
        $this->verified_by = $verifiedBy;
        $this->verified_at = now();
        return $this->save();
    }

    public function unverify(): bool
    {
        $this->is_verified = false;
        $this->verified_by = null;
        $this->verified_at = null;
        return $this->save();
    }

    // Backward-compatible aliases for older code paths
    public function getIssuedDateAttribute()
    {
        return $this->issue_date;
    }

    public function setIssuedDateAttribute($value): void
    {
        $this->attributes['issue_date'] = $value;
    }

    public function getIssuedByAttribute()
    {
        return $this->issuing_authority;
    }

    public function setIssuedByAttribute($value): void
    {
        $this->attributes['issuing_authority'] = $value;
    }

    public function getCreatedByAttribute()
    {
        return $this->uploaded_by;
    }

    public function setCreatedByAttribute($value): void
    {
        $this->attributes['uploaded_by'] = $value;
    }
}
