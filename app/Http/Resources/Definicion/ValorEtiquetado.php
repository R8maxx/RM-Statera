<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use Spatie\TypeScriptTransformer\Attributes\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El valor de una celda que no se pinta en crudo: un estado, una categoría, un
 * nivel de madurez.
 *
 * El `tono` es un nombre de estado del dominio, no un color. Los colores viven
 * en `resources/css/app.css` y se declaran una sola vez.
 *
 * El `icono` es opcional para casi todo y **obligatorio para los tipos de
 * activo**: nueve categorías no caben en el hueco de tono que dejan los estados
 * manteniendo ΔE 6 entre ellas (DESIGN.md §3), así que ahí el color agrupa y el
 * icono identifica. Viaja el nombre de lucide, nunca un componente.
 */
#[TypeScript]
final class ValorEtiquetado
{
    public function __construct(
        public readonly string|int|null $valor,
        public readonly string $etiqueta,
        // `Optional` y no sólo nulable: quien construye un `ValorEtiquetado` en
        // TypeScript —las fichas lo hacen a mano para un badge suelto— no tiene
        // por qué escribir `icono: null` en cada uno.
        #[Optional]
        public readonly ?string $tono = null,
        #[Optional]
        public readonly ?string $icono = null,
    ) {}
}
