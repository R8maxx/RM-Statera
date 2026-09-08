<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Una sola instancia por petición o por comando: es el estado que
        // mantiene sincronizadas las tres capas de aislamiento.
        $this->app->singleton(ContextoOrganizacion::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
