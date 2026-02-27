<?php

namespace App\Models\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportDigestRecipient extends Model
{
    use HasFactory;

    protected $table = 'support_digest_recipients';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Resolve the email address for this recipient.
     */
    public function getResolvedEmailAttribute(): ?string
    {
        if (!empty($this->email)) {
            return $this->email;
        }

        return $this->user?->email;
    }
}
