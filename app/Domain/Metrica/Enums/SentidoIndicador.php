<?php

declare(strict_types=1);

namespace App\Domain\Metrica\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Hacia dónde mejora un indicador.
 *
 * Es obligatorio aunque el objetivo sea opcional, y no es un adorno: «tareas
 * vencidas ≤ 5» y «cobertura de cifrado ≥ 90 %» se juzgan al revés. Sin esta
 * columna el veredicto sale invertido en la mitad de los indicadores, y un
 * cuadro de mando que felicita por subir las no conformidades vencidas deja de
 * mirarse el mismo día.
 *
 * El cumplimiento **se deriva** de (`valor`, `objetivo`, `sentido`) y no se
 * guarda: guardarlo sería el mismo dato en dos sitios que pueden discrepar.
 */
#[TypeScript]
enum SentidoIndicador: string
{
    case MayorMejor = 'mayor_mejor';
    case MenorMejor = 'menor_mejor';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MayorMejor => 'Cuanto más alto, mejor',
            self::MenorMejor => 'Cuanto más bajo, mejor',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::MayorMejor => 'ChevronUp',
            self::MenorMejor => 'ChevronDown',
        };
    }

    /** Si ese valor alcanza ese objetivo. El umbral cuenta como alcanzado. */
    public function alcanza(float $valor, float $objetivo): bool
    {
        return match ($this) {
            self::MayorMejor => $valor >= $objetivo,
            self::MenorMejor => $valor <= $objetivo,
        };
    }

    /** Cómo se lee el objetivo al lado de la cifra: «≥ 90 %», «≤ 5». */
    public function comparador(): string
    {
        return match ($this) {
            self::MayorMejor => '≥',
            self::MenorMejor => '≤',
        };
    }
}
