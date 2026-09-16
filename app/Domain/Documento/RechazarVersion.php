<?php

declare(strict_types=1);

namespace App\Domain\Documento;

use App\Domain\Documento\Enums\EstadoDocumental;
use App\Domain\Documento\Excepciones\AprobacionNoPermitida;
use App\Domain\Documento\Models\DocumentoVersion;

/**
 * La dirección lo ha mirado y ha dicho que no.
 *
 * **Exige motivo**, y la regla vive aquí y no sólo en el `FormRequest` porque
 * vale igual para un importador o para un comando. Es el mismo criterio que
 * separa `hecha` de `descartada` en una tarea: descartar es una decisión, y una
 * decisión sin motivo escrito no se puede auditar — quien recoja el documento
 * dentro de tres semanas tiene que saber qué hay que cambiar.
 *
 * No congela nada ni consume número: la versión rechazada sigue siendo el
 * borrador vivo, y retomarla es volver a `borrador` y seguir escribiendo. Un
 * número gastado en algo que nunca se entregó dejaría un hueco en la numeración
 * que el auditor preguntaría.
 */
final class RechazarVersion
{
    /**
     * @throws AprobacionNoPermitida
     */
    public function __invoke(DocumentoVersion $version, string $motivo): DocumentoVersion
    {
        if (trim($motivo) === '') {
            throw AprobacionNoPermitida::sinMotivo();
        }

        if (! $version->estado->permite(EstadoDocumental::Rechazado)) {
            throw AprobacionNoPermitida::porTransicion($version, EstadoDocumental::Rechazado);
        }

        $version->fill([
            'estado' => EstadoDocumental::Rechazado->value,
            'motivo_rechazo' => trim($motivo),
            /*
             * Se retira la firma si la había. Es el caso de una aprobación cuya
             * generación falló y que la dirección decide no reintentar: dejar la
             * firma puesta en algo rechazado sería el peor registro posible —el
             * documento diría a la vez que se aprobó y que no—.
             */
            'aprobada_por_id' => null,
            'aprobada_en' => null,
            'nota_aprobacion' => null,
            'fecha_proxima_revision' => null,
        ])->save();

        return $version->refresh();
    }
}
