<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Enums;

/**
 * Cómo salió una prueba de continuidad ya realizada.
 *
 * **Ninguno gasta el rojo de `caducada`, ni siquiera `Fallida`.** Es el mismo
 * argumento que ya lleva `ClasificacionIncidente` para no colorear la clase:
 * una prueba fallida es la prueba **funcionando**, porque es exactamente lo
 * que `op.cont.3` quiere descubrir mientras todavía es un simulacro y no una
 * caída real. Lo que incumple de verdad no es fallar una prueba, es no
 * probar nunca —y eso no lo pinta un color, lo pinta la ausencia de filas—.
 */
enum ResultadoPrueba: string
{
    case Superada = 'superada';
    case Parcial = 'parcial';
    case Fallida = 'fallida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Superada => 'Superada',
            self::Parcial => 'Superada parcialmente',
            self::Fallida => 'Fallida',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Superada => 'ShieldCheck',
            self::Parcial => 'ShieldAlert',
            self::Fallida => 'ShieldX',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Superada => 'implantado',
            self::Parcial => 'en_progreso',
            self::Fallida => 'en_revision',
        };
    }
}
