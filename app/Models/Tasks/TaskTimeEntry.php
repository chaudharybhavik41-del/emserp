<?php

namespace App\Models\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'description',
        'started_at',
        'ended_at',
        'duration_minutes',
        'is_billable',
        'hourly_rate',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'is_billable' => 'boolean',
        'hourly_rate' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (TaskTimeEntry $entry) {
            // Calculate duration if not set
            if (!$entry->duration_minutes && $entry->started_at && $entry->ended_at) {
                $entry->duration_minutes = $entry->started_at->diffInMinutes($entry->ended_at);
            }
        });

        static::created(function (TaskTimeEntry $entry) {
            // Update task logged minutes
            $entry->task->increment('logged_minutes', $entry->duration_minutes);

            // Log activity
            $entry->task->logActivity('time_logged', null, null, $entry->duration_minutes, [
                'time_entry_id' => $entry->id,
                'duration_formatted' => $entry->duration_formatted,
            ]);
        });

        static::deleted(function (TaskTimeEntry $entry) {
            // Decrease task logged minutes
            $entry->task->decrement('logged_minutes', $entry->duration_minutes);
        });
    }

    // Relationships
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    public function scopeInDateRange($query, $start, $end)
    {
        return $query->whereBetween('started_at', [$start, $end]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('started_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('started_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    // Helpers
    public function getDurationHoursAttribute(): float
    {
        return round($this->duration_minutes / 60, 2);
    }

    public function getDurationFormattedAttribute(): string
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0) {
            return $minutes > 0 
                ? "{$hours}h {$minutes}m" 
                : "{$hours}h";
        }

        return "{$minutes}m";
    }

    public function getBillableAmountAttribute(): ?float
    {
        if (!$this->is_billable || !$this->hourly_rate) {
            return null;
        }

        return round(($this->duration_minutes / 60) * $this->hourly_rate, 2);
    }

    public function isRunning(): bool
    {
        return $this->started_at && !$this->ended_at;
    }

    public function stop(): void
    {
        if ($this->isRunning()) {
            $this->update([
                'ended_at' => now(),
                'duration_minutes' => $this->started_at->diffInMinutes(now()),
            ]);
        }
    }
}
