<?php

declare(strict_types=1);

namespace App\Domain\Comunicacion\Enums;

/**
 * Si la organización comunicó algo o se lo comunicaron.
 *
 * **Las dos cosas en el mismo registro** porque son la misma conversación con la
 * misma parte interesada, y la 7.4 y la 9.3.2 e) las miran juntas: lo emitido
 * cumple el plan de comunicación, lo recibido es la retroalimentación que la
 * revisión por la dirección tiene que tener delante.
 */
enum SentidoComunicacion: string
{
    case Emitida = 'emitida';
    case Recibida = 'recibida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Emitida => 'Emitida',
            self::Recibida => 'Recibida',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Emitida => 'LogOut',
            self::Recibida => 'LogIn',
        };
    }

    /**
     * Neutros los dos: el sentido no dice si algo va bien o mal, dice hacia
     * dónde fue. Un color de estado aquí se leería como un juicio.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Emitida => 'planificado',
            self::Recibida => 'no_iniciado',
        };
    }
}
