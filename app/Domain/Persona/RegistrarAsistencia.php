<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\Asistencia;
use App\Domain\Persona\Models\Persona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Apunta quién fue convocado a una acción formativa y quién asistió.
 *
 * **Se guarda la convocatoria entera de una vez**, como la checklist de una
 * persona y la lista de subtareas: marcar veinte asistencias de una sesión es un
 * solo gesto, y una ruta por persona convertiría el registro en veinte peticiones
 * y veinte oportunidades de dejarlo a medias.
 *
 * **Convocar y asistir son dos cosas distintas.** Quien no está en la lista no fue
 * convocado; quien está con `asistio = false` fue convocado y no fue, y ése es el
 * que un auditor pregunta. Sin esa diferencia, «formación impartida al 100 % de
 * los convocados» saldría siempre.
 *
 * **Idempotente**: volver a guardar la misma convocatoria no duplica filas, porque
 * la pivote lleva índice único sobre `(accion_formativa_id, persona_id)`.
 */
final class RegistrarAsistencia
{
    /**
     * @param  array<int, bool>  $convocadas  persona_id => asistió
     */
    public function __invoke(AccionFormativa $accion, array $convocadas): void
    {
        DB::transaction(function () use ($accion, $convocadas): void {
            /*
             * Por el modelo y no por los ids a pelo: así pasa por el scope de
             * organización, que es lo que impide apuntar a la sesión de un cliente
             * la asistencia de la plantilla de otro pasando ids a mano.
             */
            $validas = Persona::query()
                ->whereIn('id', array_keys($convocadas))
                ->pluck('id')
                ->all();

            foreach ($validas as $personaId) {
                Asistencia::query()->updateOrCreate(
                    ['accion_formativa_id' => $accion->id, 'persona_id' => $personaId],
                    [
                        'organizacion_id' => $accion->organizacion_id,
                        'asistio' => $convocadas[$personaId],
                        'registrada_en' => Carbon::now(),
                    ],
                );
            }

            // Quien sale de la lista deja de estar convocado, que no es lo mismo
            // que haber faltado: se borra la fila en vez de marcarla a `false`.
            $accion->asistencias()->whereNotIn('persona_id', $validas === [] ? [0] : $validas)->delete();
        });
    }
}
