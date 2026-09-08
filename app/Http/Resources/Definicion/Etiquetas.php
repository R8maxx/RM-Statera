<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Cómo se llama el recurso en la interfaz. */
#[TypeScript]
final class Etiquetas
{
    public function __construct(
        public readonly string $singular,
        public readonly string $plural,
        public readonly ?string $descripcion = null,
        public readonly ?string $vacio = null,
    ) {}
}
