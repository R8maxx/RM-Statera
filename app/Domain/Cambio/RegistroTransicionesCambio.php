<?php

declare(strict_types=1);

namespace App\Domain\Cambio;

use App\Domain\Cambio\Enums\EstadoCambio;
use App\Domain\Cambio\Models\CambioSgsi;
use App\Domain\Cambio\Models\CambioSgsiTransicion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Escribe el histórico de estados de un cambio del SGSI.
 *
 * Mismo papel que sus hermanos de mejoras y objetivos: si un camino se saltara
 * este registro habría cambios cuyo «¿desde cuándo?» no se puede contestar, que
 * es lo que prohíbe el invariante 7.
 */
final class RegistroTransicionesCambio
{
    public function registrar(
        CambioSgsi $cambio,
        ?EstadoCambio $anterior,
        EstadoCambio $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): CambioSgsiTransicion {
        return CambioSgsiTransicion::query()->create([
            'organizacion_id' => $cambio->organizacion_id,
            'cambio_sgsi_id' => $cambio->id,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
            'usuario_id' => $usuario?->id,
            'nota' => $nota,
            'created_at' => Carbon::now(),
        ]);
    }
}
