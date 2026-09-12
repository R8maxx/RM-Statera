<?php

declare(strict_types=1);

namespace App\Domain\Implantacion\Enums;

/**
 * Estado de una implantación.
 *
 * `NoAplica` es distinto de los otros cuatro: lo pone el sistema cuando la
 * medida deja de exigirse tras un recálculo, nunca una persona a mano. Por eso
 * no aparece en ninguna lista de transiciones permitidas.
 */
enum EstadoImplantacion: string
{
    case NoIniciado = 'no_iniciado';
    case Planificado = 'planificado';
    case EnProgreso = 'en_progreso';
    case Implantado = 'implantado';
    case NoAplica = 'no_aplica';

    /**
     * A qué estados se puede pasar desde éste por decisión de una persona.
     *
     * Se permite retroceder: una implantación que se da por hecha y luego se
     * comprueba que no lo estaba vuelve a `en_progreso`, y esa vuelta atrás es
     * justo lo que el histórico tiene que poder enseñar.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::NoIniciado => [self::Planificado, self::EnProgreso, self::Implantado],
            self::Planificado => [self::NoIniciado, self::EnProgreso, self::Implantado],
            self::EnProgreso => [self::Planificado, self::Implantado],
            self::Implantado => [self::EnProgreso, self::Planificado],

            // Sale de aquí sola, cuando la medida vuelve a exigirse.
            self::NoAplica => [],
        };
    }

    public function permite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /** Los estados que puede fijar una persona. `no_aplica` lo deriva el motor. */
    public function esGestionablePorUsuario(): bool
    {
        return $this !== self::NoAplica;
    }

    public function esTerminal(): bool
    {
        return $this === self::Implantado;
    }

    /**
     * El tono del dominio con el que se pinta, que no es un color.
     *
     * No existía: los valores de este enum coinciden con las claves del mapa de
     * `CeldaBadge`, así que se venía pasando `->value` como si fuera un tono y
     * colaba por casualidad. Ahora lo dice el dominio, como en el resto.
     */
    public function tono(): string
    {
        return $this->value;
    }

    /**
     * El icono con el que se reconoce sin leer la etiqueta.
     *
     * Nombre de `@lucide/vue`, que resuelve `IconoTipo` en el cliente.
     */
    public function icono(): string
    {
        return match ($this) {
            self::NoIniciado => 'Circle',
            // Tiene fecha puesta, todavía no ha empezado.
            self::Planificado => 'CalendarClock',
            self::EnProgreso => 'CircleDotDashed',
            self::Implantado => 'CircleCheck',
            self::NoAplica => 'CircleSlash',
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::NoIniciado => 'No iniciado',
            self::Planificado => 'Planificado',
            self::EnProgreso => 'En progreso',
            self::Implantado => 'Implantado',
            self::NoAplica => 'No aplica',
        };
    }
}
