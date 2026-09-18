<?php

declare(strict_types=1);

namespace App\Domain\Contexto\Enums;

/**
 * Con qué fuerza ata lo que pide una parte interesada. Cláusula 4.2.
 *
 * La distinción no es burocrática: **lo legal y lo contractual obligan y una
 * expectativa no**. Un requisito legal incumplido es una sanción; una expectativa
 * no atendida es un cliente descontento. Meter las tres en el mismo saco lleva a
 * las dos equivocaciones a la vez — tratar una expectativa como un incumplimiento
 * y perder de vista la obligación de verdad entre treinta deseos razonables.
 *
 * Y es lo que hace que este módulo le pueda dar algo a la SoA: un requisito
 * **legal** de un regulador, atado a la implantación que lo cubre, es una
 * justificación de inclusión tan legítima para ISO 6.1.3 d) como el tratamiento de
 * un riesgo. Una expectativa no lo es, y por eso hay que poder separarlas.
 *
 * **El tono va en la familia ordinal** —`alta`, `media`, `basica`— y no en la de
 * estados, porque esto es exactamente un ordinal: sube en énfasis con lo que ata.
 * Es la solución que `DESIGN.md` § 9 ya documentó para la categoría del ENS, donde
 * el rojo mentía —«una categoría alta no es un error, es un sistema que exige
 * más»—, y aquí pasa lo mismo: un requisito legal no es un problema, es una
 * obligación conocida.
 */
enum NaturalezaRequisito: string
{
    case Legal = 'legal';
    case Contractual = 'contractual';
    case Expectativa = 'expectativa';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Legal => 'Legal o reglamentario',
            self::Contractual => 'Contractual',
            self::Expectativa => 'Expectativa',
        };
    }

    /**
     * Si el incumplimiento tiene consecuencia exigible.
     *
     * Lo miran el indicador de «requisitos que obligan sin nada que los cubra» y la
     * justificación de inclusión de la SoA: una expectativa no justifica incluir un
     * control, lo sugiere.
     */
    public function obliga(): bool
    {
        return $this !== self::Expectativa;
    }

    public function icono(): string
    {
        return match ($this) {
            // La institución que lo exige, no una balanza: la balanza es la marca.
            self::Legal => 'Landmark',
            self::Contractual => 'Handshake',
            self::Expectativa => 'MessageCircle',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Legal => 'alta',
            self::Contractual => 'media',
            self::Expectativa => 'basica',
        };
    }
}
