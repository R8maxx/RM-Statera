<?php

declare(strict_types=1);

namespace App\Domain\Objetivo;

use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Models\Objetivo;
use App\Domain\Objetivo\Models\ObjetivoTransicion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Escribe el histórico de estados de un objetivo de seguridad.
 *
 * Mismo papel que `RegistroTransicionesNoConformidad`, y por el mismo motivo: si
 * un camino se saltara este registro habría objetivos cuyo «¿desde cuándo?» no se
 * puede contestar, que es lo que prohíbe el invariante 7. El alta también deja
 * fila —con `estado_anterior` nulo—, porque «cuándo se propuso» y «cuándo se
 * firmó» son dos preguntas distintas y la segunda es la que importa.
 */
final class RegistroTransicionesObjetivo
{
    public function registrar(
        Objetivo $objetivo,
        ?EstadoObjetivo $anterior,
        EstadoObjetivo $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): ObjetivoTransicion {
        return ObjetivoTransicion::query()->create([
            'organizacion_id' => $objetivo->organizacion_id,
            'objetivo_id' => $objetivo->id,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
            'usuario_id' => $usuario?->id,
            'nota' => $nota,
            'created_at' => Carbon::now(),
        ]);
    }
}
