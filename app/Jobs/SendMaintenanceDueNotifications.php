<?php

namespace App\Jobs;

use App\Models\MachineMaintenancePlan;
use App\Models\User;
use App\Notifications\MaintenanceDueNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendMaintenanceDueNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Pull candidates in a reasonable window; per-plan alert_days_before is handled by ->isDue().
        $windowDays = (int) config('machinery.maintenance_alert_window_days', 60);
        if ($windowDays <= 0) {
            $windowDays = 60;
        }

        $plans = MachineMaintenancePlan::query()
            ->active()
            ->whereNotNull('next_scheduled_date')
            ->whereDate('next_scheduled_date', '<=', now()->addDays($windowDays))
            ->with('machine')
            ->get();

        $plansChecked  = $plans->count();
        $plansNotified = 0;
        $usersNotified = 0;

        foreach ($plans as $plan) {
            if (! $plan->isDue()) {
                continue;
            }

            $userIds = $plan->alert_user_ids ?? [];
            if (! is_array($userIds)) {
                $userIds = [];
            }

            $userIds = array_values(array_filter(array_map('intval', $userIds)));

            if (empty($userIds)) {
                // Fallback: maintenance team (users who can create maintenance logs).
                try {
                    $userIds = User::permission('machinery.maintenance_log.create')
                        ->pluck('id')
                        ->toArray();
                } catch (\Throwable $e) {
                    $userIds = [];
                }
            }

            if (empty($userIds)) {
                continue;
            }

            $users = User::query()
                ->whereIn('id', $userIds)
                ->where('is_active', true)
                ->get();

            if ($users->isEmpty()) {
                continue;
            }

            Notification::send($users, new MaintenanceDueNotification($plan));

            $plansNotified++;
            $usersNotified += $users->count();
        }

        Log::info('Maintenance due notifications processed', [
            'plans_checked'  => $plansChecked,
            'plans_notified' => $plansNotified,
            'users_notified' => $usersNotified,
        ]);
    }
}
