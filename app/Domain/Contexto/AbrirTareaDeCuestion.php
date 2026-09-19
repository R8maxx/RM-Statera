<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea la tarea que sale de una cuestión del contexto y la deja vinculada.
 *
 * Vive aquí y no en `Domain\Tarea` por la dirección de la dependencia, igual que
 * `AbrirAccionCorrectiva`: el contexto sabe de tareas y el plan de acción no tiene
 * por qué saber del contexto.
 *
 * **El origen se pone, no se pregunta**, y éste es el único camino que produce
 * tareas con `OrigenTarea::Contexto`. Una debilidad que se convierte en una tarea
 * marcada «iniciativa propia» pierde lo único que explicaba por qué se está
 * haciendo, que es justo lo que ese campo existe para contestar.
 *
 * **No ata ningún segundo vínculo**, a diferencia de la acción correctiva. Allí
 * hacía falta porque una tarea colgada sólo de la no conformidad no aparecía en
 * `implantacion_tarea` y el plan de adecuación imprimía «sin trabajo planificado»
 * sobre una medida que sí lo tenía. Aquí no hay medida detrás **por construcción**:
 * una cuestión del contexto no es un requisito de ningún marco, y forzarla contra
 * una implantación arbitraria es exactamente el vicio que `OrigenTarea::Propia`
 * existe para evitar. La consecuencia, declarada: el coste de esta tarea no entra
 * en el presupuesto del plan de adecuación, porque ese plan presupuesta medidas.
 *
 * Todo en una transacción: una tarea creada y sin vincular sería trabajo suelto que
 * nadie relacionaría con la cuestión que lo motivó.
 */
final class AbrirTareaDeCuestion
{
    public function __construct(private readonly CrearTarea $crearTarea) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(CuestionContexto $cuestion, array $atributos, ?User $autor = null): Tarea
    {
        return DB::transaction(function () use ($cuestion, $atributos, $autor): Tarea {
            $tarea = ($this->crearTarea)([
                ...$atributos,
                'origen' => OrigenTarea::Contexto->value,
                'estado' => EstadoTarea::Pendiente->value,
            ], $autor);

            $cuestion->tareas()->syncWithoutDetaching([
                $tarea->id => [
                    'organizacion_id' => $cuestion->organizacion_id,
                    'vinculada_por_id' => $autor?->id,
                ],
            ]);

            return $tarea->refresh();
        });
    }

    /** Ata una tarea que ya existía, desde la ficha de la cuestión. */
    public function vincular(CuestionContexto $cuestion, Tarea $tarea, ?User $usuario = null): void
    {
        $cuestion->tareas()->syncWithoutDetaching([
            $tarea->id => [
                'organizacion_id' => $cuestion->organizacion_id,
                'vinculada_por_id' => $usuario?->id,
            ],
        ]);
    }

    public function desvincular(CuestionContexto $cuestion, Tarea $tarea): void
    {
        $cuestion->tareas()->detach($tarea->id);
    }
}
