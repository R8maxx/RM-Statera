<?php

declare(strict_types=1);

namespace App\Domain\Tarea;

use App\Domain\Tarea\Models\Tarea;
use Illuminate\Support\Carbon;

/**
 * Cómo va de fecha una tarea, en una etiqueta que se lee de un vistazo.
 *
 * Vive en el dominio y no en `TareaRecurso` porque lo leen tres sitios —la
 * tabla, el tablero y el calendario— y la única forma de que los tres digan lo
 * mismo es que sólo haya un sitio donde se decide. Con la regla escrita tres
 * veces, la tabla dirá «Vencida» y el tablero «En plazo» el día que una de las
 * tres cambie.
 *
 * **Cuatro situaciones, y «sin plazo» es una de ellas a propósito**: no es lo
 * mismo que ir sobrado, y colapsarlas escondería justo las que nadie ha fechado
 * nunca.
 *
 * El rojo (`caducada`) sale de aquí y de ningún otro sitio de la tabla: una
 * tarea vencida es de las pocas cosas del dominio que van mal de verdad, y si
 * además los estados llevaran rojo, el plazo dejaría de saltar a la vista.
 */
final readonly class Plazo
{
    private function __construct(
        public ?string $fecha,
        public string $etiqueta,
        public string $tono,
        public bool $vencido,
    ) {}

    public static function de(Tarea $tarea): self
    {
        if ($tarea->estado->esCerrada()) {
            return new self($tarea->fecha_cierre?->toDateString(), 'Cerrada', 'no_aplica', false);
        }

        if ($tarea->fecha_limite === null) {
            return new self(null, 'Sin plazo', 'no_iniciado', false);
        }

        $fecha = $tarea->fecha_limite->toDateString();

        if ($tarea->haVencido()) {
            return new self($fecha, 'Vencida', 'caducada', true);
        }

        $dias = (int) Carbon::today()->diffInDays($tarea->fecha_limite, false);

        return new self(
            $fecha,
            match (true) {
                $dias === 0 => 'Vence hoy',
                $dias <= 7 => "Vence en {$dias} días",
                default => 'En plazo',
            },
            // Una semana es lo que cabe en un sprint: más allá, avisar de que
            // algo vence no dice nada que la fecha no diga ya.
            $dias <= 7 ? 'en_progreso' : 'implantado',
            false,
        );
    }
}
