<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El desempeño, tal y como lo lee el panel (§ 4.14 y cláusula 9.1).
 *
 * **Sin porcentaje de «indicadores en objetivo»**, por el mismo motivo por el
 * que el plan de acción no lleva porcentaje de tareas hechas: esa cifra sube al
 * ponerse objetivos flojos y baja al ponerse ambiciosos, así que mide el listón
 * y no el desempeño. Un indicador que castiga por apuntar lo que falta enseña a
 * no apuntarlo.
 *
 * Lo que sí sube es **`periodoSinMedir`**, que es la única cifra del módulo que
 * está por la norma y no por la pantalla: un indicador declarado y no medido es
 * la 9.1 sin hacer, y es lo primero que se comprueba.
 */
#[TypeScript]
final readonly class ResumenMetricasPanel
{
    public function __construct(
        public int $total,
        public int $activos,
        public int $periodoSinMedir,
        public int $nuncaMedidos,
        public int $fueraDeObjetivo,
        /** @var list<Reparto> */
        public array $porCumplimiento,
    ) {}
}
