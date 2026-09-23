<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Continuidad\Enums\NivelImpacto;
use App\Domain\Continuidad\Enums\TramoImpacto;
use App\Domain\Continuidad\Models\BiaServicio;

/**
 * El MTPD (Maximum Tolerable Period of Disruption) de un servicio, derivado de
 * los cinco tramos de impacto y no almacenado.
 *
 * **Es el primer tramo cuyo impacto llega a `MuyAlto`.** `TramoImpacto::cases()`
 * ya está declarado en el orden correcto —el mismo que impone
 * `bia_servicios_monotonia_check` en la base—, así que basta con recorrerlo y
 * quedarse con el primero: la monotonía garantiza que, a partir de ahí, todos
 * los tramos siguientes son `MuyAlto` también.
 *
 * Guardarlo en una columna sería una copia que se desincroniza de los cinco
 * tramos que la justifican, el mismo argumento por el que la valoración
 * efectiva de un activo tampoco se almacena.
 */
final class UmbralTolerable
{
    /** El primer tramo en el que el impacto se vuelve intolerable, si lo hay. */
    public static function de(BiaServicio $bia): ?TramoImpacto
    {
        foreach (TramoImpacto::cases() as $tramo) {
            if ($bia->{$tramo->value} === NivelImpacto::MuyAlto) {
                return $tramo;
            }
        }

        return null;
    }

    /** Las horas de ese tramo, para compararlas con el RTO declarado. */
    public static function horas(BiaServicio $bia): ?int
    {
        return self::de($bia)?->horas();
    }
}
