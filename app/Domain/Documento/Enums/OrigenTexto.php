<?php

declare(strict_types=1);

namespace App\Domain\Documento\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * De dónde salió el texto que hay hoy en un hueco del documento.
 *
 * Existe para una sola pregunta de la interfaz, y es una que se hace mucho:
 * «¿esto lo he escrito yo o viene de la plantilla?». Sin la respuesta, nadie se
 * atreve a tocar un texto por miedo a pisar el de la organización, y la acción
 * de «Restablecer» no tendría cuándo ofrecerse.
 *
 * **Retocado no es un error**: los dos tonos son neutros, aquí no hay rojo.
 */
#[TypeScript]
enum OrigenTexto: string
{
    case Plantilla = 'plantilla';
    case Propio = 'propio';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Plantilla => 'De la plantilla',
            self::Propio => 'Retocado',
        };
    }

    /**
     * El icono con el que se reconoce sin leer la etiqueta.
     */
    public function icono(): string
    {
        return match ($this) {
            self::Plantilla => 'LayoutTemplate',
            self::Propio => 'PenLine',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Plantilla => 'marco',
            self::Propio => 'exigible',
        };
    }
}
