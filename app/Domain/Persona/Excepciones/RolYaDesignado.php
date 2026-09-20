<?php

declare(strict_types=1);

namespace App\Domain\Persona\Excepciones;

use App\Domain\Persona\Enums\RolEns;
use DomainException;

/**
 * Un segundo titular vigente de un rol que sólo admite uno.
 *
 * Lo impone además un índice único parcial, y esta guarda existe para que el
 * mensaje que llegue sea éste y no una `QueryException` con el nombre del índice
 * — el mismo razonamiento que la guarda del cierre de una auditoría.
 *
 * Responsable de la información y responsable del servicio no pasan por aquí:
 * pueden ser varios, uno por cada información tratada y por cada servicio.
 */
final class RolYaDesignado extends DomainException
{
    public function __construct(public readonly RolEns $rol, string $titular)
    {
        parent::__construct(sprintf(
            'Este sistema ya tiene un «%s» vigente: %s. Revoca esa designación antes de nombrar a otra persona.',
            $rol->etiqueta(),
            $titular,
        ));
    }
}
