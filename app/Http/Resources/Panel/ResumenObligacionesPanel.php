<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Las obligaciones periódicas, tal y como las lee el panel. § 4.16.
 *
 * **Lleva la próxima con su nombre y su fecha, y no sólo un recuento.** «3
 * pendientes» no contesta la pregunta de quien mira el panel; «Informe INES —
 * vence en 41 días» sí. Es la misma razón por la que toda cifra del producto
 * viaja con su denominador: un número suelto se mira y no se acciona.
 *
 * **Y sin porcentaje de cumplimiento**, por el mismo argumento que dejó fuera el
 * del plan de acción: el denominador crece cada vez que alguien declara una
 * obligación, así que la cifra bajaría justo al hacer lo correcto.
 */
#[TypeScript]
final class ResumenObligacionesPanel
{
    public function __construct(
        public readonly int $total,
        public readonly int $vencidas,
        public readonly int $porVencer,
        public readonly int $nuncaCumplidas,
        public readonly int $sinResponsable,
        /** Lo que toca antes, con nombre y fecha. Nulo si no hay ninguna vigente. */
        public readonly ?ProximaObligacion $proxima,
    ) {}
}
