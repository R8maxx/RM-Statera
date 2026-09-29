<?php

declare(strict_types=1);

namespace App\Domain\Obligacion;

use App\Domain\Obligacion\Enums\TramoCiclo;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\Models\CompromisoCumplimiento;
use Illuminate\Support\Carbon;

/**
 * La vida de un compromiso partida en tramos que no se pisan.
 *
 * El histórico dice cuándo se cumplió; esto dice **si entre cumplimiento y
 * cumplimiento hubo un hueco**, que es lo que el auditor busca al mirarlo y lo
 * que una lista de fechas obliga a calcular de cabeza.
 *
 * **Sigue la misma regla que `Compromiso::PROXIMA`**, y el final del último
 * tramo cubierto es siempre `proximaFecha()`: si divergieran, la barra diría
 * «cubierto» donde la tabla dice «fuera de plazo». Por eso el primer plazo
 * —de `computa_desde` a una cadencia— sólo cuenta mientras no hay
 * cumplimientos, y se corta en el primero: a partir de ahí manda el
 * `cubre_hasta` de cada uno, igual que en el SQL.
 *
 * Los cumplimientos se solapan con frecuencia —se cumple antes de que venza el
 * anterior—, y cada tramo empieza donde acabó lo ya cubierto para que la barra
 * no pinte dos veces el mismo día.
 */
final class CicloCompromiso
{
    /**
     * @return list<array{tipo: TramoCiclo, desde: string, hasta: string, dias: int, cumplimiento: ?int}>
     */
    public function __invoke(Compromiso $compromiso, ?Carbon $hoy = null): array
    {
        $hoy = ($hoy ?? Carbon::today())->copy()->startOfDay();
        $inicio = $compromiso->computa_desde->copy()->startOfDay();
        $primerVencimiento = $compromiso->cadencia()->despuesDe($inicio);

        $cumplimientos = $compromiso->cumplimientos
            ->sortBy(fn (CompromisoCumplimiento $cumplimiento): string => $cumplimiento->fecha->toDateString())
            ->values();

        $tramos = [];
        $primero = $cumplimientos->first();

        // Hasta dónde llega lo cubierto. Sin cumplimientos, el primer plazo entero.
        $cubierto = $primero === null
            ? $primerVencimiento
            : $primerVencimiento->copy()->min($primero->fecha->copy()->startOfDay());

        if ($cubierto->greaterThan($inicio)) {
            $tramos[] = $this->tramo(TramoCiclo::Plazo, $inicio, $cubierto);
        }

        foreach ($cumplimientos as $cumplimiento) {
            $fecha = $cumplimiento->fecha->copy()->startOfDay();
            $hasta = $cumplimiento->cubre_hasta->copy()->startOfDay();

            if ($fecha->greaterThan($cubierto)) {
                $tramos[] = $this->tramo(TramoCiclo::SinCubrir, $cubierto, $fecha);
                $cubierto = $fecha;
            }

            $desde = $fecha->max($cubierto);

            if ($hasta->greaterThan($desde)) {
                $tramos[] = $this->tramo(TramoCiclo::Cubierto, $desde, $hasta, $cumplimiento->id);
                $cubierto = $hasta;
            }
        }

        // Retirado, deja de deber: lo que no se hizo después no es un hueco.
        if ($compromiso->activo && $hoy->greaterThan($cubierto)) {
            $tramos[] = $this->tramo(TramoCiclo::Vencido, $cubierto, $hoy);
        }

        return $tramos;
    }

    /**
     * @return array{tipo: TramoCiclo, desde: string, hasta: string, dias: int, cumplimiento: ?int}
     */
    private function tramo(TramoCiclo $tipo, Carbon $desde, Carbon $hasta, ?int $cumplimiento = null): array
    {
        return [
            'tipo' => $tipo,
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'dias' => (int) $desde->diffInDays($hasta),
            'cumplimiento' => $cumplimiento,
        ];
    }
}
