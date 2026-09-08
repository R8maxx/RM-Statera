<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Http\Resources\ConsultaRecurso;
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
}
