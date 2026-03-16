<?php

namespace App\Providers;

use App\Listeners\RespectNotificationPreferences;
use App\Listeners\QueueWebPushForDatabaseNotification;
use App\Models\Accounting\Voucher;
use App\Observers\Accounting\VoucherTdsCertificateObserver;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use App\Models\Party;
use App\Policies\PartyPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\SettingsService::class, function () {
            return new \App\Services\SettingsService();
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

        // Respect per-user notification channel preferences before delivery.
        Event::listen(NotificationSending::class, [RespectNotificationPreferences::class, 'handle']);

        // Bridge all database notifications to web push queue.
        Event::listen(NotificationSent::class, [QueueWebPushForDatabaseNotification::class, 'handle']);
        
    }

    protected $policies = [
    Party::class => PartyPolicy::class,
];

}
