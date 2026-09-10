<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un tramo de un reparto del inventario: cuántos activos hay de un tipo, de un
 * estado o con un control en cada valor.
 *
 * `tono` es un nombre de estado del dominio —nunca un color—: los colores viven
 * en `app.css` y los traduce el componente que pinta.
 */
#[TypeScript]
final class RepartoInventario
{
    public function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly int $valor,
        public readonly string $tono,
        /** La query string que aísla esos activos en la tabla, si la hay. */
        public readonly ?string $filtro = null,
    ) {}
}
