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
        /**
         * El icono del dominio, si la opción lo tiene.
         *
         * Llegó con el § 4.16, y para un caso concreto: los chips de fuente del
         * calendario son **filtro y leyenda a la vez**, y en la rejilla el icono
         * es el único canal que identifica la fuente. Sin esto, el cliente
         * tendría que resolver el icono de cada fuente por su cuenta, que es
         * exactamente la segunda lista que `IconoTipo` existe para evitar.
         *
         * Nulo en el resto de filtros, y ahí no se pinta nada.
         */
        public readonly ?string $icono = null,
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
