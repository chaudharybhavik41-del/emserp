<?php

namespace App\Providers;

use App\Models\Accounting\Voucher;
use App\Observers\Accounting\VoucherTdsCertificateObserver;
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
        
    }

    protected $policies = [
    Party::class => PartyPolicy::class,
];

}
