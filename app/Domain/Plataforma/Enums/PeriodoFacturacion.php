<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cada cuánto se renueva una suscripción (punto 51).
 *
 * Se modela y no se cobra, igual que el precio: decide la fecha de
 * vencimiento al contratar y el importe que se enseña, y el día que haya
 * pasarela decidirá cuándo se cobra.
 */
#[TypeScript]
enum PeriodoFacturacion: string
{
    case Mensual = 'mensual';
    case Anual = 'anual';

    public function meses(): int
    {
        return match ($this) {
            self::Mensual => 1,
            self::Anual => 12,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Mensual => 'Mensual',
            self::Anual => 'Anual',
        };
    }
}
