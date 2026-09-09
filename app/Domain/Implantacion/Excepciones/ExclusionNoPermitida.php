<?php

declare(strict_types=1);

namespace App\Domain\Implantacion\Excepciones;

use App\Domain\Categorizacion\Enums\OrigenExigencia;
use DomainException;

/**
 * Se ha intentado excluir a mano una medida cuya exigencia deriva del motor.
 *
 * Es el invariante 4 aplicado a la ficha: si a un sistema se le exige `op.acc.5`
 * porque su categoría es media, dejar de exigírsela es cambiar la valoración,
 * no marcar una casilla. Si se permitiera, el siguiente recálculo la
 * reactivaría y nadie entendería por qué.
 */
final class ExclusionNoPermitida extends DomainException
{
    public function __construct(public readonly ?OrigenExigencia $origen)
    {
        $motivo = match ($origen) {
            OrigenExigencia::Categoria => 'la exige la categoría del sistema',
            OrigenExigencia::ModulacionDimension => 'la exige el nivel de una dimensión concreta',
            OrigenExigencia::Perfil => 'la exige el perfil de cumplimiento del sistema',
            default => 'su exigencia la deriva el motor de categorización',
        };

        parent::__construct(
            "Esta medida no se puede excluir a mano: {$motivo}. Deja de exigirse cambiando la valoración de las dimensiones del sistema."
        );
    }
}
