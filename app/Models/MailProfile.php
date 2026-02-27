<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailProfile extends Model
{
    protected $fillable = [
        'code',
        'name',
        'company_id',
        'department_id',
        'from_name',
        'from_email',
        'reply_to',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'is_default',
        'is_active',
        'last_tested_at',
        'last_test_success',
        'last_test_error',
    ];

    protected $casts = [
        'is_default'        => 'boolean',
        'is_active'         => 'boolean',
        'last_tested_at'    => 'datetime',
        'last_test_success' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // Encrypt/decrypt password transparently
    public function setSmtpPasswordAttribute($value): void
    {
        $this->attributes['smtp_password'] = $value ? encrypt($value) : null;
    }

    public function getSmtpPasswordAttribute($value): ?string
    {
        return $value ? decrypt($value) : null;
    }
}
