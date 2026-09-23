<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Enums;

/**
 * Los cinco horizontes del MTPD en los que se valora el impacto de un
 * servicio caído, en orden creciente.
 *
 * **El valor del caso ES el nombre de la columna** de `bia_servicios`
 * (`impacto_4h` … `impacto_1m`): no hace falta un método `columna()` aparte
 * porque tenerlo sería una segunda fuente para el mismo dato, y
 * `array_position()` sobre `TramoImpacto::cases()` ya basta para recorrerlos
 * en orden. Es el mismo tramo que usa `bia_servicios_monotonia_check`, escrito
 * a mano en la migración.
 */
enum TramoImpacto: string
{
    case CuatroHoras = 'impacto_4h';
    case UnDia = 'impacto_1d';
    case TresDias = 'impacto_3d';
    case UnaSemana = 'impacto_1s';
    case UnMes = 'impacto_1m';

    /** Las horas del horizonte, para expresar el umbral tolerable en una cifra. */
    public function horas(): int
    {
        return match ($this) {
            self::CuatroHoras => 4,
            self::UnDia => 24,
            self::TresDias => 72,
            self::UnaSemana => 168,
            self::UnMes => 720,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::CuatroHoras => '4 horas',
            self::UnDia => '1 día',
            self::TresDias => '3 días',
            self::UnaSemana => '1 semana',
            self::UnMes => '1 mes',
        };
    }
}
