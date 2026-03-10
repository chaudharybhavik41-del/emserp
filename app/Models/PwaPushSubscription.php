<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PwaPushSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'last_seen_at',
        'last_push_attempt_at',
        'last_push_success_at',
        'last_push_status',
        'last_push_error',
        'push_attempt_count',
        'disabled_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_push_attempt_at' => 'datetime',
        'last_push_success_at' => 'datetime',
        'push_attempt_count' => 'integer',
        'disabled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
