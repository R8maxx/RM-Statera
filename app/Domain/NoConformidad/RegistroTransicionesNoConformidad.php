<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad;

use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\NoConformidad\Models\NoConformidadTransicion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Escribe el histórico de estados de una no conformidad.
 *
 * Mismo papel que `RegistroTransicionesTarea`, y por el mismo motivo: si un
 * camino se saltara este registro habría no conformidades cuyo «¿desde cuándo?»
 * no se puede contestar, que es lo que prohíbe el invariante 7. El alta también
 * deja fila —con `estado_anterior` nulo—, porque «cuándo se detectó» y «cuándo se
 * registró» no son lo mismo y las dos se preguntan.
 */
final class RegistroTransicionesNoConformidad
{
    public function registrar(
        NoConformidad $noConformidad,
        ?EstadoNoConformidad $anterior,
        EstadoNoConformidad $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): NoConformidadTransicion {
        return NoConformidadTransicion::query()->create([
            'organizacion_id' => $noConformidad->organizacion_id,
            'no_conformidad_id' => $noConformidad->id,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
            'usuario_id' => $usuario?->id,
            'nota' => $nota,
            'created_at' => Carbon::now(),
        ]);
    }
}
