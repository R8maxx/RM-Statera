<?php

declare(strict_types=1);

namespace App\Domain\Riesgo\Excepciones;

use DomainException;

/**
 * Un riesgo que no pesa sobre ningún activo.
 *
 * No es un riesgo: es una frase. Sin activos no hay de dónde deducir el impacto
 * —que sale de lo que valen— ni qué hay que proteger si se decide tratarlo.
 */
final class RiesgoSinActivos extends DomainException
{
    public static function alCrear(): self
    {
        return new self(
            'Un riesgo pesa sobre al menos un activo: el impacto se deduce de lo que valen, '
            .'y sin ninguno no hay nada que puntuar ni nada que proteger.'
        );
    }

    public static function alDesvincular(string $codigo): self
    {
        return new self(sprintf(
            'No se puede quitar el último activo de [%s]. Si el riesgo ya no aplica a nada, lo que '
            .'procede es cerrarlo con su motivo, no dejarlo sin objeto.',
            $codigo,
        ));
    }
}
