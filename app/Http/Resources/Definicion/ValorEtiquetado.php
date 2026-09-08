<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El valor de una celda que no se pinta en crudo: un estado, una categoría, un
 * nivel de madurez.
 *
 * El `tono` es un nombre de estado del dominio, no un color. Los colores viven
 * en `resources/css/app.css` y se declaran una sola vez.
 */
#[TypeScript]
final class ValorEtiquetado
{
    public function __construct(
        public readonly string|int|null $valor,
        public readonly string $etiqueta,
        public readonly ?string $tono = null,
    ) {}
}
