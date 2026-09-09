<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Qué le pasó a la entidad. Tres casos y no más: lo que interesa de un log de
 * cumplimiento es si algo nació, cambió o desapareció, y el detalle vive en los
 * valores anterior y nuevo.
 */
#[TypeScript]
enum AccionAuditada: string
{
    case Creado = 'creado';
    case Actualizado = 'actualizado';
    case Eliminado = 'eliminado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Creado => 'Creado',
            self::Actualizado => 'Actualizado',
            self::Eliminado => 'Eliminado',
        };
    }
}
