<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Continuidad\Models\PruebaContinuidadTransicion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Escribe el histórico de estados de una prueba de continuidad.
 *
 * Mismo papel que `RegistroTransicionesBia` y `RegistroTransicionesIncidente`:
 * la pregunta del auditor no es «¿se probó?», es «¿cuándo, y con qué
 * resultado?».
 */
final class RegistroTransicionesPrueba
{
    public function registrar(
        PruebaContinuidad $prueba,
        ?EstadoPrueba $anterior,
        EstadoPrueba $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): PruebaContinuidadTransicion {
        return PruebaContinuidadTransicion::query()->create([
            'organizacion_id' => $prueba->organizacion_id,
            'prueba_continuidad_id' => $prueba->id,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
            'usuario_id' => $usuario?->id,
            'nota' => $nota,
            'created_at' => Carbon::now(),
        ]);
    }
}
