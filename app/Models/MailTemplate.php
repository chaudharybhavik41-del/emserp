<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'mail_profile_id',
        'subject',
        'body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function mailProfile(): BelongsTo
    {
        return $this->belongsTo(MailProfile::class);
    }
}
