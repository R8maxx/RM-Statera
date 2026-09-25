<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Excepciones;

use App\Domain\Proveedor\Enums\Criticidad;
use DomainException;

/** Lo que el dominio de proveedores impide, con la frase que lo explica. */
final class OperacionDeProveedorNoPermitida extends DomainException
{
    public static function sinCriticidad(): self
    {
        return new self(
            'Este proveedor no presta ningún activo del inventario, así que su criticidad no se puede '
            .'derivar: hay que declararla.'
        );
    }

    public static function rebajaSinJustificar(Criticidad $derivada): self
    {
        return new self(sprintf(
            'Los activos que presta le dan una criticidad %s. Para declararla por debajo hay que '
            .'justificar por qué: es la que decide cada cuánto se le reevalúa.',
            mb_strtolower($derivada->etiqueta()),
        ));
    }

    public static function retirado(): self
    {
        return new self('Un proveedor retirado no se evalúa. Si se vuelve a trabajar con él, primero hay que reactivarlo.');
    }

    public static function clausulasIncompletas(): self
    {
        return new self('Una evaluación contesta todas las cláusulas vigentes: cumple, no cumple o no aplica.');
    }

    public static function aptoConIncumplimientos(): self
    {
        return new self(
            'Hay cláusulas que no se cumplen y el resultado es «apto». Si se acepta así, es «apto con '
            .'condiciones», y las condiciones van en las conclusiones.'
        );
    }

    public static function sinMotivo(): self
    {
        return new self('Retirar un proveedor necesita un motivo: es lo que se pregunta cuando alguien lo busca después.');
    }

    public static function yaEnEseEstado(): self
    {
        return new self('El proveedor ya está en ese estado.');
    }
}
