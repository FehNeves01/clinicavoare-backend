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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configura o Passport para não executar migrations automaticamente
        Passport::ignoreRoutes();

        // Configura a expiração dos tokens de acesso para 24 horas
        Passport::tokensExpireIn(now()->addHours(24));

        // Configura a expiração dos refresh tokens para 30 dias
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}
