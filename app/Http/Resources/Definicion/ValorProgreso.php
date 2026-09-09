<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El valor de una celda de tipo `progreso`.
 *
 * Un porcentaje suelto no basta: «62 %» sin saber sobre cuántos requisitos se
 * calcula no es un dato que un auditor pueda usar. Por eso viajan también el
 * numerador y el denominador, que es lo que se enseña junto a la barra.
 *
 * Se admite `null` en `de` para los casos en que sólo hay un porcentaje real.
 */
#[TypeScript]
final class ValorProgreso
{
    public function __construct(
        public readonly int $porcentaje,
        public readonly ?int $hechas = null,
        public readonly ?int $de = null,
    ) {}
}
