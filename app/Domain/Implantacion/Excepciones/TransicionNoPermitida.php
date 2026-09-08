<?php

declare(strict_types=1);

namespace App\Domain\Implantacion\Excepciones;

use App\Domain\Implantacion\Enums\EstadoImplantacion;
use DomainException;

final class TransicionNoPermitida extends DomainException
{
    public function __construct(
        public readonly EstadoImplantacion $desde,
        public readonly EstadoImplantacion $hasta,
        string $motivo = '',
    ) {
        $mensaje = "No se permite pasar de [{$desde->value}] a [{$hasta->value}].";

        parent::__construct($motivo === '' ? $mensaje : "{$mensaje} {$motivo}");
    }

    public static function noAplicaEsDerivado(EstadoImplantacion $desde): self
    {
        return new self(
            $desde,
            EstadoImplantacion::NoAplica,
            'El estado `no_aplica` lo deriva el motor de categorización a partir de la valoración del sistema; no se fija a mano.',
        );
    }
}
