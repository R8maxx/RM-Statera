<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El inventario, tal y como lo lee el panel.
 *
 * `resueltos` viaja con `vigentes` a propósito, como la madurez viaja con su
 * denominador: «el 50 % tiene los controles decididos» no dice lo mismo sobre
 * cuatro activos que sobre trescientos, y quien lee el panel tiene derecho a
 * saber sobre cuántos se calcula.
 */
#[TypeScript]
final class ResumenInventarioPanel
{
    /**
     * @param  list<Reparto>  $cifrado
     * @param  list<Reparto>  $copia
     * @param  list<Reparto>  $porTipo
     * @param  list<Reparto>  $porCicloDeVida
     */
    public function __construct(
        public readonly int $vigentes,
        public readonly int $resueltos,
        public readonly int $restringidos,
        public readonly array $cifrado,
        public readonly array $copia,
        public readonly array $porTipo,
        public readonly array $porCicloDeVida,
    ) {}
}
