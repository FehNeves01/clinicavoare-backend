<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! $this->app->environment('local')) {
            return;
        }

        $providers = [
            'Laravel\\Telescope\\TelescopeServiceProvider',
            'App\\Providers\\TelescopeServiceProvider',
        ];

        foreach ($providers as $provider) {
            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configura o Passport para não executar migrations automaticamente
        Passport::ignoreRoutes();
    }
}
