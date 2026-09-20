<?php

declare(strict_types=1);

namespace App\Domain\Mejora;

use App\Domain\Mejora\Models\Mejora;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Une una tarea con la mejora que materializa.
 *
 * **Sin doble vínculo**, como en objetivos y en el contexto, y a diferencia de la
 * acción correctiva del § 4.13: allí la tarea se ata además a `implantacion_tarea`
 * porque el plan de adecuación imprimiría «sin trabajo planificado» sobre una
 * medida que sí lo tiene. Aquí **puede haber medida detrás** —cuando la mejora
 * viene de un hallazgo con punto de checklist— y aun así **no se ata**, que es la
 * decisión que conviene dejar escrita:
 *
 * Una oportunidad de mejora **no incumple la medida**. El plan de adecuación lista
 * lo que falta por implantar, y una medida que ya está implantada y que además se
 * puede hacer mejor no está pendiente de nada. Atar el vínculo la metería en un
 * plan que presupuesta brechas, y esa es exactamente la clase de cifra inflada
 * que el § 4.13 tuvo que arreglar por el otro lado.
 *
 * **La consecuencia, declarada:** el coste de una actuación de mejora no entra en
 * el presupuesto del plan de adecuación. Lo que se ve es el coste de la mejora en
 * su ficha.
 *
 * Idempotente, como todas las de su familia.
 */
final class VincularActuacionDeMejora
{
    public function vincular(Mejora $mejora, Tarea $tarea, ?User $usuario = null): void
    {
        $mejora->tareas()->syncWithoutDetaching([
            $tarea->id => [
                'organizacion_id' => $mejora->organizacion_id,
                'vinculada_por_id' => $usuario?->id,
                'created_at' => Carbon::now(),
            ],
        ]);
    }

    /**
     * Suelta la actuación de la mejora, y **no borra la tarea**.
     *
     * Es trabajo real con su histórico: desvincular dice «esto ya no es lo que
     * materializa esta mejora», no «esto no se hace».
     */
    public function desvincular(Mejora $mejora, Tarea $tarea): void
    {
        $mejora->tareas()->detach($tarea->id);
    }
}
