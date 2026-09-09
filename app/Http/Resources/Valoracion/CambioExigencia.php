<?php

declare(strict_types=1);

namespace App\Http\Resources\Valoracion;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Una medida que sigue exigiéndose pero a otro nivel de refuerzo.
 *
 * Ni entra ni sale del conjunto, así que es el cambio que más fácil pasa
 * desapercibido y el que más caro sale: la implantación conserva su estado
 * mientras lo que se le exige ha cambiado por debajo.
 */
#[TypeScript]
final class CambioExigencia
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $anterior,
        public readonly string $nueva,
    ) {}
}
