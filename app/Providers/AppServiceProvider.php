<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Documento\Render\GotenbergHttp;
use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Una sola instancia por petición, por comando o por job: es el estado
         * que mantiene sincronizadas las tres capas de aislamiento.
         *
         * `scoped` y no `singleton` por el worker de la cola, que es un proceso
         * largo que atiende jobs de organizaciones distintas: con `singleton`,
         * la organización del job A seguiría puesta al empezar el job B. Un
         * `scoped` se descarta entre jobs, así que el punto de partida vuelve a
         * ser el de denegar por defecto.
         *
         * No basta con esto: `set_config(..., false)` es de SESIÓN y vive en la
         * conexión de PostgreSQL, que el worker reutiliza. Quien la devuelve a
         * su sitio es el `finally` de `ContextoOrganizacion::paraOrganizacion()`.
         * Hacen falta las dos cosas.
         */
        $this->app->scoped(ContextoOrganizacion::class);

        /*
         * El cliente de Gotenberg va tras una interfaz para que los tests de
         * contenido —que son casi todos— corran sin levantar el contenedor, y
         * para poder afirmar sobre la solicitud que se le mandó. Probar el HTML
         * es probar el documento; Gotenberg sólo es la impresora.
         *
         * El timeout va por debajo del del job (300 s) y por encima del de la
         * API de Gotenberg (120 s): esa cadena tiene que quedar en ese orden o
         * los fallos aparecen donde no está la causa.
         */
        $this->app->bind(ClienteGotenberg::class, fn (): GotenbergHttp => new GotenbergHttp(
            (string) config('services.gotenberg.url'),
            (int) config('services.gotenberg.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
