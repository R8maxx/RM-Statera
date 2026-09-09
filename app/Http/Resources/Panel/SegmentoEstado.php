<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cuántos requisitos exigibles hay en un estado.
 *
 * La `clave` es el valor del estado del dominio, que es lo que `BarraSegmentada`
 * traduce a color. Aquí no viaja ningún color.
 */
#[TypeScript]
final class SegmentoEstado
{
    public function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly int $valor,
    ) {}
}
