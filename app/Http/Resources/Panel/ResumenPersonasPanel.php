<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Las personas, tal y como las lee el panel. § 4.8 y cláusula 5.3.
 *
 * **`rolesDesignados` sobre `rolesExigibles` es la cifra del 5.3**, y es la que
 * está aquí por la norma y no por la pantalla — como `sinVerificar` en las no
 * conformidades y `sinIndicador` en los objetivos. Hasta este módulo, los roles
 * ENS eran una limitación impresa en el PDF de la DdA.
 *
 * **Y sin porcentaje de personal formado como cifra de cabecera.** Esa cifra sube
 * al impartir una sesión y baja sola al pasar doce meses, así que castigaría por
 * tener plantilla nueva. Lo que abre la tarjeta es cuántas personas hay, con su
 * denominador; el porcentaje vive en su indicador, que es donde se compara contra
 * un objetivo.
 */
#[TypeScript]
final class ResumenPersonasPanel
{
    public function __construct(
        public readonly int $total,
        public readonly int $activas,
        public readonly int $sinFormacion,
        public readonly int $sinAcuerdo,
        public readonly int $bajaSinCerrar,
        public readonly int $rolesDesignados,
        public readonly int $rolesExigibles,
    ) {}
}
