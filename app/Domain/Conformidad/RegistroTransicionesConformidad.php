<?php

declare(strict_types=1);

namespace App\Domain\Conformidad;

use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Conformidad\Models\Conformidad;
use App\Domain\Conformidad\Models\ConformidadTransicion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Escribe el histórico de estados de una conformidad.
 *
 * Mismo papel que sus hermanos de mejoras, objetivos y no conformidades: si un
 * camino se saltara este registro, habría declaraciones cuyo «¿desde cuándo?» no
 * se puede contestar, que es lo que prohíbe el invariante 7.
 */
final class RegistroTransicionesConformidad
{
    public function registrar(
        Conformidad $conformidad,
        ?EstadoConformidad $anterior,
        EstadoConformidad $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): ConformidadTransicion {
        return ConformidadTransicion::query()->create([
            'organizacion_id' => $conformidad->organizacion_id,
            'conformidad_id' => $conformidad->id,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
            'usuario_id' => $usuario?->id,
            'nota' => $nota,
            'created_at' => Carbon::now(),
        ]);
    }
}
