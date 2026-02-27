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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();


