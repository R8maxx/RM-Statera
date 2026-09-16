<?php

declare(strict_types=1);

namespace App\Domain\Tarea;

use App\Domain\Tarea\Models\Tarea;
use Illuminate\Support\Collection;

/**
 * El coste estimado de una tarea, en euros y escrito una sola vez.
 *
 * `coste_estimado` es `decimal(12,2)` y Eloquent lo entrega como cadena, así que
 * todo el que lo enseña tiene que decidir cómo se escribe. Estaba decidido dos
 * veces —la columna de la tabla y la ficha— y el plan de adecuación iba a ser la
 * tercera; es el mismo motivo por el que `Plazo` y `PrioridadTarea::tono()`
 * viven en el dominio y no en `TareaRecurso`.
 *
 * **Sumar tareas de varias implantaciones exige deduplicar.** `implantacion_tarea`
 * es N:M —«revisar la política de contraseñas» hace avanzar un control de ISO y
 * tres medidas del ENS a la vez—, así que sumar el coste de cada medida cuenta
 * la misma tarea tantas veces como medidas cubra: en un plan de adecuación, una
 * tarea de 1.800 € vinculada a tres medidas se presupuestaría a 5.400 € en el
 * documento que se le lleva a la dirección a pedir dinero. `total()` suma sobre
 * tareas distintas; `deLaTarea()` imputa a cada medida lo suyo. Los dos números
 * son correctos y **no cuadran entre sí**, y por eso el documento lo dice.
 */
final class Coste
{
    /** Lo que cuesta una tarea, ya escrito, o nulo si nadie lo ha estimado. */
    public static function deLaTarea(Tarea $tarea): ?string
    {
        return $tarea->coste_estimado === null
            ? null
            : self::escribir((float) $tarea->coste_estimado);
    }

    /**
     * La suma de un conjunto de tareas, contando cada una **una sola vez**.
     *
     * @param  Collection<int, Tarea>|list<Tarea>  $tareas
     */
    public static function total(Collection|array $tareas): float
    {
        return (float) (new Collection($tareas))
            ->filter(static fn (Tarea $tarea): bool => $tarea->coste_estimado !== null)
            ->unique(static fn (Tarea $tarea): int => $tarea->id)
            ->sum(static fn (Tarea $tarea): float => (float) $tarea->coste_estimado);
    }

    /**
     * Cuántas de ellas nadie ha estimado, que es el denominador de la suma.
     *
     * @param  Collection<int, Tarea>|list<Tarea>  $tareas
     */
    public static function sinEstimar(Collection|array $tareas): int
    {
        return (new Collection($tareas))
            ->unique(static fn (Tarea $tarea): int => $tarea->id)
            ->filter(static fn (Tarea $tarea): bool => $tarea->coste_estimado === null)
            ->count();
    }

    public static function escribir(float $euros): string
    {
        return number_format($euros, 2, ',', '.').' €';
    }
}
