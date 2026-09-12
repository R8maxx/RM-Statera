<?php

declare(strict_types=1);

namespace App\Domain\Tarea\Enums;

/**
 * Cuánto corre.
 *
 * Cuatro niveles y no cinco: con una escala impar todo el mundo elige el del
 * medio y la prioridad deja de ordenar nada. Sin `media` no habría dónde poner
 * lo normal, así que se deja en cuatro con dos por encima.
 */
enum PrioridadTarea: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case Critica = 'critica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
            self::Critica => 'Crítica',
        };
    }

    /** Para ordenar: lo que más corre, primero. */
    public function peso(): int
    {
        return match ($this) {
            self::Critica => 4,
            self::Alta => 3,
            self::Media => 2,
            self::Baja => 1,
        };
    }
}
