<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Una opción de un filtro de selección. */
#[TypeScript]
final class Opcion
{
    public function __construct(
        public readonly string $valor,
        public readonly string $etiqueta,
    ) {}

    /**
     * @param  array<string, string>  $pares  valor => etiqueta
     * @return list<self>
     */
    public static function desdeArray(array $pares): array
    {
        return array_map(
            static fn (string $etiqueta, string $valor): self => new self($valor, $etiqueta),
            array_values($pares),
            array_keys($pares),
        );
    }
}
