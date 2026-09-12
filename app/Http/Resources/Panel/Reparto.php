<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un tramo de un reparto: cuántas filas caen en cada valor de algo.
 *
 * Cuántos activos hay de un tipo, cuántas tareas en cada estado. Es lo que comen
 * `BarraSegmentada` y `GraficaBarras`, y la forma es la misma para cualquier
 * módulo: no se duplica por módulo.
 *
 * `tono` es un nombre de estado del dominio —nunca un color—: los colores viven
 * en `app.css` y los traduce el componente que pinta.
 */
#[TypeScript]
final class Reparto
{
    public function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly int $valor,
        public readonly string $tono,
        /** La query string que aísla esas filas en la tabla, si la hay. */
        public readonly ?string $filtro = null,
    ) {}
}
