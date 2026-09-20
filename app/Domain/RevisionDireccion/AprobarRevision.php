<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion;

use App\Domain\RevisionDireccion\Enums\EstadoRevision;
use App\Domain\RevisionDireccion\Excepciones\RevisionNoAprobable;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Firma el acta: congela las siete entradas de la 9.3.2 y vuelve la fila inmutable.
 *
 * Es el acto que da sentido al módulo, igual que `AprobarAnalisis` en el § 4.1.
 * Hasta aquí la revisión es una reunión que se está preparando; a partir de aquí
 * es lo que la dirección revisó y decidió, con fecha y firma.
 *
 * **Las entradas se recogen ANTES de tocar la fila.** No es una preferencia de
 * estilo: es exactamente el error que se cometió en `CerrarAuditoria`, donde
 * congelar después de marcar el estado hacía que el trigger de inmutabilidad
 * bloqueara el propio congelado con un mensaje que hablaba de la checklist y no
 * del orden. Aquí pasaría lo mismo, y el mensaje hablaría del acta.
 *
 * **Sólo se firma desde `en_curso`.** Una revisión `planificada` no se ha
 * celebrado, y firmar el acta de una reunión que no ha ocurrido es lo que la 9.3
 * existe para hacer imposible. La comprobación vive aquí y no en el `FormRequest`
 * porque vale igual para un importador o para el seeder.
 *
 * **Lo que no se comprueba es que las entradas digan algo.** Una organización
 * puede celebrar su primera revisión sin auditorías, sin no conformidades y sin
 * objetivos, y el acta lo dirá: recoger un cero es recoger la entrada. Exigir que
 * haya contenido convertiría la primera revisión en imposible, que es cuando más
 * falta hace.
 */
final class AprobarRevision
{
    public function __construct(private readonly EntradasRevision $entradas) {}

    /**
     * @throws RevisionNoAprobable
     */
    public function __invoke(RevisionDireccion $revision, User $aprobador): RevisionDireccion
    {
        if ($revision->estado !== EstadoRevision::EnCurso) {
            throw RevisionNoAprobable::porEstado($revision->estado);
        }

        $instantanea = $this->entradas->para($revision);

        return DB::transaction(function () use ($revision, $aprobador, $instantanea): RevisionDireccion {
            $revision->update([
                'estado' => EstadoRevision::Aprobada->value,
                'instantanea' => $instantanea,
                'aprobada_por_id' => $aprobador->id,
                'aprobada_en' => Carbon::now(),
            ]);

            return $revision->refresh();
        });
    }
}
