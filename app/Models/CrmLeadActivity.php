<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmLeadActivity extends Model
{
    use HasFactory;

    protected $table = 'crm_lead_activities';

    protected $fillable = [
        'lead_id',
        'user_id',
        'type',
        'subject',
        'description',
        'due_at',
        'done_at',
        'outcome',
    ];

    protected $casts = [
        'due_at'           => 'datetime',
        'done_at'          => 'datetime',
        'last_reminded_at' => 'datetime',
        'last_escalated_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getSlaDueAtAttribute(): ?CarbonInterface
    {
        if ($this->due_at) {
            return $this->due_at;
        }

        $hours = (int) config('crm.activity_slas.' . ($this->type ?? 'task'), 24);
        $base = $this->created_at ?? now();

        return $base->copy()->addHours(max(1, $hours));
    }

    public function getSlaStatusAttribute(): string
    {
        if ($this->done_at) {
            return $this->done_at->lessThanOrEqualTo($this->sla_due_at ?? $this->done_at)
                ? 'completed_on_time'
                : 'completed_late';
        }

        if (! $this->sla_due_at) {
            return 'open';
        }

        if ($this->sla_due_at->isPast()) {
            return 'breached';
        }

        if ($this->sla_due_at->lessThanOrEqualTo(now()->addHours((int) config('crm.lead_scoring.due_soon_hours', 24)))) {
            return 'due_soon';
        }

        return 'open';
    }
}
