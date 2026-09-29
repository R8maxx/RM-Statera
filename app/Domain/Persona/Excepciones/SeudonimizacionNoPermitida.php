<?php

declare(strict_types=1);

namespace App\Domain\Persona\Excepciones;

use DomainException;

/**
 * Por qué no se pueden suprimir todavía los datos de una persona.
 *
 * Suprimir no tiene vuelta atrás, así que lo que lo impide se dice con el
 * motivo y con lo que hay que hacer antes.
 */
final class SeudonimizacionNoPermitida extends DomainException
{
    public static function sigueEnPlantilla(string $persona): self
    {
        return new self("{$persona} sigue en plantilla. Registra antes su fecha de baja: sólo se suprimen los datos de quien ya no trabaja aquí.");
    }

    public static function conNombramientos(string $persona, int $vigentes): self
    {
        return new self(sprintf(
            '%s tiene %d nombramiento%s vigente%s. Ciérralo%s antes: un rol del ENS no puede quedar asignado a alguien sin nombre.',
            $persona,
            $vigentes,
            $vigentes === 1 ? '' : 's',
            $vigentes === 1 ? '' : 's',
            $vigentes === 1 ? '' : 's',
        ));
    }

    public static function yaSeudonimizada(string $codigo): self
    {
        return new self("Los datos de {$codigo} ya se suprimieron.");
    }
}
