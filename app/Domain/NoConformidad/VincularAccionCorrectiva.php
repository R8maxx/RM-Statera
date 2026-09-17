<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad;

use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\VincularTarea;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Une una tarea con la no conformidad que trata, y —cuando la hay— con la medida
 * a la que apunta.
 *
 * **El doble vínculo es el motivo entero de que esta clase exista.**
 * `Implantacion::sinTrabajo()` mira `implantacion_tarea`, así que una acción
 * correctiva colgada sólo de la no conformidad no está ahí: el plan de adecuación
 * imprimiría «sin trabajo planificado» sobre una medida que sí lo tiene, en la
 * tabla que la dirección mira seguro. Un documento que se contradice con el
 * registro es peor que un documento incompleto.
 *
 * Tres precisiones que conviene dejar escritas:
 *
 * 1. **Vive aquí y no en el controlador.** Si sólo lo hiciera el formulario de
 *    alta, la tarea que alguien vincule más tarde desde la ficha no tendría el
 *    segundo vínculo y el falso positivo volvería por la otra puerta.
 *
 * 2. **Cubre una parte de los casos, no todos.** El camino existe cuando la no
 *    conformidad viene de un hallazgo **con punto de checklist**: ahí hay medida
 *    detrás. Una no conformidad suelta, o de un hallazgo que no cuelga de ninguna
 *    medida —«el programa de auditoría no está definido»—, no tiene a qué
 *    apuntar, y forzarla contra una implantación arbitraria sería el vicio que
 *    `OrigenTarea::Propia` existe para evitar.
 *
 * 3. **No depende del estado de la auditoría.** Escribir en `implantacion_tarea`
 *    no pasa por el trigger de inmutabilidad —que blinda la checklist y los
 *    hallazgos, no lo que cuelga de ellos—, y es lo correcto: las no conformidades
 *    se tratan **después** de cerrar la auditoría, que es cuando se sabe qué hubo.
 *    Pero es justo donde uno espera un error, así que hay test.
 *
 * Y una cuarta, sobre lo que **no** hace: ninguna de las cifras que cuentan tareas
 * se mueve al vincular, porque todas cuentan filas de `tareas` y esto no crea
 * ninguna. Lo que sube es el total del plan de adecuación, porque trabajo que era
 * invisible pasa a estar presupuestado — que es exactamente lo que se quiere.
 * `Coste::total()` sigue contando cada tarea una vez aunque cubra tres medidas.
 */
final class VincularAccionCorrectiva
{
    public function __construct(private readonly VincularTarea $vincularTarea) {}

    public function vincular(NoConformidad $noConformidad, Tarea $tarea, ?User $usuario = null): void
    {
        DB::transaction(function () use ($noConformidad, $tarea, $usuario): void {
            // Idempotente: volver a vincular lo mismo no es un error del usuario.
            $noConformidad->tareas()->syncWithoutDetaching([
                $tarea->id => [
                    'organizacion_id' => $noConformidad->organizacion_id,
                    'vinculada_por_id' => $usuario?->id,
                    'created_at' => Carbon::now(),
                ],
            ]);

            $implantacion = $this->medidaAfectada($noConformidad);

            if ($implantacion instanceof Implantacion) {
                $this->vincularTarea->vincular($tarea, $implantacion, $usuario);
            }
        });
    }

    /**
     * Suelta la acción correctiva de la no conformidad.
     *
     * **Y deja puesto el vínculo con la medida**, a propósito. No hay forma de
     * saber si lo escribió esto o una persona desde la ficha de la implantación,
     * y quitarlo a ciegas borraría trabajo que alguien planificó a mano. Además,
     * mientras la tarea siga abierta y apuntando a la medida, «tiene trabajo
     * planificado» es verdad: el vínculo no miente por haberse quedado.
     */
    public function desvincular(NoConformidad $noConformidad, Tarea $tarea): void
    {
        $noConformidad->tareas()->detach($tarea->id);
    }

    /**
     * La medida a la que apunta esta no conformidad, si apunta a alguna.
     *
     * Hallazgo → punto de la checklist → implantación. Los tres eslabones son
     * opcionales y el resultado es nulo en cuanto falte uno; ver el punto 2 de la
     * cabecera.
     */
    private function medidaAfectada(NoConformidad $noConformidad): ?Implantacion
    {
        return $noConformidad->hallazgo?->punto?->implantacion;
    }
}
