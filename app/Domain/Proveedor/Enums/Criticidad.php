<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Enums;

use App\Domain\Categorizacion\Enums\NivelDimension;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cuánto depende la organización de un proveedor, y por tanto cada cuánto se le
 * reevalúa.
 *
 * Tres escalones, los de las dimensiones del Anexo I, porque el mínimo se deriva
 * de ellas: la valoración más alta de los activos que presta. `na` no da
 * criticidad —un activo sin valorar no dice nada— y un proveedor sin activos la
 * declara.
 */
#[TypeScript]
enum Criticidad: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';

    public static function desdeNivel(NivelDimension $nivel): ?self
    {
        return match ($nivel) {
            NivelDimension::Na => null,
            NivelDimension::Bajo => self::Baja,
            NivelDimension::Medio => self::Media,
            NivelDimension::Alto => self::Alta,
        };
    }

    public function peso(): int
    {
        return match ($this) {
            self::Baja => 1,
            self::Media => 2,
            self::Alta => 3,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
        };
    }

    /** La familia ordinal: sube en énfasis, no cambia de hue. */
    public function tono(): string
    {
        return match ($this) {
            self::Baja => 'basica',
            self::Media => 'media',
            self::Alta => 'alta',
        };
    }
}
