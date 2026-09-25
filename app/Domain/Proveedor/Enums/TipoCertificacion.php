<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Qué acredita el proveedor.
 *
 * Las dos que el § 2.2 nombra —ISO 27001 y conformidad ENS con su categoría— y
 * «otra», con descripción, para lo que no es ninguna de las dos: un informe SOC 2
 * o una cualificación del catálogo CPSTIC.
 */
#[TypeScript]
enum TipoCertificacion: string
{
    case Iso27001 = 'iso27001';
    case Ens = 'ens';
    case Otra = 'otra';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Iso27001 => 'ISO/IEC 27001',
            self::Ens => 'Conformidad con el ENS',
            self::Otra => 'Otra',
        };
    }
}
