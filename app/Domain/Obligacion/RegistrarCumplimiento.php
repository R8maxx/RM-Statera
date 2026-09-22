<?php

declare(strict_types=1);

namespace App\Domain\Obligacion;

use App\Domain\Obligacion\Excepciones\CumplimientoInvalido;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\Models\CompromisoCumplimiento;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Sella que un compromiso se cumplió, y hasta cuándo cubre.
 *
 * **`cubre_hasta` se congela aquí, con la cadencia vigente en este momento.** Es
 * la pieza que hace que subir la periodicidad de anual a semestral en marzo no
 * repinte como fuera de plazo un cumplimiento de enero que en enero estaba al día
 * — misma familia que `documento_versiones.fecha_proxima_revision`, que se calcula
 * al firmar y se queda.
 *
 * Se puede pasar una fecha de cobertura distinta, y es un caso real y no una
 * escapatoria: la ventana del INES no cae a doce meses exactos de la anterior. Lo
 * que no se admite es que cubra hasta antes de la propia fecha, que además la base
 * rechaza por `CHECK`; aquí se comprueba para dar un mensaje que se entienda en vez
 * de un error de PostgreSQL.
 *
 * **Una fecha futura tampoco entra.** Un cumplimiento es un hecho, no una
 * previsión: lo que todavía no ha pasado es lo que el compromiso ya está pintando
 * en el calendario.
 */
final class RegistrarCumplimiento
{
    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(
        Compromiso $compromiso,
        Carbon $fecha,
        array $atributos = [],
        ?User $usuario = null,
        ?Carbon $cubreHasta = null,
    ): CompromisoCumplimiento {
        if ($fecha->isAfter(Carbon::today())) {
            throw CumplimientoInvalido::enElFuturo($fecha);
        }

        $cobertura = $cubreHasta ?? $compromiso->cadencia()->despuesDe($fecha);

        if (! $cobertura->isAfter($fecha)) {
            throw CumplimientoInvalido::coberturaAnterior($fecha, $cobertura);
        }

        $cumplimiento = $compromiso->cumplimientos()->create([
            ...$atributos,
            'fecha' => $fecha,
            'cubre_hasta' => $cobertura,
            'registrado_por_id' => $usuario?->id,
        ]);

        return $cumplimiento->refresh();
    }
}
