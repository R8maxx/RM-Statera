<?php

declare(strict_types=1);

namespace App\Domain\Evidencia\Enums;

use Carbon\CarbonInterface;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cada cuánto hay que volver a obtener la evidencia.
 *
 * La bienal existe porque la conformidad del ENS se renueva cada dos años y no
 * coincide con el ciclo de tres de ISO (§ 4.16). Meter las dos en «anual» era
 * la forma de que a alguien se le pasara una de las dos.
 */
#[TypeScript]
enum PeriodicidadRenovacion: string
{
    case Mensual = 'mensual';
    case Trimestral = 'trimestral';
    case Semestral = 'semestral';
    case Anual = 'anual';
    case Bienal = 'bienal';

    public function meses(): int
    {
        return match ($this) {
            self::Mensual => 1,
            self::Trimestral => 3,
            self::Semestral => 6,
            self::Anual => 12,
            self::Bienal => 24,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Mensual => 'Mensual',
            self::Trimestral => 'Trimestral',
            self::Semestral => 'Semestral',
            self::Anual => 'Anual',
            self::Bienal => 'Bienal (conformidad ENS)',
        };
    }

    /** Cuándo caduca una evidencia obtenida en esa fecha. */
    public function caducidadDesde(CarbonInterface $obtencion): CarbonInterface
    {
        return $obtencion->copy()->addMonths($this->meses());
    }
}
