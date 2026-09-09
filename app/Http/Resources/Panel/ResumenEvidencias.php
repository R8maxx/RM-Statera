<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El estado del repositorio de pruebas, para el panel.
 *
 * `implantadasSinEvidencia` es la cifra incómoda y por eso está: separa «lo
 * tenemos hecho» de «lo podemos demostrar», que es lo único que un auditor
 * distingue. Un requisito implantado sin ninguna prueba detrás es un hallazgo
 * esperando a que alguien pregunte.
 */
#[TypeScript]
final class ResumenEvidencias
{
    public function __construct(
        public readonly int $total,
        public readonly int $caducadas,
        public readonly int $porCaducar,
        public readonly int $implantadasSinEvidencia,
    ) {}
}
