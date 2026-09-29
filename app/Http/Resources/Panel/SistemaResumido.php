<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Una fila de la lista de sistemas del panel. */
#[TypeScript]
final class SistemaResumido
{
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?string $marco,
        public readonly ?string $categoria,
        public readonly int $aplicables,
        public readonly int $implantadas,
        /** Implantados sin ninguna prueba detrás: `Implantacion::sinEvidencia()`. */
        public readonly int $sinPrueba = 0,
    ) {}
}
