<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Persona\Models\Puesto;

/**
 * Propone el código del siguiente puesto: `PUE-001`.
 *
 * Calcado de `CodigoPersona`, incluido el motivo de cada decisión: **propone y no
 * impone**, **sin año** —un puesto no pertenece a un ejercicio— y correlativo
 * desde el máximo sin reutilizar huecos.
 *
 * El `::int` del parámetro no es adorno: sin él, PDO manda el binding como texto y
 * PostgreSQL lee `substring(x from '5')` como la forma con expresión regular,
 * devuelve NULL y **todos los códigos salen `-001`**, chocando con el índice único
 * a partir del segundo. Ver `CodigoNoConformidad`, que es donde mordió.
 */
final class CodigoPuesto
{
    private const PREFIJO = 'PUE-';

    public function siguiente(): string
    {
        $ultimo = Puesto::query()
            ->where('codigo', 'like', self::PREFIJO.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?::int), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen(self::PREFIJO) + 1])
            ->value('ultimo');

        return self::PREFIJO.sprintf('%03d', ((int) $ultimo) + 1);
    }
}
