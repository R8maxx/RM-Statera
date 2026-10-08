<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Plataforma\Enums\CapacidadPlataforma;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        // Quien administra la plataforma con permiso para ver la salud del
        // servicio (punto 55), **y con el segundo factor confirmado**, como
        // `/plataforma`: Horizon enseña los payloads de los trabajos y deja
        // reintentarlos o borrarlos. Antes era la lista vacía de la plantilla.
        Gate::define('viewHorizon', fn (?User $user = null): bool => $user?->puedeEnPlataforma(CapacidadPlataforma::SaludVer) === true
            && (! config('seguridad.exigir_dos_factores') || $user->dosFactoresConfirmado()));
    }
}
