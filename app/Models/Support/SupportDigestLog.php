<?php

namespace App\Models\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportDigestLog extends Model
{
    use HasFactory;

    protected $table = 'support_digest_logs';

    protected $guarded = [];

    protected $casts = [
        'digest_date' => 'date',
        'sent_at'     => 'datetime',
        'recipients'  => 'array',
        'summary'     => 'array',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
