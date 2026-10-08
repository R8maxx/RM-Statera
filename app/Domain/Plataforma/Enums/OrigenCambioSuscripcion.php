<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Quién cambió la suscripción (punto 51).
 *
 * Hasta aquí sólo la cambiaba la plataforma. Ahora la organización contrata
 * por su cuenta los planes que la plataforma marca como contratables, y el
 * histórico tiene que decir cuál de las dos lo hizo sin que haga falta mirar
 * si la cuenta era de la plataforma: una cuenta puede ser las dos cosas
 * (punto 45).
 */
#[TypeScript]
enum OrigenCambioSuscripcion: string
{
    case Plataforma = 'plataforma';
    case Organizacion = 'organizacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Plataforma => 'Equipo de Statera',
            self::Organizacion => 'La organización',
        };
    }
}
