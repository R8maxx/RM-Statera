<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Domain\Organizacion\ContextoOrganizacion;

/**
 * La organización contra la que se valida: la del contexto, y no la de la
 * cuenta que hace la petición.
 *
 * Hasta el punto 44 eran siempre la misma. Desde que quien administra la
 * plataforma puede entrar como soporte en un cliente, o ser de una
 * organización y mirar otra, ya no: la frontera es la del contexto, que es la
 * que siguen las tres capas de aislamiento. Validar contra la de la cuenta
 * acotaría el `exists` a otro tenant.
 *
 * `idObligatorio()` y no `id()`: un `where` con nulo es un `whereNull`, y
 * encontraría justo las filas sin organización.
 */
trait DeLaOrganizacionActiva
{
    protected function organizacionActiva(): int
    {
        return app(ContextoOrganizacion::class)->idObligatorio();
    }
}
