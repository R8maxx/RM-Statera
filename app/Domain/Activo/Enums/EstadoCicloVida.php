<?php

declare(strict_types=1);

namespace App\Domain\Activo\Enums;

/**
 * Dónde está el activo en su vida.
 *
 * `Retirado` y `DadoDeBaja` no son lo mismo, y colapsarlos sería perder justo lo
 * que se pregunta en una auditoría: retirado es que ya no presta servicio;
 * dado de baja es que además se ha destruido o borrado de forma segura y hay
 * constancia de ello (`mp.si.5`). Un disco retirado que sigue en un cajón con
 * los datos dentro es un hallazgo, no un activo cerrado.
 *
 * `EnStock`, `EnReparacion` y `Prestado` siguen contando como vigentes: un
 * portátil en el armario o en el taller sigue teniendo los datos dentro y sigue
 * siendo responsabilidad de alguien. Sacarlos del inventario activo es
 * exactamente cómo se pierde el rastro de un equipo.
 */
enum EstadoCicloVida: string
{
    case Planificado = 'planificado';
    case EnStock = 'en_stock';
    case EnProduccion = 'en_produccion';
    case EnMantenimiento = 'en_mantenimiento';
    case EnReparacion = 'en_reparacion';
    case Prestado = 'prestado';
    case Retirado = 'retirado';
    case DadoDeBaja = 'dado_de_baja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Planificado => 'Planificado',
            self::EnStock => 'En stock',
            self::EnProduccion => 'En uso',
            self::EnMantenimiento => 'En mantenimiento',
            self::EnReparacion => 'En reparación',
            self::Prestado => 'Prestado o cedido',
            self::Retirado => 'Retirado',
            self::DadoDeBaja => 'Dado de baja',
        };
    }

    /**
     * El icono con el que se reconoce sin leer la etiqueta.
     *
     * Ocho etapas no caben en cinco tonos —tres comparten `en_progreso` y dos
     * `no_aplica`—, así que aquí el icono no es refuerzo: es lo único que separa
     * «en mantenimiento» de «en reparación» o «retirado» de «dado de baja».
     */
    public function icono(): string
    {
        return match ($this) {
            self::Planificado => 'CalendarClock',
            self::EnStock => 'Package',
            self::EnProduccion => 'CircleCheck',
            self::EnMantenimiento => 'Wrench',
            self::EnReparacion => 'Hammer',
            self::Prestado => 'ArrowRightLeft',
            self::Retirado => 'Archive',
            // Lo que además tiene constancia de borrado seguro.
            self::DadoDeBaja => 'Trash2',
        };
    }

    /** El tono del badge. Reutiliza los `--estado-*`; no hay familia nueva. */
    public function tono(): string
    {
        return match ($this) {
            self::Planificado => 'planificado',
            self::EnStock => 'no_iniciado',
            self::EnProduccion => 'implantado',
            self::EnMantenimiento, self::EnReparacion, self::Prestado => 'en_progreso',
            self::Retirado, self::DadoDeBaja => 'no_aplica',
        };
    }

    /** Si el activo sigue vivo a efectos del inventario y del análisis de riesgos. */
    public function estaVigente(): bool
    {
        return match ($this) {
            self::Planificado, self::EnStock, self::EnProduccion,
            self::EnMantenimiento, self::EnReparacion, self::Prestado => true,
            self::Retirado, self::DadoDeBaja => false,
        };
    }
}
