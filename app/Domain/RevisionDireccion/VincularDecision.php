<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion;

use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Une una tarea con la revisión que la decidió (9.3.3).
 *
 * **Estas tareas son la entrada a) de la revisión siguiente**, y es lo que hace
 * que la serie de actas signifique algo: sin ellas cada revisión empieza de cero y
 * las decisiones de la anterior no se comprueban nunca. Es el motivo por el que
 * las salidas son tareas y no un campo de texto.
 *
 * **Se puede vincular sobre una revisión aprobada**, y conviene decirlo porque es
 * justo donde uno espera un error: escribir en `revision_tarea` no pasa por el
 * trigger de inmutabilidad —que blinda el acta, no lo que cuelga de ella—. Es el
 * mismo caso que la acción correctiva de una auditoría cerrada, y es lo correcto
 * por el mismo motivo: las decisiones se ejecutan **después** de firmar el acta, y
 * alguna aparece semanas más tarde. Lo que el acta congela es lo que se decidió
 * ese día; lo que cuelga después sigue siendo trazable por la pivote.
 *
 * **Sin doble vínculo**, como en objetivos, mejoras y contexto: una decisión de la
 * dirección no cuelga de ninguna medida del Anexo II por construcción.
 *
 * Idempotente, como todas las de su familia.
 */
final class VincularDecision
{
    public function vincular(RevisionDireccion $revision, Tarea $tarea, ?User $usuario = null): void
    {
        $revision->tareas()->syncWithoutDetaching([
            $tarea->id => [
                'organizacion_id' => $revision->organizacion_id,
                'vinculada_por_id' => $usuario?->id,
                'created_at' => Carbon::now(),
            ],
        ]);
    }

    /**
     * Suelta la decisión de la revisión, y **no borra la tarea**.
     *
     * Ojo con esto sobre un acta aprobada: lo que se suelta es el vínculo, no lo
     * que el acta dice. El acta congeló las entradas, no las salidas, así que
     * desvincular aquí **sí cambia** lo que la revisión siguiente verá como
     * «acciones previas». Queda declarado, y es el motivo por el que la interfaz
     * sólo lo ofrece a quien puede gestionar el módulo.
     */
    public function desvincular(RevisionDireccion $revision, Tarea $tarea): void
    {
        $revision->tareas()->detach($tarea->id);
    }
}
