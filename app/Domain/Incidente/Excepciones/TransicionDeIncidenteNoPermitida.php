<?php

declare(strict_types=1);

namespace App\Domain\Incidente\Excepciones;

use App\Domain\Incidente\Enums\EstadoIncidente;
use DomainException;

/**
 * Un paso que la máquina de estados de un incidente no admite, o un cierre sin
 * lección aprendida.
 *
 * **El segundo caso es el que paga el módulo**: `op.exp.7` pide aprender del
 * incidente, y es el paso que todo el mundo se salta el día que el servicio
 * vuelve. Lo impone además un `CHECK`, y esta excepción existe para que el
 * mensaje sea legible y no el nombre de una restricción — el mismo razonamiento
 * que la guarda del cierre de una auditoría.
 */
final class TransicionDeIncidenteNoPermitida extends DomainException
{
    public static function entre(EstadoIncidente $desde, EstadoIncidente $hasta): self
    {
        return new self(sprintf(
            'Un incidente «%s» no puede pasar a «%s».',
            $desde->etiqueta(),
            $hasta->etiqueta(),
        ));
    }

    public static function sinLeccion(): self
    {
        return new self(
            'Para cerrar un incidente hay que escribir qué se aprendió. Es lo que pide op.exp.7, y es '
            .'el paso que se salta todo el mundo el día que el servicio vuelve: sin él, el mismo '
            .'incidente se repite el año que viene.'
        );
    }

    public static function sinMotivo(EstadoIncidente $destino): self
    {
        return new self(sprintf(
            'Volver a «%s» exige escribir por qué: lo que se reabre es un incidente que alguien ya '
            .'había dado por resuelto.',
            $destino->etiqueta(),
        ));
    }
}
