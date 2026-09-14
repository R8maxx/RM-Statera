<?php

declare(strict_types=1);

namespace App\Domain\Riesgo\Excepciones;

use DomainException;

/**
 * Una metodología cuyas piezas se contradicen entre sí.
 *
 * Las escalas pueden ser válidas por separado y los umbrales seguir sin tener
 * sentido: un umbral crítico por debajo del de aceptación deja una franja donde
 * un riesgo es a la vez tolerable e inasumible, y un umbral por encima del
 * producto máximo de las dos escalas es una línea que no se puede cruzar nunca
 * —el indicador de «riesgos por encima del umbral» daría cero para siempre y
 * nadie sabría por qué—.
 */
final class MetodologiaIncoherente extends DomainException
{
    public static function umbralesCruzados(int $aceptacion, int $critico): self
    {
        return new self(sprintf(
            'El umbral crítico (%d) no puede estar por debajo del de aceptación (%d): dejaría una '
            .'franja donde un riesgo es tolerable e inasumible a la vez.',
            $critico,
            $aceptacion,
        ));
    }

    public static function umbralInalcanzable(string $cual, int $umbral, int $maximo): self
    {
        return new self(sprintf(
            'El umbral %s vale %d y el riesgo máximo que permiten las dos escalas es %d: es una línea '
            .'que no se puede cruzar, así que el indicador daría cero para siempre.',
            $cual,
            $umbral,
            $maximo,
        ));
    }

    public static function umbralBajoMinimo(string $cual, int $umbral): self
    {
        return new self(sprintf(
            'El umbral %s vale %d. El riesgo más bajo posible es 1, así que un umbral de 1 o menos '
            .'deja todos los riesgos por encima y el umbral deja de separar nada.',
            $cual,
            $umbral,
        ));
    }
}
