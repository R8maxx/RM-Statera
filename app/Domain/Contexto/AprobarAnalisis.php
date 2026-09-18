<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Enums\EstadoAnalisis;
use App\Domain\Contexto\Excepciones\AnalisisNoAprobable;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Firma el análisis del contexto: lo congela, lo numera y jubila al anterior.
 *
 * Es el acto que da sentido al módulo. Hasta aquí el DAFO es una lista que se
 * edita; a partir de aquí es lo que la organización declaró que era su situación,
 * con fecha y con firma, y la cláusula 9.3 puede preguntarle qué ha cambiado desde
 * la vez anterior.
 *
 * **La instantánea se calcula ANTES de tocar la fila**, y no es una preferencia de
 * estilo: leer el contexto entero dentro de la misma transacción que lo firma
 * alarga el bloqueo sin ganar nada, y el orden importa por la otra punta — es
 * exactamente el error que se cometió en `CerrarAuditoria`, donde congelar después
 * de marcar el estado hacía que el trigger bloqueara el propio congelado con un
 * mensaje que hablaba de la checklist y no del orden.
 *
 * **Y el anterior se jubila antes de aprobar el nuevo.** El índice único parcial
 * `WHERE estado = 'aprobado'` deja uno como mucho: al revés, la inserción choca
 * con un error de índice que no menciona la palabra «vigente».
 *
 * Las tres comprobaciones viven aquí y no en el `FormRequest` porque valen igual
 * para un importador o para el seeder. La del clima la repite un `CHECK` de la
 * base, y eso es a propósito: la base es la que no se puede saltar y ésta es la que
 * sale por pantalla al lado del campo.
 */
final class AprobarAnalisis
{
    public function __construct(private readonly InstantaneaContexto $instantanea) {}

    public function __invoke(AnalisisContexto $analisis, User $aprobador): AnalisisContexto
    {
        if ($analisis->estado !== EstadoAnalisis::Borrador) {
            throw AnalisisNoAprobable::porEstado($analisis->estado);
        }

        if ($analisis->clima_pertinente === null || trim((string) $analisis->clima_justificacion) === '') {
            throw AnalisisNoAprobable::porElClima();
        }

        if (CuestionContexto::query()->vigentes()->doesntExist()) {
            throw AnalisisNoAprobable::porEstarVacio();
        }

        $instantanea = $this->instantanea->para($analisis);

        return DB::transaction(function () use ($analisis, $aprobador, $instantanea): AnalisisContexto {
            $anterior = AnalisisContexto::query()->vigente()->first();

            $anterior?->update(['estado' => EstadoAnalisis::Obsoleto]);

            $analisis->update([
                'numero' => $this->siguienteNumero(),
                'estado' => EstadoAnalisis::Aprobado,
                'instantanea' => $instantanea,
                'aprobado_por_id' => $aprobador->id,
                'aprobado_en' => Carbon::now(),
            ]);

            return $analisis->refresh();
        });
    }

    /**
     * El siguiente de la organización, contando sobre el máximo.
     *
     * Sobre el máximo y no sobre el total, como los códigos: si algún día se borra
     * una fila, el total daría un número ya usado — y un número repetido en dos
     * actas es peor que un salto.
     */
    private function siguienteNumero(): int
    {
        $ultimo = AnalisisContexto::query()->max('numero');

        return ((int) $ultimo) + 1;
    }
}
