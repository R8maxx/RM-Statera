<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Las no conformidades, tal y como las lee el panel.
 *
 * `abiertas` viaja con `total` por lo mismo que en el plan de acción: dos
 * abiertas sobre tres es una organización que no cierra nada, y sobre ciento
 * veinte es un martes. El § 4.14 pide literalmente «no conformidades abiertas»
 * entre los indicadores del cuadro de mando.
 *
 * **`sinVerificar` va en la misma fila que `vencidas` y no escondida en el
 * reparto**, y es la única cifra de este módulo que se añadió por la norma y no
 * por la pantalla: una no conformidad cerrada y sin verificar se lee como
 * resuelta y no lo está, y la cláusula 10.2 e) es justo el paso que el auditor
 * comprueba porque es el que todo el mundo se salta.
 *
 * Y **sin porcentaje de cerradas**, por el mismo argumento que dejó fuera el del
 * plan de acción: esa cifra sube al cerrar y baja al registrar una nueva, así
 * que castiga por apuntar lo que falta — y aquí castigaría por auditar bien.
 */
#[TypeScript]
final class ResumenNoConformidadesPanel
{
    /**
     * @param  list<Reparto>  $porEstado
     */
    public function __construct(
        public readonly int $total,
        public readonly int $abiertas,
        public readonly int $vencidas,
        public readonly int $sinVerificar,
        public readonly int $sinAccion,
        public readonly array $porEstado,
    ) {}
}
