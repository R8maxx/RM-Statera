<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\BiaServicioTransicion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Escribe el histórico de estados de un BIA.
 *
 * Mismo papel que `RegistroTransicionesIncidente` y sus hermanos de tareas, no
 * conformidades, objetivos y mejoras: la pregunta del auditor no es «¿está
 * aprobado?», es «¿desde cuándo, y quién lo aprobó?».
 */
final class RegistroTransicionesBia
{
    public function registrar(
        BiaServicio $bia,
        ?EstadoBia $anterior,
        EstadoBia $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): BiaServicioTransicion {
        return BiaServicioTransicion::query()->create([
            'organizacion_id' => $bia->organizacion_id,
            'bia_servicio_id' => $bia->id,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
            'usuario_id' => $usuario?->id,
            'nota' => $nota,
            'created_at' => Carbon::now(),
        ]);
    }
}
