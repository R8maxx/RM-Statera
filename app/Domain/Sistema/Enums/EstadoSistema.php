<?php

declare(strict_types=1);

namespace App\Domain\Sistema\Enums;

enum EstadoSistema: string
{
    case Borrador = 'borrador';
    case Activo = 'activo';
    case Archivado = 'archivado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Activo => 'Activo',
            self::Archivado => 'Archivado',
        };
    }
}
