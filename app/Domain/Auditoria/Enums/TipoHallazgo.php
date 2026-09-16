<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Enums;

/**
 * Los cuatro del § 2.2, y la frontera que importa es cuál obliga a abrir una no
 * conformidad.
 *
 * **Las dos `nc_` la obligan** —es lo que pide la cláusula 10.2 de ISO: ante una
 * no conformidad, reaccionar, analizar la causa y comprobar la eficacia—. Una
 * observación y una oportunidad de mejora **no**: son avisos, y convertirlas en
 * no conformidades por comodidad infla el registro y acaba con nadie mirándolo.
 *
 * La diferencia entre mayor y menor no la decide la herramienta: es el juicio del
 * auditor sobre si el sistema de gestión falla o si falla una aplicación puntual
 * de él. Statera registra lo que diga, y lo que sí hace es no dejar que una mayor
 * se quede sin no conformidad detrás.
 */
enum TipoHallazgo: string
{
    case NcMayor = 'nc_mayor';
    case NcMenor = 'nc_menor';
    case Observacion = 'observacion';
    case OportunidadMejora = 'oportunidad_mejora';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NcMayor => 'No conformidad mayor',
            self::NcMenor => 'No conformidad menor',
            self::Observacion => 'Observación',
            self::OportunidadMejora => 'Oportunidad de mejora',
        };
    }

    public function etiquetaCorta(): string
    {
        return match ($this) {
            self::NcMayor => 'NC mayor',
            self::NcMenor => 'NC menor',
            self::Observacion => 'Observación',
            self::OportunidadMejora => 'Mejora',
        };
    }

    /**
     * Si el hallazgo obliga a abrir una no conformidad.
     *
     * Lo lee el registro para señalar las que faltan: una NC mayor sin no
     * conformidad detrás es un hallazgo de la siguiente auditoría.
     */
    public function exigeNoConformidad(): bool
    {
        return $this === self::NcMayor || $this === self::NcMenor;
    }

    public function icono(): string
    {
        return match ($this) {
            self::NcMayor => 'OctagonAlert',
            self::NcMenor => 'TriangleAlert',
            self::Observacion => 'MessageSquareWarning',
            self::OportunidadMejora => 'Lightbulb',
        };
    }

    /**
     * **La mayor gasta rojo y la menor no**, y la línea está en la regla de
     * siempre: rojo para lo que va mal de verdad. Una no conformidad mayor es el
     * sistema de gestión fallando; una menor es una aplicación puntual que se
     * corrige, y pintarla igual dejaría de distinguir lo que hay que atender hoy.
     *
     * La oportunidad de mejora va en el azul de lo planificado: no incumple nada,
     * es trabajo que alguien puede decidir hacer.
     */
    public function tono(): string
    {
        return match ($this) {
            self::NcMayor => 'caducada',
            self::NcMenor => 'en_progreso',
            self::Observacion => 'no_iniciado',
            self::OportunidadMejora => 'planificado',
        };
    }
}
