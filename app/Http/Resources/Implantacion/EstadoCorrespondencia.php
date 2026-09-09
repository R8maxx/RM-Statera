<?php

declare(strict_types=1);

namespace App\Http\Resources\Implantacion;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Lo que la organización tiene hecho en un requisito de otro marco.
 *
 * Va por sistema porque el mismo control puede estar implantado en el SGSI de
 * ISO y no en el sistema del ENS, y ésa es justamente la diferencia que la
 * hoja de cálculo duplicada no sabía enseñar.
 */
#[TypeScript]
final class EstadoCorrespondencia
{
    public function __construct(
        public readonly int $implantacionId,
        public readonly string $sistema,
        public readonly string $estado,
        public readonly string $estadoEtiqueta,
        public readonly bool $aplica,
    ) {}
}
