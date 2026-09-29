<?php

declare(strict_types=1);

namespace App\Domain\Persona\Enums;

/**
 * Quién impartió una sesión: alguien de la plantilla o un tercero.
 *
 * De esto depende qué se rellena: la interna nombra a una persona de la
 * plantilla, y la externa a quien la impartió y, si se conoce, al proveedor.
 */
enum ImparticionFormacion: string
{
    case Interna = 'interna';
    case Externa = 'externa';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Interna => 'Interna',
            self::Externa => 'Externa',
        };
    }
}
