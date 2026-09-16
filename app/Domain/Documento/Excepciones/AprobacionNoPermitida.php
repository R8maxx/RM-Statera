<?php

declare(strict_types=1);

namespace App\Domain\Documento\Excepciones;

use App\Domain\Documento\Enums\EstadoDocumental;
use App\Domain\Documento\Models\DocumentoVersion;
use RuntimeException;

/**
 * Se intentó mover una versión por un camino que el flujo no admite.
 *
 * Los mensajes van con nombres propios porque salen en pantalla: «no permitido»
 * a secas obliga a ir a leer el código para saber qué falta.
 */
final class AprobacionNoPermitida extends RuntimeException
{
    public static function porTransicion(DocumentoVersion $version, EstadoDocumental $destino): self
    {
        return new self(sprintf(
            'Una versión en «%s» no puede pasar a «%s».',
            $version->estado->etiqueta(),
            $destino->etiqueta(),
        ));
    }

    /**
     * Firmar exige tener delante lo que se firma.
     *
     * No es burocracia: la firma se imprime en la portada del PDF, así que
     * aprobar algo que no se ha llegado a generar produciría un documento que
     * nadie ha podido leer antes de aprobarlo.
     */
    public static function sinPdf(DocumentoVersion $version): self
    {
        return new self(
            'La versión todavía no tiene PDF que aprobar: está '
            .mb_strtolower($version->estado_generacion->etiqueta()).'.'
        );
    }

    /**
     * Acusar la lectura de algo que no está aprobado.
     *
     * Un borrador se regenera, así que la fila diría que alguien leyó un
     * documento que ya no existe. Lo que la cláusula 7.3 pide es que la gente
     * conozca lo que está vigente.
     */
    public static function noAprobada(DocumentoVersion $version): self
    {
        return new self(sprintf(
            'Sólo se acusa la lectura de una versión aprobada, y ésta está en «%s».',
            $version->estado->etiqueta(),
        ));
    }

    /** Descartar es una decisión, y una decisión sin motivo no se audita. */
    public static function sinMotivo(): self
    {
        return new self('Rechazar una versión exige escribir por qué.');
    }

    public static function yaAprobada(DocumentoVersion $version): self
    {
        return new self("La versión {$version->etiqueta()} ya está aprobada y no se vuelve a aprobar.");
    }
}
