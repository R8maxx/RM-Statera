<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Enums;

/**
 * Las cinco dimensiones de seguridad del ENS. La valoración de estas cinco es
 * la única entrada del motor de categorización: la aplicabilidad se deriva de
 * ella, nunca se selecciona a mano.
 */
enum Dimension: string
{
    case Confidencialidad = 'C';
    case Integridad = 'I';
    case Disponibilidad = 'D';
    case Autenticidad = 'A';
    case Trazabilidad = 'T';

    public function nombre(): string
    {
        return match ($this) {
            self::Confidencialidad => 'Confidencialidad',
            self::Integridad => 'Integridad',
            self::Disponibilidad => 'Disponibilidad',
            self::Autenticidad => 'Autenticidad',
            self::Trazabilidad => 'Trazabilidad',
        };
    }
}
