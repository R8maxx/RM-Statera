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

    /**
     * El icono con el que se reconoce sin leer la etiqueta.
     *
     * La prioridad es ordinal, así que los iconos también: una flecha que sube
     * más cuanto más corre. Es lo que hace que se lea el orden sin leer la
     * palabra.
     */
    public function icono(): string
    {
        return match ($this) {
            self::Baja => 'ChevronDown',
            self::Media => 'Equal',
            self::Alta => 'ChevronUp',
            self::Critica => 'ChevronsUp',
        };
    }

    /**
     * El tono del dominio con el que se pinta, que no es un color.
     *
     * La prioridad es ordinal —baja < media < alta < crítica—, así que sube en
     * énfasis en vez de cambiar de significado. Mismo criterio que la categoría
     * del ENS en `CeldaBadge`: `alta` estaba en rojo y eso mentía, porque una
     * prioridad alta no es un error.
     *
     * **Y sin rojo**: el rojo es del plazo. Si además la prioridad lo llevara,
     * una tabla se pondría roja por dos motivos distintos y el plazo dejaría de
     * saltar a la vista.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Baja => 'basica',
            self::Media => 'exigible',
            self::Alta => 'media',
            self::Critica => 'alta',
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
