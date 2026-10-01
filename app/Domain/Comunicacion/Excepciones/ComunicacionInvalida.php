<?php

declare(strict_types=1);

namespace App\Domain\Comunicacion\Excepciones;

use DomainException;
use Illuminate\Support\Carbon;

/**
 * Lo que una comunicación no puede ser.
 *
 * La base sostiene casi todo con `CHECK`; esto existe para que el mensaje lo
 * escriba el dominio y no salga como «SQLSTATE[23514]». Mismo papel que
 * `CumplimientoInvalido`.
 */
final class ComunicacionInvalida extends DomainException
{
    public static function enElFuturo(Carbon $fecha): self
    {
        return new self(sprintf(
            'No se puede registrar una comunicación con fecha %s: todavía no ha pasado. '
            .'Lo que está previsto ya lo enseña el plan.',
            $fecha->format('d/m/Y'),
        ));
    }

    public static function previstaRetirada(string $codigo): self
    {
        return new self("La comunicación prevista {$codigo} está retirada: lo que se comunique ya no la cumple.");
    }

    public static function recibidaConPrevista(): self
    {
        return new self('Lo recibido no cumple ningún plan de comunicación: el plan es de lo que la organización comunica.');
    }

    public static function recibidaSinTipo(): self
    {
        return new self('Lo recibido necesita decir qué es: una queja, una sugerencia, el resultado de una encuesta…');
    }
}
