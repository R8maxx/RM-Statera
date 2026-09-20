<?php

declare(strict_types=1);

namespace App\Domain\Objetivo;

use App\Domain\Metrica\Models\Indicador;
use App\Domain\Objetivo\Models\Objetivo;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Une un objetivo con los indicadores que lo evalúan (6.2, planificación e).
 *
 * **Se vincula, no se crea.** El indicador ya existe o se define en su módulo, y
 * fabricar uno desde aquí produciría indicadores sin periodicidad, sin
 * responsable y sin método —que es justo lo que la cláusula 9.1 pide y lo que
 * este vínculo existe para poder enseñar—. Es el mismo criterio con el que una
 * cuestión del contexto se vincula a un riesgo y no lo genera.
 *
 * **Y no hay doble vínculo**, a diferencia de la acción correctiva del § 4.13.
 * Allí hacía falta porque el plan de adecuación imprimía «sin trabajo
 * planificado» sobre una medida que sí lo tenía; aquí no hay ninguna cifra que
 * mienta por no atar un segundo extremo, porque un indicador no cuelga de ninguna
 * implantación.
 *
 * Idempotente: volver a vincular lo mismo no es un error de quien lo hace.
 */
final class VincularIndicador
{
    public function vincular(Objetivo $objetivo, Indicador $indicador, ?User $usuario = null): void
    {
        $objetivo->indicadores()->syncWithoutDetaching([
            $indicador->id => [
                'organizacion_id' => $objetivo->organizacion_id,
                'vinculado_por_id' => $usuario?->id,
                'created_at' => Carbon::now(),
            ],
        ]);
    }

    /**
     * Suelta el indicador del objetivo.
     *
     * **No retira el indicador ni borra su serie.** Un indicador sirve a varios
     * objetivos y sigue midiendo lo que mide; lo que se deshace aquí es que ése
     * sea el criterio con el que se juzga este objetivo.
     */
    public function desvincular(Objetivo $objetivo, Indicador $indicador): void
    {
        $objetivo->indicadores()->detach($indicador->id);
    }
}
