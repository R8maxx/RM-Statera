<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Los incidentes, tal y como los lee el panel. § 4.10 y `op.exp.7`.
 *
 * **`fueraDePlazoAepd` es la cifra que está aquí por la ley y no por la
 * pantalla** —como `sinVerificar` en las no conformidades y `rolesDesignados` en
 * personas—: son las 72 h del artículo 33.1 del RGPD, y es el único rojo del
 * módulo. Va separada de `enPlazoAepd` a propósito: una es un incumplimiento y la
 * otra es trabajo urgente, y colapsarlas pondría en rojo a quien lo está haciendo
 * bien.
 *
 * **El CCN-CERT no tiene cifra propia**, y es la decisión del módulo: el
 * RD 311/2022 dice «sin dilación» y no fija horas, así que no hay plazo que
 * contar sin inventárselo. Se ve en la ficha, con su nota.
 *
 * **Y sin porcentaje de cerrados**, por el mismo argumento de siempre: esa cifra
 * sube al cerrar y baja al registrar uno nuevo, así que castigaría por detectar
 * bien.
 */
#[TypeScript]
final class ResumenIncidentesPanel
{
    /**
     * @param  list<Reparto>  $porEstado
     */
    public function __construct(
        public readonly int $total,
        public readonly int $abiertos,
        public readonly int $fueraDePlazoAepd,
        public readonly int $enPlazoAepd,
        public readonly int $sinLeccion,
        public readonly array $porEstado,
    ) {}
}
