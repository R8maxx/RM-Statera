<?php

declare(strict_types=1);

namespace App\Domain\Tarea\Enums;

/**
 * En qué situación está una tarea del plan de acción.
 *
 * `Bloqueada` existe porque es la diferencia entre «nadie la ha cogido» y
 * «alguien la cogió y no puede avanzar». Sin ese estado las dos se cuentan
 * igual, y son problemas distintos: una pide asignarla y la otra pide destrabar
 * algo. Es además la que más cara sale de no ver.
 *
 * `Descartada` no es `Hecha`. Una tarea que se decide no hacer se cierra con su
 * motivo en la nota de la transición, y eso es una decisión que el auditor puede
 * cuestionar; borrarla deja el hallazgo sin rastro de qué se hizo con él.
 */
enum EstadoTarea: string
{
    case Pendiente = 'pendiente';
    case EnCurso = 'en_curso';
    case Bloqueada = 'bloqueada';
    case Hecha = 'hecha';
    case Descartada = 'descartada';

    /**
     * A qué estados se puede pasar desde éste.
     *
     * Se permite reabrir lo cerrado, como en implantaciones: una tarea que se da
     * por hecha y luego se comprueba que no lo estaba vuelve a `en_curso`, y esa
     * vuelta atrás es justo lo que el histórico tiene que poder enseñar.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Pendiente => [self::EnCurso, self::Bloqueada, self::Hecha, self::Descartada],
            self::EnCurso => [self::Pendiente, self::Bloqueada, self::Hecha, self::Descartada],
            self::Bloqueada => [self::Pendiente, self::EnCurso, self::Hecha, self::Descartada],
            self::Hecha => [self::EnCurso, self::Pendiente],
            self::Descartada => [self::Pendiente, self::EnCurso],
        };
    }

    public function permite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /** Cerrada: ni cuenta como pendiente ni puede vencer. */
    public function esCerrada(): bool
    {
        return $this === self::Hecha || $this === self::Descartada;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnCurso => 'En curso',
            self::Bloqueada => 'Bloqueada',
            self::Hecha => 'Hecha',
            self::Descartada => 'Descartada',
        };
    }

    /**
     * El icono con el que se reconoce sin leer la etiqueta.
     *
     * El nombre es de `@lucide/vue` y lo resuelve `IconoTipo` en el cliente. Va
     * en el enum y no en el mapa de tonos porque el tono se comparte: el azul de
     * `planificado` es «Planificado» en una implantación y «Bloqueada» en una
     * tarea, y un icono por tono mentiría en una de las dos.
     */
    public function icono(): string
    {
        return match ($this) {
            self::Pendiente => 'Circle',
            self::EnCurso => 'CircleDotDashed',
            // Parada por algo que no depende de quien la tiene.
            self::Bloqueada => 'Ban',
            self::Hecha => 'CircleCheck',
            self::Descartada => 'CircleSlash',
        };
    }

    /**
     * El tono del dominio con el que se pinta, que no es un color.
     *
     * Se reutiliza el vocabulario de `--estado-*` que ya existe: una tarea en
     * curso y una implantación en progreso son la misma idea, y darles dos
     * colores distintos obligaría a aprenderse dos códigos.
     *
     * **Ninguno es rojo, y eso es deliberado.** El rojo (`caducada`) lo tiene la
     * columna de plazo, donde significa lo mismo que en evidencias: algo que ya
     * ha vencido. Gastarlo además en `bloqueada` dejaría la tabla en rojo por dos
     * motivos distintos y el plazo dejaría de saltar a la vista, que es la única
     * razón por la que se pinta de rojo. Bloqueada va en el azul de
     * `planificado`: está aparcada, no incumplida.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Pendiente => 'no_iniciado',
            self::EnCurso => 'en_progreso',
            self::Bloqueada => 'planificado',
            self::Hecha => 'implantado',
            self::Descartada => 'no_aplica',
        };
    }
}
