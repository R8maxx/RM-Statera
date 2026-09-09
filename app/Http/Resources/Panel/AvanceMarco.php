<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El avance de un marco: cuántos de sus requisitos exigibles están implantados.
 *
 * Viajan los dos números y no el porcentaje: «62 %» sin denominador no es un
 * dato que un auditor pueda contrastar, y el panel enseña siempre la fracción
 * real debajo de la cifra.
 */
#[TypeScript]
final class AvanceMarco
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly int $aplicables,
        public readonly int $implantadas,
    ) {}
}
