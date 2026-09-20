<?php

declare(strict_types=1);

namespace App\Domain\Objetivo;

use App\Domain\Objetivo\Models\Objetivo;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Une una tarea con el objetivo que hace avanzar (6.2, planificación a).
 *
 * **Sin doble vínculo, y es una diferencia con `VincularAccionCorrectiva` que
 * conviene dejar escrita.** Allí la tarea se ata además a `implantacion_tarea`
 * porque `Implantacion::sinTrabajo()` mira esa pivote y el plan de adecuación
 * imprimiría «sin trabajo planificado» sobre una medida que sí lo tiene. Aquí no
 * hay medida detrás **por construcción**: un objetivo de seguridad no cuelga de
 * ningún requisito, y forzarlo contra una implantación arbitraria sería el vicio
 * que `OrigenTarea::Propia` existe para evitar. Mismo reparto que `cuestion_tarea`
 * en el § 4.1.
 *
 * **La consecuencia, declarada:** el coste de una actuación de objetivo **no entra
 * en el presupuesto del plan de adecuación**, porque ese plan presupuesta medidas
 * del Anexo II. Lo que se ve en la ficha del objetivo es su propio coste.
 *
 * Idempotente, como todas las de su familia.
 */
final class VincularActuacion
{
    public function vincular(Objetivo $objetivo, Tarea $tarea, ?User $usuario = null): void
    {
        $objetivo->tareas()->syncWithoutDetaching([
            $tarea->id => [
                'organizacion_id' => $objetivo->organizacion_id,
                'vinculada_por_id' => $usuario?->id,
                'created_at' => Carbon::now(),
            ],
        ]);
    }

    /**
     * Suelta la actuación del objetivo, y **no borra la tarea**.
     *
     * Es trabajo real con su histórico y puede que con su coste: desvincular dice
     * «esto ya no es lo que hace avanzar este objetivo», no «esto no se hace».
     */
    public function desvincular(Objetivo $objetivo, Tarea $tarea): void
    {
        $objetivo->tareas()->detach($tarea->id);
    }
}
