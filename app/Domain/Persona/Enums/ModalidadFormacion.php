<?php

declare(strict_types=1);

namespace App\Domain\Persona\Enums;

/**
 * Cómo se impartió una sesión.
 *
 * «En línea» cubre tanto la sesión en directo por videollamada como el curso a
 * su ritmo en una plataforma: para `mp.per.3` y `mp.per.4` la diferencia la
 * marca la prueba —hoja de firmas o registro de la plataforma—, no la etiqueta.
 */
enum ModalidadFormacion: string
{
    case Presencial = 'presencial';
    case EnLinea = 'en_linea';
    case Mixta = 'mixta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Presencial => 'Presencial',
            self::EnLinea => 'En línea',
            self::Mixta => 'Mixta',
        };
    }
}
