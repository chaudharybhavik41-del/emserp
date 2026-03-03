<?php

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pwa:push-test {--user=} {--title=ERP Push Test} {--message=This is a test browser push from EMS Infra ERP.} {--url=/notifications}', function (NotificationService $notificationService) {
    $userId = (int) $this->option('user');

    /** @var User|null $user */
    $user = $userId > 0
        ? User::query()->find($userId)
        : User::query()->where('is_active', true)->orderBy('id')->first();

    if (!$user) {
        $this->error('No target user found. Pass --user=<id>.');
        return 1;
    }

    $title = (string) $this->option('title');
    $message = (string) $this->option('message');
    $url = (string) $this->option('url');

    $notificationService->sendSystemAlertToUser(
        $user,
        $title,
        $message,
        ['source' => 'artisan:pwa:push-test'],
        $url ?: '/notifications',
        'info',
        'pwa_push_test'
    );

    $this->info("Queued test alert for user #{$user->id} ({$user->email}).");
    $this->line('If push transport is configured, browser push will be attempted in queue processing.');

    return 0;
})->purpose('Queue a test in-app + browser push alert for a user');
