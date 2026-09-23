<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Enums;

/**
 * El ciclo de una prueba de continuidad: § 4.11 y `op.cont.3`.
 *
 * **Sólo dos salidas desde `planificada`, y cada una con su propia acción del
 * dominio.** `realizada` la sella `RegistrarResultadoPrueba` y `cancelada` la
 * sella `CancelarPrueba`; no hay un `CambiarEstadoPrueba` genérico porque no
 * hace falta uno: las dos salidas exigen datos distintos —fecha y resultado la
 * primera, motivo la segunda— y colapsarlas en un único punto de entrada sólo
 * escondería esa diferencia. **Las dos son terminales**: una prueba realizada
 * o cancelada no vuelve a planificada ni cambia de resultado; lo que se hace
 * es planificar la siguiente.
 */
enum EstadoPrueba: string
{
    case Planificada = 'planificada';
    case Realizada = 'realizada';
    case Cancelada = 'cancelada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Planificada => 'Planificada',
            self::Realizada => 'Realizada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Planificada => 'CalendarClock',
            self::Realizada => 'CircleCheck',
            self::Cancelada => 'CircleSlash',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Planificada => 'planificado',
            self::Realizada => 'implantado',
            self::Cancelada => 'no_aplica',
        };
    }

    public function esTerminal(): bool
    {
        return $this !== self::Planificada;
    }
}
