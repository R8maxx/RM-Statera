<?php

declare(strict_types=1);

namespace App\Domain\Documento\Excepciones;

use App\Domain\Documento\Models\DocumentoVersion;
use RuntimeException;

/**
 * Se intentó cerrar una aprobación que no está en condiciones de cerrarse.
 *
 * Cerrarla es numerar y entregar: a partir de ahí la fila es inmutable y el PDF
 * es el que se enseñará dentro de dos años. Por eso no se cierra una versión sin
 * PDF, ni una que nadie ha firmado, ni se vuelve a numerar la ya numerada.
 */
final class VersionNoEmisible extends RuntimeException
{
    public static function porEstado(DocumentoVersion $version): self
    {
        if ($version->estaEmitida()) {
            return new self("La versión v{$version->numero} ya está emitida y no se vuelve a emitir.");
        }

        if (! $version->tieneFichero()) {
            return new self(
                'El borrador todavía no tiene PDF: está '
                .mb_strtolower($version->estado_generacion->etiqueta()).'.'
            );
        }

        // El caso que importa: sin firma no hay entrega. Emitir dejó de ser un
        // acto propio cuando aprobar pasó a ser lo que emite, y una versión
        // numerada sin firmante sería exactamente el registro que este módulo
        // existe para hacer imposible.
        return new self(sprintf(
            'La versión está en «%s» y sin firma: sólo se entrega lo que la dirección ha aprobado.',
            $version->estado->etiqueta(),
        ));
    }
}
