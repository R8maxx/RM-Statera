<?php

declare(strict_types=1);

namespace App\Domain\Metrica\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cómo va un indicador respecto a su objetivo.
 *
 * **No es una columna**: se deriva de (`valor`, `objetivo`, `sentido`) cada vez
 * que hace falta. Guardarlo sería el mismo dato en dos sitios que pueden
 * discrepar, como `vigente` en el análisis del contexto.
 *
 * **Cuatro casos y no dos, por el argumento de `EstadoControl::PorConfirmar`:**
 * «sin objetivo» y «sin medir» no son «fuera de objetivo». Un indicador que la
 * organización vigila sin comprometerse a una cifra está haciendo seguimiento,
 * que es lo que la 9.1 pide; y un periodo sin medir es una pregunta abierta, no
 * un suspenso. Meterlos en el mismo saco daría un cuadro de mando en rojo el día
 * que se crea el primer indicador, que es cómo se deja de mirar un panel.
 *
 * **Y el rojo no entra aquí.** `DESIGN.md` § 3 lo reserva para lo que va mal de
 * verdad, y estar por debajo de un objetivo **es la distancia que queda**, no un
 * incumplimiento: pintarlo de alarma castiga por ponerse objetivos ambiciosos,
 * que es exactamente lo que el quinto principio del producto existe para
 * impedir. Lo que sí va mal de verdad es un periodo que se cerró **sin medir**
 * habiéndose comprometido a medirlo, y de eso responde el vencimiento del
 * calendario, no este enum.
 *
 * Los dos grises se separan por icono, como `no_iniciado` y `no_aplica`, que
 * están a ΔE 2.3 con protanopía y llevan declarado el mismo tratamiento.
 */
#[TypeScript]
enum CumplimientoIndicador: string
{
    case EnObjetivo = 'en_objetivo';
    case FueraDeObjetivo = 'fuera_de_objetivo';
    case SinObjetivo = 'sin_objetivo';
    case SinMedir = 'sin_medir';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnObjetivo => 'En objetivo',
            self::FueraDeObjetivo => 'Fuera de objetivo',
            self::SinObjetivo => 'Sin objetivo',
            self::SinMedir => 'Sin medir',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::EnObjetivo => 'implantado',
            // Ámbar y no rojo: es lo que queda por recorrer, no un fallo.
            self::FueraDeObjetivo => 'en_progreso',
            self::SinObjetivo => 'no_aplica',
            self::SinMedir => 'no_iniciado',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::EnObjetivo => 'CircleCheck',
            self::FueraDeObjetivo => 'CircleDotDashed',
            // Los dos grises, separados por el símbolo: uno es una decisión de no
            // ponerse cifra y el otro es un dato que falta.
            self::SinObjetivo => 'CircleSlash',
            self::SinMedir => 'CircleHelp',
        };
    }
}
