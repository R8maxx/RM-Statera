<?php

declare(strict_types=1);

namespace App\Domain\Riesgo\Excepciones;

use DomainException;

/**
 * No se puede firmar esta valoración.
 *
 * Aceptar un riesgo es el acto formal del módulo —la organización declarando que
 * conoce una exposición y decide convivir con ella— y por eso tiene tres puertas
 * cerradas: no se firma lo que no se ha medido, no se firma dos veces, y no se
 * firma sin haber dicho qué se va a hacer.
 */
final class ValoracionNoAceptable extends DomainException
{
    public static function sinValorar(string $codigo): self
    {
        return new self(sprintf(
            'El riesgo [%s] no está valorado. No se puede aceptar una exposición que nadie ha medido.',
            $codigo,
        ));
    }

    public static function yaAceptada(string $codigo): self
    {
        return new self(sprintf(
            'La valoración vigente de [%s] ya está aceptada. Para cambiar la decisión hay que volver '
            .'a valorar el riesgo, que deja la anterior en el histórico.',
            $codigo,
        ));
    }

    public static function sinResidual(string $codigo): self
    {
        return new self(sprintf(
            'La valoración de [%s] no declara riesgo residual. Firmar sin él es aceptar una cifra '
            .'que no existe: lo que se acepta es lo que queda después de tratar.',
            $codigo,
        ));
    }
}
