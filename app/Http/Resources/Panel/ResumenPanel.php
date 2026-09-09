<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Las cifras que abren el panel.
 *
 * `madurezMedia` viaja con su denominador a propósito: una media de madurez
 * calculada sobre cuatro requisitos de doscientos no dice lo mismo que sobre
 * los doscientos, y el panel tiene que poder decir sobre cuántos se calcula.
 * Es `null` cuando nadie ha valorado la madurez todavía, que no es lo mismo
 * que cero.
 */
#[TypeScript]
final class ResumenPanel
{
    public function __construct(
        public readonly int $sistemas,
        public readonly int $aplicables,
        public readonly int $implantadas,
        public readonly int $pendientes,
        public readonly ?float $madurezMedia,
        public readonly int $madurezEvaluadas,
    ) {}
}
