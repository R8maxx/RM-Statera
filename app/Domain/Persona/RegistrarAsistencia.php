<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Persona\Enums\JustificacionAusencia;
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
 * **Quien faltó puede llevar justificación**, y va en la misma petición: es parte
 * de la misma convocatoria y se revisa en el mismo gesto. Lo que se normaliza
 * aquí y no en el llamador —vale igual para un importador—: quien asistió no
 * lleva ni justificación ni motivo, y el motivo sólo acompaña a la justificada.
 *
 * **Idempotente**: volver a guardar la misma convocatoria no duplica filas, porque
 * la pivote lleva índice único sobre `(accion_formativa_id, persona_id)`.
 */
final class RegistrarAsistencia
{
    /**
     * @param  array<int, bool>  $convocadas  persona_id => asistió
     * @param  array<int, array{ausencia: ?JustificacionAusencia, motivo: ?string}>  $ausencias  persona_id => por qué faltó
     */
    public function __invoke(AccionFormativa $accion, array $convocadas, array $ausencias = []): void
    {
        DB::transaction(function () use ($accion, $convocadas, $ausencias): void {
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
                $asistio = $convocadas[$personaId];
                $ausencia = $asistio ? null : ($ausencias[$personaId]['ausencia'] ?? null);
                $motivo = $ausencia === JustificacionAusencia::Justificada ? ($ausencias[$personaId]['motivo'] ?? null) : null;

                Asistencia::query()->updateOrCreate(
                    ['accion_formativa_id' => $accion->id, 'persona_id' => $personaId],
                    [
                        'organizacion_id' => $accion->organizacion_id,
                        'asistio' => $asistio,
                        'ausencia' => $ausencia,
                        'motivo_ausencia' => $motivo,
                        'registrada_en' => Carbon::now(),
                    ],
                );
            }

            // Quien sale de la lista deja de estar convocado, que no es lo mismo
            // que haber faltado: se borra la fila en vez de marcarla a `false`.
            // Una a una y no en bloque, para que cada baja deje su evento en la traza.
            $accion->asistencias()->whereNotIn('persona_id', $validas === [] ? [0] : $validas)->get()->each->delete();
        });
    }
}
