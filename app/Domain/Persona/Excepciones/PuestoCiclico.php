<?php

declare(strict_types=1);

namespace App\Domain\Persona\Excepciones;

use App\Domain\Persona\Models\Puesto;
use DomainException;

/**
 * Se ha intentado hacer que un puesto reporte a alguien que ya depende de él.
 *
 * Un ciclo en el organigrama no es un dato raro: es lo que hace que la CTE
 * recursiva **no termine**. Se rechaza al crearlo, no al leerlo.
 */
final class PuestoCiclico extends DomainException
{
    public static function entre(Puesto $puesto, Puesto $superior, int $saltos): self
    {
        $por = $saltos === 0
            ? 'ya depende directamente de él'
            : sprintf('ya depende de él a través de %d puesto(s)', $saltos);

        return new self(sprintf(
            'No se puede hacer que «%s» reporte a «%s»: %s, y eso cerraría un bucle en el organigrama.',
            $puesto->titulo,
            $superior->titulo,
            $por,
        ));
    }
}
