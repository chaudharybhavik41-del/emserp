<?php

namespace App\Providers;

use App\Models\Party;
use App\Models\Project;
use App\Observers\PartyObserver;
use App\Observers\ProjectAccountingObserver;
use Illuminate\Support\ServiceProvider;

class AccountingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Automatically create/update accounting accounts when Parties change
        Party::observe(PartyObserver::class);

        // Auto-create WIP→COGS draft voucher when a project is completed
        Project::observe(ProjectAccountingObserver::class);
    }
}
