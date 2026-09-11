<?php

declare(strict_types=1);

namespace App\Domain\Organizacion\Jobs;

use App\Domain\Organizacion\ContextoOrganizacion;
use Closure;

/**
 * Fija la organización activa alrededor de un job en cola.
 *
 * En una cola **no hay petición HTTP**, así que no corre
 * `EstablecerContextoOrganizacion` y no hay usuario autenticado del que deducir
 * el tenant. Sin contexto, el scope global de Eloquent no devuelve ninguna fila
 * y la política de RLS deniega por defecto: el job no rompe, sencillamente no ve
 * nada, que es la peor forma de fallar que existe.
 *
 * Es el equivalente en cola del middleware HTTP, y vive aquí y no dentro de cada
 * job porque vienen dieciocho módulos más y todos van a necesitarlo.
 *
 * **El job tiene que pasar el id como escalar.** Con `SerializesModels`, el
 * modelo se vuelve a consultar al deserializar —antes de que corra ningún
 * middleware—, el scope devuelve cero filas y el job muere con un
 * `ModelNotFoundException` que no menciona la palabra «organización» por ninguna
 * parte.
 *
 * Y OJO: `failed()` **no** pasa por aquí. Un job que escriba en su `failed()`
 * tiene que fijar el contexto por su cuenta.
 */
final readonly class ConContextoDeOrganizacion
{
    public function __construct(private int $organizacionId) {}

    public function handle(object $job, Closure $siguiente): void
    {
        app(ContextoOrganizacion::class)->paraOrganizacion(
            $this->organizacionId,
            static fn () => $siguiente($job),
        );
    }
}
