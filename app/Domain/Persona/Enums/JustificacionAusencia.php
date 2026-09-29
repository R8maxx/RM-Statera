<?php

declare(strict_types=1);

namespace App\Domain\Persona\Enums;

/**
 * Por qué faltó alguien que estaba convocado.
 *
 * **Sólo tiene sentido en quien faltó**: la fila de alguien que asistió la lleva
 * a nulo, y un `CHECK` lo impone. Y nulo en quien faltó significa «sin indicar»,
 * que no es ninguno de los dos casos: nadie ha dicho todavía si la ausencia
 * estaba justificada, y pintarlo como «injustificada» sería afirmar algo que no
 * consta.
 *
 * **Dos casos y no un texto libre** porque lo que se cuenta es la pregunta del
 * auditor —«de los que faltaron, ¿cuántos tenían motivo?»—, y eso con un campo
 * de texto no se puede contar. El motivo va aparte, y sólo en la justificada.
 */
enum JustificacionAusencia: string
{
    case Justificada = 'justificada';
    case Injustificada = 'injustificada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Justificada => 'Justificada',
            self::Injustificada => 'Sin justificar',
        };
    }

    /** Neutro la justificada: faltó, pero con motivo, y no pide nada más. */
    public function tono(): string
    {
        return match ($this) {
            self::Justificada => 'no_iniciado',
            self::Injustificada => 'en_progreso',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Justificada => 'FileCheck',
            self::Injustificada => 'UserX',
        };
    }
}
