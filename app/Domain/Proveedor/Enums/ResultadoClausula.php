<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Lo que se vio en el contrato para una cláusula.
 *
 * «No aplica» existe porque no todas muerden siempre: el encargo de tratamiento
 * sólo si hay datos personales, la conformidad ENS sólo si el servicio es parte
 * de un sistema sujeto al ENS.
 */
#[TypeScript]
enum ResultadoClausula: string
{
    case Cumple = 'cumple';
    case NoCumple = 'no_cumple';
    case NoAplica = 'no_aplica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Cumple => 'Cumple',
            self::NoCumple => 'No cumple',
            self::NoAplica => 'No aplica',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Cumple => 'implantado',
            self::NoCumple => 'no_iniciado',
            self::NoAplica => 'no_aplica',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Cumple => 'CircleCheck',
            self::NoCumple => 'CircleX',
            self::NoAplica => 'CircleSlash',
        };
    }
}
