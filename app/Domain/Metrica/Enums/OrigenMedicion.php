<?php

declare(strict_types=1);

namespace App\Domain\Metrica\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * De dónde sale la cifra. Cláusula 9.1 b), «los métodos de seguimiento».
 *
 * Lo llevan **el indicador y cada medición**, y no es el mismo dato dos veces:
 * el del indicador es la política de hoy y el de la fila es el hecho de cómo se
 * obtuvo **aquélla**. Pasar un indicador de calculado a manual no puede
 * reescribir cómo se tomó la medición de marzo — mismo reparto que la exigencia
 * congelada en `auditoria_puntos`.
 *
 * Y «calculado» no quiere decir «se consulta al mirarlo»: quiere decir que el
 * sistema **propone** la cifra al cerrar el periodo y la sella. Una serie que se
 * recalcula reescribe el pasado.
 */
#[TypeScript]
enum OrigenMedicion: string
{
    case Calculado = 'calculado';
    case Manual = 'manual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Calculado => 'Calculado por Statera',
            self::Manual => 'Registro manual',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Calculado => 'Database',
            self::Manual => 'PenLine',
        };
    }
}
