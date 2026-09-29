<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use App\Domain\Aviso\Vencimiento;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * «Lo que vence», al lado del plan de acción en «El ciclo».
 *
 * Las filas van recortadas —lo pasado primero y después lo próximo— y los dos
 * recuentos van enteros, para que la tarjeta pueda decir «y 6 más» con su enlace
 * al calendario en vez de callarse lo que no cabe.
 */
#[TypeScript]
final class VencimientosPanel
{
    /**
     * @param  list<Vencimiento>  $filas
     */
    public function __construct(
        public readonly int $pasados,
        public readonly int $proximos,
        /** La ventana hacia delante, en días. */
        public readonly int $dias,
        public readonly array $filas,
    ) {}
}
