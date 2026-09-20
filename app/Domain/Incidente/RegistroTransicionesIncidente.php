<?php

declare(strict_types=1);

namespace App\Domain\Incidente;

use App\Domain\Incidente\Enums\EstadoIncidente;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Incidente\Models\IncidenteTransicion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Escribe el histórico de estados de un incidente.
 *
 * Mismo papel que sus hermanos de tareas, no conformidades, objetivos y mejoras.
 * Aquí pesa más que en ninguno: **la pregunta del auditor no es «¿está cerrado?»,
 * es «¿cuánto se tardó en contenerlo?»**, y sin estas filas no hay de dónde
 * sacarla.
 */
final class RegistroTransicionesIncidente
{
    public function registrar(
        Incidente $incidente,
        ?EstadoIncidente $anterior,
        EstadoIncidente $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): IncidenteTransicion {
        return IncidenteTransicion::query()->create([
            'organizacion_id' => $incidente->organizacion_id,
            'incidente_id' => $incidente->id,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
            'usuario_id' => $usuario?->id,
            'nota' => $nota,
            'created_at' => Carbon::now(),
        ]);
    }
}
