<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Persona\Models\Persona;

/**
 * Propone el código de la siguiente persona: `PER-001`.
 *
 * **Propone y no impone**, como el resto de generadores. Aquí pesa más que en
 * ninguno: la mayoría de organizaciones ya tienen un número de empleado y
 * obligarlas a renumerar rompe la correspondencia con su nómina.
 *
 * **Sin el año en el código**, a diferencia de una no conformidad o de un
 * objetivo: una persona no pertenece a un ejercicio. Numera correlativo desde el
 * máximo, y el hueco que deja una baja no se reutiliza — reasignar el código de
 * quien se fue a quien entra es cómo se acaba con dos personas distintas en el
 * mismo registro de formación.
 *
 * El `::int` del parámetro no es adorno: sin él, PDO manda el binding como texto y
 * PostgreSQL lee `substring(x from '5')` como la forma con expresión regular. Ver
 * `CodigoNoConformidad`.
 */
final class CodigoPersona
{
    private const PREFIJO = 'PER-';

    public function siguiente(): string
    {
        $ultimo = Persona::query()
            ->where('codigo', 'like', self::PREFIJO.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?::int), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen(self::PREFIJO) + 1])
            ->value('ultimo');

        return self::PREFIJO.sprintf('%03d', ((int) $ultimo) + 1);
    }
}
