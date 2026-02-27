<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\MachineMaintenancePlan;
use Illuminate\Support\Carbon;

class MaintenanceScheduleService
{
    /**
     * Scheduling source-of-truth rules:
     * 1) Plan-level next_scheduled_date is authoritative per plan.
     * 2) Machine-level next_maintenance_due_date is a cached summary:
     *    - min(active plan next_scheduled_date) if any
     *    - else fallback to machine last_maintenance_date + maintenance_frequency_days
     */
    public function syncMachineNextDueDate(int $machineId): void
    {
        $machine = Machine::find($machineId);
        if (! $machine) {
            return;
        }

        $planNext = MachineMaintenancePlan::query()
            ->where('machine_id', $machineId)
            ->where('is_active', true)
            ->whereNotNull('next_scheduled_date')
            ->min('next_scheduled_date');

        if ($planNext) {
            $machine->next_maintenance_due_date = Carbon::parse($planNext)->toDateString();
            $machine->save();
            return;
        }

        $freqDays = (int) ($machine->maintenance_frequency_days ?? 0);

        if ($freqDays <= 0) {
            // No plans and no machine-level frequency configured
            $machine->next_maintenance_due_date = null;
            $machine->save();
            return;
        }

        $base = $machine->last_maintenance_date ? Carbon::parse($machine->last_maintenance_date) : now();
        $machine->next_maintenance_due_date = $base->copy()->addDays($freqDays)->toDateString();
        $machine->save();
    }
}
