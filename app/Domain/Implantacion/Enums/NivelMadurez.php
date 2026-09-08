<?php

declare(strict_types=1);

namespace App\Domain\Implantacion\Enums;

/**
 * Escala de madurez L0–L5 del CCN, la que pide el informe INES.
 *
 * No es lo mismo que el estado: una medida puede estar `implantado` con madurez
 * L2 (reproducible pero informal), y el INES pregunta por lo segundo.
 */
enum NivelMadurez: string
{
    case L0 = 'l0';
    case L1 = 'l1';
    case L2 = 'l2';
    case L3 = 'l3';
    case L4 = 'l4';
    case L5 = 'l5';

    public function valor(): int
    {
        return (int) substr($this->value, 1);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::L0 => 'L0 — Inexistente',
            self::L1 => 'L1 — Inicial / ad hoc',
            self::L2 => 'L2 — Reproducible pero intuitivo',
            self::L3 => 'L3 — Proceso definido',
            self::L4 => 'L4 — Gestionado y medible',
            self::L5 => 'L5 — Optimizado',
        };
    }
}
