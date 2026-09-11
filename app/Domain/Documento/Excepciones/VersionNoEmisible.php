<?php

declare(strict_types=1);

namespace App\Domain\Documento\Excepciones;

use App\Domain\Documento\Models\DocumentoVersion;
use RuntimeException;

/**
 * Se intentó emitir algo que no se puede emitir.
 *
 * Emitir es entregar: a partir de ahí la fila es inmutable y el PDF es el que se
 * enseñará dentro de dos años. Por eso no se emite un borrador que todavía se
 * está generando, ni uno que falló, ni se re-emite lo ya emitido.
 */
final class VersionNoEmisible extends RuntimeException
{
    public static function porEstado(DocumentoVersion $version): self
    {
        if ($version->estaEmitida()) {
            return new self("La versión v{$version->numero} ya está emitida y no se vuelve a emitir.");
        }

        return new self(
            'El borrador todavía no tiene PDF: está '
            .mb_strtolower($version->estado_generacion->etiqueta()).'.'
        );
    }
}
