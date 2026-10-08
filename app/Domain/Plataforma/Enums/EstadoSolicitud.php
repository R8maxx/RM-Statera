<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * En qué punto está una solicitud de rescate (punto 52).
 *
 * `Caducada` no se guarda: se deriva de que siga pendiente más de
 * `SolicitudPlataforma::HORAS_DE_VALIDEZ` horas, como caduca una cuenta.
 */
#[TypeScript]
enum EstadoSolicitud: string
{
    case Pendiente = 'pendiente';
    case Ejecutada = 'ejecutada';
    case Rechazada = 'rechazada';
    case Caducada = 'caducada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Ejecutada => 'Ejecutada',
            self::Rechazada => 'Rechazada',
            self::Caducada => 'Caducada',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Pendiente => 'planificado',
            self::Ejecutada => 'implantado',
            self::Rechazada => 'no_aplica',
            self::Caducada => 'no_iniciado',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Pendiente => 'Clock',
            self::Ejecutada => 'CircleCheck',
            self::Rechazada => 'CircleX',
            self::Caducada => 'CalendarX',
        };
    }
}
