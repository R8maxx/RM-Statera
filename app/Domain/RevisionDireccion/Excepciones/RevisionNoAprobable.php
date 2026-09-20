<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion\Excepciones;

use App\Domain\RevisionDireccion\Enums\EstadoRevision;
use DomainException;

/**
 * Un acta que no se puede firmar todavía.
 *
 * Dos casos, y el segundo es el que importa: una revisión que sigue
 * **planificada** no se ha celebrado —firmar el acta de una reunión que no ha
 * ocurrido es exactamente lo que la 9.3 existe para hacer imposible— y una que ya
 * está **aprobada** no se vuelve a firmar, porque el trigger de PostgreSQL la ha
 * vuelto inmutable y la firma sería un `UPDATE` que el propio trigger rechazaría
 * con un mensaje que habla del acta y no de lo que la persona estaba haciendo.
 */
final class RevisionNoAprobable extends DomainException
{
    public static function porEstado(EstadoRevision $estado): self
    {
        return new self(match ($estado) {
            EstadoRevision::Planificada => 'La revisión sigue planificada: no se puede firmar el acta de una reunión que todavía no se ha celebrado.',
            EstadoRevision::Aprobada => 'El acta ya está aprobada. Para corregirla hay que reabrir la revisión.',
            EstadoRevision::EnCurso => 'La revisión no se puede aprobar.',
        });
    }
}
