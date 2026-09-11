<?php

declare(strict_types=1);

namespace App\Domain\Documento\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cómo de restringido es el documento.
 *
 * Va impresa en el pie de cada página, y eso no es adorno: estampar la
 * clasificación en el propio documento es lo que pide `mp.info.2` del ENS. Una
 * SoA sin marca de clasificación acaba reenviada por correo a quien no debe, y
 * ése es exactamente el hallazgo.
 *
 * **No sustituye al nivel del Anexo I.** La clasificación se decide y se
 * estampa; el nivel se deriva de valorar el perjuicio. Conviven.
 */
#[TypeScript]
enum ClasificacionDocumental: string
{
    case Publico = 'publico';
    case UsoInterno = 'uso_interno';
    case Confidencial = 'confidencial';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Publico => 'Público',
            self::UsoInterno => 'Uso interno',
            self::Confidencial => 'Confidencial',
        };
    }

    /** Cómo se imprime en el pie: en versales y sin adornos. */
    public function sello(): string
    {
        return mb_strtoupper($this->etiqueta());
    }

    public function tono(): string
    {
        return match ($this) {
            self::Publico => 'no_aplica',
            self::UsoInterno => 'planificado',
            self::Confidencial => 'caducada',
        };
    }
}
