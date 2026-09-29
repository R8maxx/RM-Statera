<?php

declare(strict_types=1);

namespace App\Domain\Obligacion\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Qué fue de un compromiso durante un trozo de su vida.
 *
 * Lo calcula `CicloCompromiso` y lo pinta la ficha como una barra en el tiempo.
 * Existe porque el histórico en lista contesta «cuándo se cumplió», pero no
 * «¿hubo un hueco?», que es lo que un auditor busca al mirarlo: dos
 * cumplimientos seguidos no dicen si el segundo llegó a tiempo.
 *
 * **Los dos rojos son el mismo tono y tienen icono distinto.** Un periodo que
 * estuvo sin cubrir ya fue mal —es un hallazgo aunque hoy esté al día— y el
 * que sigue abierto va mal ahora. El color dice que los dos son lo mismo; el
 * icono y la palabra, cuál es cuál.
 */
#[TypeScript]
enum TramoCiclo: string
{
    /** Desde que se empieza a contar hasta el primer vencimiento. Todavía no se debía nada. */
    case Plazo = 'plazo';

    /** Lo que cubre un cumplimiento, desde su fecha hasta su `cubre_hasta`. */
    case Cubierto = 'cubierto';

    /** Un vencimiento que pasó sin cumplir, hasta que llegó el cumplimiento siguiente. */
    case SinCubrir = 'sin_cubrir';

    /** Lo mismo, pero sin cerrar: va del vencimiento a hoy. */
    case Vencido = 'vencido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Plazo => 'Primer plazo',
            self::Cubierto => 'Cubierto',
            self::SinCubrir => 'Sin cubrir',
            self::Vencido => 'Fuera de plazo',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Plazo => 'no_iniciado',
            self::Cubierto => 'implantado',
            self::SinCubrir, self::Vencido => 'caducada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Plazo => 'Clock',
            self::Cubierto => 'CircleCheck',
            self::SinCubrir => 'CircleAlert',
            self::Vencido => 'TriangleAlert',
        };
    }
}
