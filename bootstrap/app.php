<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // you can add api routes later if you want:
        // api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register Spatie permission middleware aliases (v6+ uses Middleware namespace)
        $middleware->alias([
            'role'               => RoleMiddleware::class,
            'permission'         => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
  
  	->withSchedule(function (Schedule $schedule): void {
        // Maintenance due job (already exists in your codebase)
        $schedule->job(new \App\Jobs\SendMaintenanceDueNotifications)
            ->dailyAt('09:00');

        // Calibration due job (from the patch we created)
        $schedule->job(new \App\Jobs\SendCalibrationDueNotifications)
            ->dailyAt('09:10');

        // Daily ERP Digest (yesterday's summary)
        $schedule->job(new \App\Jobs\SendDailyDigestJob)
            ->dailyAt('09:20');

        // CRM overdue follow-up reminders.
        $schedule->job(new \App\Jobs\SendCrmFollowUpReminderNotifications)
            ->dailyAt(config('crm.follow_up_reminders.daily_at', '09:30'));

        // Recurring task generation.
        $schedule->command('tasks:run-recurring')
            ->everyFifteenMinutes();

        // Task overdue automation.
        $schedule->command('tasks:run-overdue-automation')
            ->dailyAt('08:45');

        // Notification inbox retention cleanup.
        $schedule->command('notifications:prune-read')
            ->dailyAt(config('erp_notifications.retention.daily_at', '03:45'));

        // Keep push subscriptions healthy.
        $schedule->command('pwa:subscriptions:prune')
            ->dailyAt('03:30');

        // Fallback queue worker loop (use Supervisor/systemd in production for primary worker).
        if (config('pwa.push.worker_fallback_enabled', true)) {
            $connection = (string) config('pwa.push.worker_fallback_connection', config('queue.default', 'database'));
            $queues = (string) config('pwa.push.worker_fallback_queues', 'default');

            $schedule->command("queue:work {$connection} --queue={$queues} --stop-when-empty --max-time=50 --sleep=1 --tries=3")
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground();
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
