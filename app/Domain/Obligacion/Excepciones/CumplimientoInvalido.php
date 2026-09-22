<?php

declare(strict_types=1);

namespace App\Domain\Obligacion\Excepciones;

use DomainException;
use Illuminate\Support\Carbon;

/**
 * Lo que un cumplimiento no puede ser.
 *
 * Las dos condiciones las sostiene también la base —`CHECK (cubre_hasta > fecha)`
 * y, la del futuro, la propia validación del formulario—, y estar aquí no es
 * duplicarlas: es que el mensaje lo escriba el dominio. Un `CHECK` violado sale
 * por pantalla como «SQLSTATE[23514]», que no le dice nada a quien acaba de
 * teclear una fecha.
 */
final class CumplimientoInvalido extends DomainException
{
    public static function enElFuturo(Carbon $fecha): self
    {
        return new self(sprintf(
            'No se puede registrar un cumplimiento con fecha %s: todavía no ha pasado. '
            .'Un cumplimiento es un hecho, y lo que está por venir ya lo enseña el calendario.',
            $fecha->format('d/m/Y'),
        ));
    }

    public static function coberturaAnterior(Carbon $fecha, Carbon $cobertura): self
    {
        return new self(sprintf(
            'La cobertura (%s) no puede ser anterior al cumplimiento (%s): dejaría el compromiso '
            .'fuera de plazo el mismo día en que se cumplió.',
            $cobertura->format('d/m/Y'),
            $fecha->format('d/m/Y'),
        ));
    }
}
