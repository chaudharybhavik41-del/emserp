<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineBreakdownRegister extends Model
{
    use HasFactory;

    protected $table = 'machine_breakdown_register';

    protected $fillable = [
        'machine_id',
        'breakdown_number',
        'reported_at',
        'reported_by',
        'breakdown_type',
        'severity',
        'project_id',
        'operation_during_breakdown',
        'problem_description',
        'immediate_action_taken',
        'acknowledged_at',
        'acknowledged_by',
        'maintenance_team_assigned',
        'repair_started_at',
        'repair_completed_at',
        'root_cause',
        'corrective_action',
        'preventive_measures',
        'production_loss_hours',
        'estimated_cost',
        'status',
        'maintenance_log_id',
        'remarks',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'repair_started_at' => 'datetime',
        'repair_completed_at' => 'datetime',
        'maintenance_team_assigned' => 'array',
        'production_loss_hours' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
    ];

    // Relationships

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function maintenanceLog(): BelongsTo
    {
        return $this->belongsTo(MachineMaintenanceLog::class, 'maintenance_log_id');
    }

    // Scopes

    public function scopeReported($query)
    {
        return $query->where('status', 'reported');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    // Helpers

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function getResponseTimeHours(): ?float
    {
        if (!$this->acknowledged_at) {
            return null;
        }

        return $this->reported_at->diffInHours($this->acknowledged_at, true);
    }

    public function getRepairTimeHours(): ?float
    {
        if (!$this->repair_started_at || !$this->repair_completed_at) {
            return null;
        }

        return $this->repair_started_at->diffInHours($this->repair_completed_at, true);
    }

    public static function generateNumber(): string
    {
        $year = now()->format('y');
        $prefix = 'BRK-' . $year . '-';

        $lastBreakdown = self::where('breakdown_number', 'like', $prefix . '%')
            ->orderByDesc('breakdown_number')
            ->first();

        if ($lastBreakdown) {
            $lastNum = (int) substr($lastBreakdown->breakdown_number, -4);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
}