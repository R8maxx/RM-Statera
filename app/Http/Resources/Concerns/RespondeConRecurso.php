<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Http\Resources\ConsultaRecurso;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Recurso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * El puente entre un `Recurso` y una página de Inertia.
 *
 * Reparte los props en dos mitades porque cambian a ritmos distintos: la
 * definición no varía al paginar ni al ordenar, así que viaja como prop `once` y
 * el cliente la conserva; las filas y la meta se recargan con
 * `router.reload({ only: ['filas', 'meta'] })`.
 */
trait RespondeConRecurso
{
    /**
     * @template TModel of Model
     *
     * @param  Recurso<TModel>  $recurso
     * @return array<string, mixed>
     */
    protected function tabla(Recurso $recurso, Request $request): array
    {
        $resultado = (new ConsultaRecurso($recurso))->ejecutar($request);

        return [
            // La clave distingue la caché de cada recurso: sin ella, dos tablas
            // distintas compartirían la definición de la primera que se visitó.
            'recurso' => Inertia::once(fn () => $recurso->definicion())
                ->as("recurso:{$recurso->clave()}"),
            'filas' => $resultado['filas'],
            'meta' => $resultado['meta'],
        ];
    }

    /**
     * Sólo los filtros, para las pantallas que no son tablas.
     *
     * El tablero y el calendario filtran igual que la tabla —misma declaración,
     * mismos scopes, misma barra— pero no paginan ni ordenan, así que no tienen
     * nada que hacer con `filas` ni con `meta`.
     *
     * **Sin `Inertia::once()`, y a propósito.** `tabla()` cachea la definición
     * bajo `recurso:{clave}` y esa clave la comparten las tres pantallas del
     * mismo recurso: si el tablero emitiera ahí su lista recortada, ganaría la
     * primera pantalla visitada y la otra vería filtros que no le sirven. La
     * lista pesa poco y, como no va en el `only` de las recargas parciales, se
     * queda en el cliente igual.
     *
     * @template TModel of Model
     *
     * @param  Recurso<TModel>  $recurso
     * @param  list<string>  $excepto  claves que esta pantalla no ofrece
     * @return array<string, mixed>
     */
    protected function filtros(Recurso $recurso, Request $request, array $excepto = []): array
    {
        $filtros = array_values(array_filter(
            $recurso->filtros(),
            static fn (Filtro $filtro): bool => ! in_array($filtro->clave, $excepto, true),
        ));

        return [
            'filtros' => array_map(
                static fn (Filtro $filtro): Filtro => $filtro->resolver(),
                $filtros,
            ),
            'filtrosAplicados' => (new ConsultaRecurso($recurso))->filtrosAplicados($request, $filtros),
        ];
    }
}
