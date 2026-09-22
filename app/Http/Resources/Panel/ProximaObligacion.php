<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * La obligación que toca antes, con lo justo para pintarla en una línea.
 *
 * Escalares y nada más, como el resto de lo que viaja al panel: `dias` con signo
 * —negativo si ya pasó— y la frase ya escrita, para que la tarjeta no repita la
 * aritmética que ya hace el dominio.
 */
#[TypeScript]
final class ProximaObligacion
{
    public function __construct(
        public readonly int $id,
        public readonly string $titulo,
        public readonly string $fecha,
        public readonly int $dias,
        public readonly string $cuando,
    ) {}
}
