<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachineMaintenanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'maintenance_plan_id',
        'machine_assignment_id',
        'contractor_party_id',
        'worker_user_id',

        'log_number',
        'maintenance_type',
        'scheduled_date',
        'started_at',
        'completed_at',

        'meter_reading_before',
        'meter_reading_after',

        'work_description',
        'work_performed',
        'findings',
        'recommendations',

        'technician_user_ids',
        'external_vendor_party_id',

        'status',
        'priority',

        'labor_cost',
        'parts_cost',
        'external_service_cost',
        'total_cost',
        'downtime_hours',

        'remarks',
        'created_by',
        'completed_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'technician_user_ids' => 'array',
        'labor_cost' => 'decimal:2',
        'parts_cost' => 'decimal:2',
        'external_service_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'downtime_hours' => 'decimal:2',
        'meter_reading_before' => 'decimal:2',
        'meter_reading_after' => 'decimal:2',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function plan()
    {
        return $this->belongsTo(MachineMaintenancePlan::class, 'maintenance_plan_id');
    }

    public function assignment()
    {
        return $this->belongsTo(MachineAssignment::class, 'machine_assignment_id');
    }

    public function contractor()
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_user_id');
    }

    public function spares()
    {
        // FIX: foreign key is machine_maintenance_log_id
        return $this->hasMany(MachineSpareConsumption::class, 'machine_maintenance_log_id');
    }

    public static function generateLogNumber(): string
    {
        $prefix = 'MLG-' . now()->format('Y') . '-';
        $last = static::where('log_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('log_number');

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = ((int)$m[1]) + 1;
        }

        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }


    /**
     * Roll-up spare consumption costs into this maintenance log.
     *
     * parts_cost = SUM(machine_spare_consumptions.total_cost)
     * total_cost = labor_cost + external_service_cost + parts_cost
     */
    public function updatePartsCost(): void
    {
        $partsCost = (float) $this->spares()->sum('total_cost');

        $this->parts_cost = $partsCost;

        $labor = (float) ($this->labor_cost ?? 0);
        $external = (float) ($this->external_service_cost ?? 0);

        $this->total_cost = $labor + $external + $partsCost;

        $this->save();
    }
}
