<?php

declare(strict_types=1);

namespace App\Domain\Implantacion;

use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Implantacion\Models\ImplantacionTransicion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Escribe el histórico de estados.
 *
 * Todo cambio de estado pasa por aquí, venga de una persona (CambiarEstado) o
 * del recálculo (GeneradorImplantaciones). Si un camino se saltara este registro
 * habría implantaciones cuyo "¿desde cuándo?" no se puede contestar, que es
 * exactamente lo que el invariante 7 prohíbe.
 */
final class RegistroTransiciones
{
    public function registrar(
        Implantacion $implantacion,
        ?EstadoImplantacion $anterior,
        EstadoImplantacion $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): ImplantacionTransicion {
        return ImplantacionTransicion::query()->create([
            'organizacion_id' => $implantacion->organizacion_id,
            'implantacion_id' => $implantacion->id,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
            'usuario_id' => $usuario?->id,
            'nota' => $nota,
            'created_at' => Carbon::now(),
        ]);
    }
}
