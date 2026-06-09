<?php

namespace App\Providers;

use App\Listeners\SyncTeamPlan;
use App\Models\Team;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

class AppServiceProvider extends ServiceProvider
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
        // Billing is per-organization: the Jetstream "team" is the Stripe customer.
        Cashier::useCustomerModel(Team::class);

        // Keep plan/usage limits in sync with Stripe subscription changes.
        Event::listen(WebhookReceived::class, SyncTeamPlan::class);
    }
}
