<?php

namespace App\Providers;

use App\Listeners\QueueWebPushForDatabaseNotification;
use App\Listeners\RespectNotificationPreferences;
use App\Models\Accounting\Voucher;
use App\Models\Party;
use App\Models\User;
use App\Observers\Accounting\VoucherTdsCertificateObserver;
use App\Observers\UserObserver;
use App\Policies\PartyPolicy;
use App\Services\Users\UserCreationAuditContext;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\SettingsService::class, function () {
            return new \App\Services\SettingsService;
        });

        $this->app->singleton(UserCreationAuditContext::class, function () {
            return new UserCreationAuditContext;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Phase 1.6 / DEV18: create payable-side TDS certificate tracking rows
        // when Purchase/Subcontractor vouchers are posted.
        Voucher::observe(VoucherTdsCertificateObserver::class);
        User::observe(UserObserver::class);

        // Respect per-user notification channel preferences before delivery.
        Event::listen(NotificationSending::class, [RespectNotificationPreferences::class, 'handle']);

        // Bridge all database notifications to web push queue.
        Event::listen(NotificationSent::class, [QueueWebPushForDatabaseNotification::class, 'handle']);

    }

    protected $policies = [
        Party::class => PartyPolicy::class,
    ];
}
