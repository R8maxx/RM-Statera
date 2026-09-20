<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Los objetivos de seguridad, tal y como los lee el panel. Cláusula 6.2.
 *
 * **`sinIndicador` es la cifra que está aquí por la norma y no por la pantalla**,
 * igual que `sinVerificar` en las no conformidades. La 6.2 exige que el objetivo
 * sea medible y que se declare cómo se evaluarán los resultados; un objetivo sin
 * ningún indicador detrás cumple eso de palabra y no de hecho, y es lo primero
 * que un auditor pide cuando ve la lista.
 *
 * **Y sin porcentaje de objetivos alcanzados**, por el mismo argumento que dejó
 * fuera el del plan de acción y el de las no conformidades: esa cifra sube al
 * cerrar y baja al comprometerse con uno nuevo, así que castiga por ponerse
 * objetivos ambiciosos. Lo que abre la tarjeta es cuántos hay vivos, con su
 * denominador.
 */
#[TypeScript]
final class ResumenObjetivosPanel
{
    /**
     * @param  list<Reparto>  $porEstado
     */
    public function __construct(
        public readonly int $total,
        public readonly int $vivos,
        public readonly int $vencidos,
        public readonly int $sinIndicador,
        public readonly int $sinActuacion,
        public readonly array $porEstado,
    ) {}
}
