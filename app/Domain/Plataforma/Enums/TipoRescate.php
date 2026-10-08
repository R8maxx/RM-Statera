<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Lo que la plataforma puede hacer para rescatar a un cliente (punto 52).
 *
 * Dos, y los dos tocan la llave de una cuenta ajena: por eso van siempre con
 * solicitud, verificación escrita y otra persona que ejecuta.
 */
#[TypeScript]
enum TipoRescate: string
{
    case RestablecerSegundoFactor = 'restablecer_segundo_factor';
    case DesignarResponsable = 'designar_responsable';

    public function etiqueta(): string
    {
        return match ($this) {
            self::RestablecerSegundoFactor => 'Restablecer la verificación en dos pasos',
            self::DesignarResponsable => 'Designar un nuevo responsable de seguridad',
        };
    }
}
