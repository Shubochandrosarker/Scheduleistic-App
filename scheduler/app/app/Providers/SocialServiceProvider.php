<?php

namespace App\Providers;

use App\Social\ProviderManager;
use Illuminate\Support\ServiceProvider;

class SocialServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ProviderManager::class, function () {
            return new ProviderManager(
                fake: (bool) config('scheduleistic.fake_publishing', false),
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
