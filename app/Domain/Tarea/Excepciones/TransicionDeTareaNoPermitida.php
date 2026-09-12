<?php

declare(strict_types=1);

namespace App\Domain\Tarea\Excepciones;

use App\Domain\Tarea\Enums\EstadoTarea;
use DomainException;

final class TransicionDeTareaNoPermitida extends DomainException
{
    public function __construct(
        public readonly EstadoTarea $desde,
        public readonly EstadoTarea $hasta,
        string $motivo = '',
    ) {
        $mensaje = "No se permite pasar de [{$desde->value}] a [{$hasta->value}].";

        parent::__construct($motivo === '' ? $mensaje : "{$mensaje} {$motivo}");
    }
}
