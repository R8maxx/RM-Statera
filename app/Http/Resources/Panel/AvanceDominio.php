<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El avance de un dominio de control: la raíz de la jerarquía del catálogo.
 *
 * `A.5` a `A.8` y las cláusulas 4 a 10 en ISO/IEC 27001:2022; `org`, `op` y `mp`
 * en el ENS. Es lo que DESIGN.md § 9 pide al panel de cumplimiento —«grado de
 * implantación por dominio de control»— y lo que el avance por marco no dice: un
 * 66 % en el Anexo A puede ser un 88 % en personas y un 57 % en físicos.
 *
 * Lleva el código del marco y no un anidamiento dentro de `AvanceMarco` para no
 * tocar la forma que ya lee el informe de estado.
 */
#[TypeScript]
final class AvanceDominio
{
    public function __construct(
        public readonly string $marco,
        public readonly string $codigo,
        public readonly string $titulo,
        public readonly int $aplicables,
        public readonly int $implantadas,
    ) {}
}
