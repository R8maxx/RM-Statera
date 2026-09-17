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
     * @param  ?string  $cache  Sufijo de la clave de caché, para los recursos acotados a un padre.
     * @return array<string, mixed>
     */
    protected function tabla(Recurso $recurso, Request $request, ?string $cache = null): array
    {
        $resultado = (new ConsultaRecurso($recurso))->ejecutar($request);

        return [
            // La clave distingue la caché de cada recurso: sin ella, dos tablas
            // distintas compartirían la definición de la primera que se visitó.
            'recurso' => Inertia::once(fn () => $recurso->definicion())
                ->as($this->claveDeCache($recurso, $cache)),
            'filas' => $resultado['filas'],
            'meta' => $resultado['meta'],
        ];
    }

    /**
     * La clave con la que el cliente cachea la definición.
     *
     * Por defecto es la del recurso, que es lo correcto mientras el recurso sea
     * de nivel superior: su definición no cambia entre visitas.
     *
     * **Un recurso acotado a un padre necesita algo más**, y no es teórico: la
     * checklist de una auditoría emite en su definición la URL de la acción
     * masiva y las opciones de sus filtros, y las dos llevan el id de la
     * auditoría dentro. Con la clave pelada, el cliente reclama la prop al
     * navegar de una auditoría a otra —existe en la página actual y la clave
     * coincide—, se la omite el servidor y **copia el valor viejo**: la segunda
     * checklist se pintaría con la definición de la primera, sin error y sin
     * aviso.
     *
     * Por eso el sufijo va aquí y **no en `clave()`**. `clave()` es además el
     * nombre con el que la vista de columnas se guarda en el navegador y con el
     * que se nombra el CSV: hacerla dinámica guardaría una vista por auditoría
     * —crecimiento sin techo, y quien ordena sus columnas las pierde en la
     * siguiente— y metería dos puntos en el nombre del fichero. Las columnas de
     * una checklist son idénticas auditoría a auditoría: compartir la vista es lo
     * que se quiere.
     *
     * @template TModel of Model
     *
     * @param  Recurso<TModel>  $recurso
     */
    private function claveDeCache(Recurso $recurso, ?string $cache): string
    {
        $clave = "recurso:{$recurso->clave()}";

        return $cache === null ? $clave : "{$clave}:{$cache}";
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
