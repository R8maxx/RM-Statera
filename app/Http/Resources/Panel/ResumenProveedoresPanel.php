<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Los proveedores, tal y como los lee el panel (§ 4.9).
 *
 * **Dos rojos, y los dos caducan solos**: la reevaluación vencida y la
 * certificación caducada. El reparto es por criticidad de los que no están
 * retirados, que es lo que dice cuánto depende la organización de terceros.
 */
#[TypeScript]
final class ResumenProveedoresPanel
{
    /**
     * @param  list<Reparto>  $porCriticidad
     */
    public function __construct(
        public readonly int $total,
        public readonly int $reevaluacionVencida,
        public readonly int $certificacionCaducada,
        public readonly int $sinEvaluar,
        public readonly int $condicionados,
        public readonly array $porCriticidad,
    ) {}
}
