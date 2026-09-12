<?php

declare(strict_types=1);

namespace App\Domain\Tarea;

use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Models\TareaTransicion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Escribe el histórico de estados de una tarea.
 *
 * Mismo papel que `RegistroTransiciones` en implantaciones, y por el mismo
 * motivo: si un camino se saltara este registro habría tareas cuyo «¿desde
 * cuándo?» no se puede contestar, que es lo que prohíbe el invariante 7. El alta
 * también deja fila —con `estado_anterior` nulo—, porque «cuándo se abrió» es
 * parte de la misma pregunta.
 */
final class RegistroTransicionesTarea
{
    public function registrar(
        Tarea $tarea,
        ?EstadoTarea $anterior,
        EstadoTarea $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): TareaTransicion {
        return TareaTransicion::query()->create([
            'organizacion_id' => $tarea->organizacion_id,
            'tarea_id' => $tarea->id,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
            'usuario_id' => $usuario?->id,
            'nota' => $nota,
            'created_at' => Carbon::now(),
        ]);
    }
}
