<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El valor de una celda de tipo `escala`: un nivel dentro de una progresión
 * conocida.
 *
 * Es lo que necesita la madurez del CCN (L0–L5) y lo que no daba un badge: un
 * badge dice «L4» pero no que L4 es más que L2, y esa comparación es justo lo
 * que se busca al recorrer la columna. Viajan el nivel, el máximo de la escala
 * y la etiqueta completa, porque el color nunca es la única lectura de un
 * estado (DESIGN.md §11).
 */
#[TypeScript]
final class ValorEscala
{
    public function __construct(
        public readonly int $valor,
        public readonly int $de,
        public readonly string $etiqueta,
        /** La forma corta que usa el marco: «L3». Sin ella se enseña `3/5`. */
        public readonly ?string $corta = null,
    ) {}
}
