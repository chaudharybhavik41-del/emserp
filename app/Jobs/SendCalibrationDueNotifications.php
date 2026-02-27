<?php

namespace App\Jobs;

use App\Models\MachineCalibrationRecord;
use App\Models\User;
use App\Notifications\CalibrationDueNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCalibrationDueNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $alertDays = config('machinery.calibration_alert_days', 15);

            // Overdue calibrations
            $overdueRecords = MachineCalibrationRecord::with('machine')
                ->overdue()
                ->get();

            // Due soon calibrations
            $dueSoonRecords = MachineCalibrationRecord::with('machine')
                ->dueSoon($alertDays)
                ->get();

            foreach ($overdueRecords as $record) {
                $this->notifyRecord($record, true);
            }

            foreach ($dueSoonRecords as $record) {
                $this->notifyRecord($record, false);
            }

            Log::info('Calibration due notifications processed', [
                'overdue_count' => $overdueRecords->count(),
                'due_soon_count' => $dueSoonRecords->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to process calibration due notifications', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify appropriate users for a given calibration record.
     */
    protected function notifyRecord(MachineCalibrationRecord $record, bool $overdue): void
    {
        $machine = $record->machine;

        if (!$machine) {
            return;
        }

        // Default: users who can view calibration module
        $users = User::permission('machinery.calibration.view')->get();

        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            try {
                $user->notify(new CalibrationDueNotification($record, $overdue));
            } catch (\Throwable $e) {
                Log::error('Failed to notify user about calibration', [
                    'user_id' => $user->id,
                    'record_id' => $record->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

