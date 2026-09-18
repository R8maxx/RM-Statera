<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Retira una cuestión o una parte interesada, con su motivo escrito.
 *
 * **Una sola clase para las dos**, y no por ahorro: la operación es literalmente
 * la misma —`analisis_baja_id` y `motivo_baja`— y dos copias del mismo cuerpo es
 * cómo se acaba con una que exige motivo y otra que no.
 *
 * **Retirar no es borrar, y exige motivo**, igual que descartar una tarea y anular
 * una no conformidad. Una amenaza que dejó de serlo es información: explica por
 * qué el análisis de este año tiene una cuestión menos que el del anterior, que es
 * exactamente lo que la revisión por la dirección va a preguntar. Borrar la fila
 * dejaría esa diferencia sin explicación y, peor, se llevaría por delante los
 * riesgos y las tareas que colgaban de ella.
 *
 * **Y no se retira contra el análisis vigente sino contra el borrador**, que se
 * estrena si no hay ninguno. Anotar la baja en el análisis ya firmado sería
 * cambiar lo que dice un documento aprobado, que es justo lo que el trigger
 * impide: aquí no reventaría —la escritura es sobre la cuestión, no sobre el
 * análisis— y por eso hay que decidirlo aquí.
 *
 * Reponer una cuestión retirada es alta nueva, con su código nuevo. Deshacer la
 * baja dejaría un hueco en el histórico que diría que nunca se retiró.
 */
final class RetirarDelAnalisis
{
    public function __construct(private readonly AnalisisEnCurso $analisis) {}

    public function cuestion(CuestionContexto $cuestion, string $motivo, ?User $usuario = null): CuestionContexto
    {
        return $this->retirar($cuestion, $motivo, $usuario);
    }

    public function parte(ParteInteresada $parte, string $motivo, ?User $usuario = null): ParteInteresada
    {
        return $this->retirar($parte, $motivo, $usuario);
    }

    /**
     * @template T of CuestionContexto|ParteInteresada
     *
     * @param  T  $registro
     * @return T
     */
    private function retirar(Model $registro, string $motivo, ?User $usuario): Model
    {
        return DB::transaction(function () use ($registro, $motivo, $usuario): Model {
            $borrador = $this->analisis->borradorObligatorio($usuario);

            $registro->update([
                'analisis_baja_id' => $borrador->id,
                'motivo_baja' => trim($motivo),
            ]);

            return $registro;
        });
    }
}
