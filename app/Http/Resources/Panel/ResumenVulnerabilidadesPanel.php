<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Las vulnerabilidades, tal y como las lee el panel. Invariante 8, A.8.8 y
 * `op.exp.4`.
 *
 * **`fueraDePlazo` es el único rojo**, como en el registro: una crítica recién
 * detectada no va mal, se está atendiendo. El reparto es por severidad y **sólo
 * de las vivas**, porque la pregunta es cuánto pesa lo que sigue sin arreglo; las
 * cerradas están en su lista, con su verificación.
 */
#[TypeScript]
final class ResumenVulnerabilidadesPanel
{
    /**
     * @param  list<Reparto>  $porSeveridad
     */
    public function __construct(
        public readonly int $total,
        public readonly int $vivas,
        public readonly int $fueraDePlazo,
        public readonly int $criticasAbiertas,
        public readonly int $sinVerificar,
        public readonly int $aceptadas,
        public readonly array $porSeveridad,
    ) {}
}
