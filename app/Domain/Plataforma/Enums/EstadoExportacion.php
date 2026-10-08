<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * En qué punto está una exportación de los datos de un cliente (punto 56).
 */
#[TypeScript]
enum EstadoExportacion: string
{
    case EnCurso = 'en_curso';
    case Lista = 'lista';
    case Fallida = 'fallida';
    case Caducada = 'caducada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnCurso => 'Preparándose',
            self::Lista => 'Lista para descargar',
            self::Fallida => 'Fallida',
            self::Caducada => 'Caducada',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::EnCurso => 'en_progreso',
            self::Lista => 'implantado',
            self::Fallida => 'caducada',
            self::Caducada => 'no_aplica',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::EnCurso => 'LoaderCircle',
            self::Lista => 'CircleCheck',
            self::Fallida => 'TriangleAlert',
            self::Caducada => 'CalendarX',
        };
    }
}
