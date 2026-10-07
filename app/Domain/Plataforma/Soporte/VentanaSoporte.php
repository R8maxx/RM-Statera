<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Soporte;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Excepciones\SoporteNoPermitido;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * El cliente abre o cierra la puerta a la plataforma (punto 44).
 *
 * **Sólo desde su propio contexto**, como cualquier cambio de la ficha: la
 * traza del tenant registra cada apertura y cada cierre, porque
 * `Organizacion::booted()` deja el evento de `soporte_hasta`. Una escritura
 * que cruzara la frontera la tumbaría RLS al dejarlo.
 *
 * Abrir es poner una fecha de fin: la ventana se cierra sola al pasar, aunque
 * nadie se acuerde. Cerrar antes es borrarla, y quien estuviera dentro sale en
 * su siguiente petición.
 */
final class VentanaSoporte
{
    public const HORAS_MINIMAS = 1;

    public const HORAS_MAXIMAS = 24 * 7;

    public const HORAS_POR_DEFECTO = 72;

    public function abrir(Organizacion $organizacion, int $horas): void
    {
        if ($horas < self::HORAS_MINIMAS || $horas > self::HORAS_MAXIMAS) {
            throw SoporteNoPermitido::duracionFueraDeRango();
        }

        $organizacion->forceFill([
            'soporte_hasta' => Carbon::now()->addHours($horas),
            'soporte_abierto_por' => Auth::id(),
        ])->save();
    }

    public function cerrar(Organizacion $organizacion): void
    {
        $organizacion->forceFill(['soporte_hasta' => null, 'soporte_abierto_por' => null])->save();
    }
}
