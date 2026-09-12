<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El plan de acción, tal y como lo lee el panel.
 *
 * `abiertas` viaja con `total` por lo mismo que `resueltos` viaja con
 * `vigentes`: ocho tareas abiertas sobre diez es un plan que no ha arrancado, y
 * sobre doscientas es un martes.
 *
 * No hay porcentaje de completado y es deliberado: esa cifra sube al cerrar y
 * baja al apuntar trabajo nuevo, así que mide actividad y no salud. Lo que
 * alarma es `vencidas`, y va aparte.
 */
#[TypeScript]
final class ResumenPlanPanel
{
    /**
     * @param  list<Reparto>  $porEstado
     * @param  list<Reparto>  $porPrioridad
     */
    public function __construct(
        public readonly int $total,
        public readonly int $abiertas,
        public readonly int $vencidas,
        public readonly int $sinResponsable,
        public readonly array $porEstado,
        public readonly array $porPrioridad,
    ) {}
}
