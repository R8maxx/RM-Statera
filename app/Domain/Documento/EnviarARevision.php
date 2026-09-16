<?php

declare(strict_types=1);

namespace App\Domain\Documento;

use App\Domain\Documento\Enums\EstadoDocumental;
use App\Domain\Documento\Excepciones\AprobacionNoPermitida;
use App\Domain\Documento\Models\DocumentoVersion;

/**
 * «Esto ya está: que lo mire quien tiene que firmarlo.»
 *
 * No congela nada y no mueve ningún fichero: el borrador sigue siendo
 * regenerable mientras está en revisión, porque quien lo revisa pide cambios y
 * quien lo escribió los hace. Lo único que cambia es quién tiene la pelota.
 *
 * El `motivo` es **por qué hay una versión nueva** —«se añade el control A.5.7
 * tras el análisis de riesgos»— y lo escribe quien la prepara. Es distinto de
 * `nota_aprobacion`, que la escribe quien firma. Dos personas, dos momentos y dos
 * columnas, igual que `nota` y `nota_aceptacion` en una valoración de riesgo:
 * con una sola, firmar pisaría el razonamiento que el auditor quiere leer al
 * lado de la firma.
 */
final class EnviarARevision
{
    /**
     * @throws AprobacionNoPermitida
     */
    public function __invoke(DocumentoVersion $version, ?string $motivo = null): DocumentoVersion
    {
        if (! $version->estado->permite(EstadoDocumental::EnRevision)) {
            throw AprobacionNoPermitida::porTransicion($version, EstadoDocumental::EnRevision);
        }

        // Sin PDF no hay nada que revisar: pedirle a la dirección que mire un
        // documento que todavía no existe es cómo se consigue que firme sin
        // mirar.
        if (! $version->tieneFichero()) {
            throw AprobacionNoPermitida::sinPdf($version);
        }

        $version->fill([
            'estado' => EstadoDocumental::EnRevision->value,
            'motivo' => $motivo,
            // Se limpia el rechazo anterior: si esto venía de vuelta, lo que
            // dijo la dirección la vez pasada ya no describe a esta versión.
            'motivo_rechazo' => null,
        ])->save();

        return $version->refresh();
    }
}
