<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion\Excepciones;

use App\Domain\RevisionDireccion\Enums\EstadoRevision;
use DomainException;

/**
 * Un salto que la máquina de estados no admite.
 *
 * El caso que importa es volver a `planificada` desde una revisión aprobada:
 * sería decir que la reunión no se celebró. Lo impide también el trigger de
 * PostgreSQL, y esta guarda existe para que el mensaje que llega sea éste y no un
 * `RAISE EXCEPTION` sin tildes que sube como `QueryException` — el mismo
 * razonamiento que la guarda del cierre de una auditoría.
 */
final class TransicionDeRevisionNoPermitida extends DomainException
{
    public function __construct(
        public readonly EstadoRevision $desde,
        public readonly EstadoRevision $hasta,
    ) {
        parent::__construct("No se permite pasar de [{$desde->value}] a [{$hasta->value}].");
    }
}
