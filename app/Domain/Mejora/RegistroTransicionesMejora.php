<?php

declare(strict_types=1);

namespace App\Domain\Mejora;

use App\Domain\Mejora\Enums\EstadoMejora;
use App\Domain\Mejora\Models\Mejora;
use App\Domain\Mejora\Models\MejoraTransicion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Escribe el histórico de estados de una oportunidad de mejora.
 *
 * Mismo papel que sus hermanos de tareas, no conformidades y objetivos: si un
 * camino se saltara este registro habría mejoras cuyo «¿desde cuándo?» no se
 * puede contestar, que es lo que prohíbe el invariante 7.
 */
final class RegistroTransicionesMejora
{
    public function registrar(
        Mejora $mejora,
        ?EstadoMejora $anterior,
        EstadoMejora $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): MejoraTransicion {
        return MejoraTransicion::query()->create([
            'organizacion_id' => $mejora->organizacion_id,
            'mejora_id' => $mejora->id,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
            'usuario_id' => $usuario?->id,
            'nota' => $nota,
            'created_at' => Carbon::now(),
        ]);
    }
}
