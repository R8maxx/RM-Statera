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
 *
 * **Desde el § 4.13 tiene un segundo cliente**, las no conformidades, que
 * también son «algo abierto con una fecha para cuándo». De ahí que la regla viva
 * en `para()` y que `de()` sea sólo la traducción de una tarea: el argumento de
 * los tres sitios vale igual para cinco, y una segunda copia de «vencida en rojo,
 * sin plazo en gris» es exactamente cómo se acaba con dos pantallas que
 * discrepan. Se queda en `Domain\Tarea` porque es donde nació y donde se lee
 * tres de las cinco veces; si llega un tercer contexto, se mueve al lado de
 * `Indicador`, que hizo ese mismo viaje.
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
        return self::para(
            $tarea->fecha_limite,
            $tarea->estado->esCerrada(),
            $tarea->fecha_cierre,
            $tarea->haVencido(),
        );
    }

    /**
     * La regla, sin saber de qué registro viene.
     *
     * `$vencido` se recibe en vez de deducirse de la fecha porque cada contexto
     * ya tiene escrito qué significa —`Tarea::haVencido()`,
     * `NoConformidad::haVencido()`— y deducirlo aquí sería la misma condición por
     * segunda vez, que es justo lo que esta clase existe para evitar.
     *
     * `$etiquetaCerrada` la pone quien llama: «Cerrada» en una tarea y «Tratada»
     * en una no conformidad, que ahí todavía le falta la verificación de eficacia
     * y llamarla cerrada sería decir que está resuelta.
     */
    public static function para(
        ?Carbon $fecha,
        bool $cerrado,
        ?Carbon $fechaCierre,
        bool $vencido,
        string $etiquetaCerrada = 'Cerrada',
    ): self {
        if ($cerrado) {
            return new self($fechaCierre?->toDateString(), $etiquetaCerrada, 'no_aplica', false);
        }

        if ($fecha === null) {
            return new self(null, 'Sin plazo', 'no_iniciado', false);
        }

        $texto = $fecha->toDateString();

        if ($vencido) {
            return new self($texto, 'Vencida', 'caducada', true);
        }

        $dias = (int) Carbon::today()->diffInDays($fecha, false);

        return new self(
            $texto,
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
