<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MachineMaintenancePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'plan_code',
        'plan_name',
        'maintenance_type',
        'frequency_type',
        'frequency_value',
        'checklist_items',
        'estimated_duration_hours',
        'requires_shutdown',
        'alert_days_before',
        'alert_user_ids',
        'is_active',
        'last_executed_date',
        'next_scheduled_date',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'checklist_items' => 'array',
        'alert_user_ids' => 'array',
        'last_executed_date' => 'date',
        'next_scheduled_date' => 'date',
        'estimated_duration_hours' => 'decimal:2',
        'requires_shutdown' => 'boolean',
        'is_active' => 'boolean',
        'alert_days_before' => 'integer',
        'frequency_value' => 'integer',
    ];

    // Relationships

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MachineMaintenanceLog::class, 'maintenance_plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDueSoon($query, $days = 7)
    {
        return $query->active()
            ->whereNotNull('next_scheduled_date')
            ->whereDate('next_scheduled_date', '<=', now()->addDays($days));
    }

    public function scopeOverdue($query)
    {
        return $query->active()
            ->whereNotNull('next_scheduled_date')
            ->whereDate('next_scheduled_date', '<', now());
    }

    // Helpers

    public function isDue(): bool
    {
        if (!$this->is_active || !$this->next_scheduled_date) {
            return false;
        }

        return $this->next_scheduled_date->lte(now()->addDays($this->alert_days_before));
    }

    public function isOverdue(): bool
    {
        if (!$this->is_active || !$this->next_scheduled_date) {
            return false;
        }

        return $this->next_scheduled_date->lt(now());
    }

    public function calculateNextDate(): ?\Carbon\Carbon
    {
        $baseDate = $this->last_executed_date ?? now();

        return match($this->frequency_type) {
            'daily' => $baseDate->copy()->addDays($this->frequency_value),
            'weekly' => $baseDate->copy()->addWeeks($this->frequency_value),
            'monthly' => $baseDate->copy()->addMonths($this->frequency_value),
            'quarterly' => $baseDate->copy()->addMonths($this->frequency_value * 3),
            'half_yearly' => $baseDate->copy()->addMonths($this->frequency_value * 6),
            'yearly' => $baseDate->copy()->addYears($this->frequency_value),
            'operating_hours' => null, // Calculated based on machine hours
            default => null,
        };
    }

    public static function generateCode(): string
    {
        $year = now()->format('Y');
        $prefix = 'MTP-' . $year . '-';

        $lastPlan = self::where('plan_code', 'like', $prefix . '%')
            ->orderByDesc('plan_code')
            ->first();

        if ($lastPlan) {
            $lastNum = (int) substr($lastPlan->plan_code, -4);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
}
