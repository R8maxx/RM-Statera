<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use Illuminate\Support\Carbon;

/**
 * La rejilla de un mes: seis semanas de lunes a domingo.
 *
 * **Se calcula en el servidor y no en el navegador**, y no por gusto: aquí hay
 * un test que la recorre —meses de 28, 30 y 31 días, bisiestos, meses que
 * empiezan en domingo, cambios de año— y en `resources/js` no hay con qué
 * probarla. La aritmética de fechas es justo la clase de código donde un fallo
 * se ve tarde y mal, así que vive donde se puede clavar.
 *
 * Seis semanas siempre, aunque un mes quepa en cinco: una rejilla que cambia de
 * alto al pasar de mes hace saltar la página bajo el cursor.
 *
 * De lunes a domingo, que es como se lee un calendario en España. El fin de
 * semana va marcado porque un plazo que cae en sábado casi nunca es un plazo.
 */
final readonly class RejillaMes
{
    private const SEMANAS = 6;

    /**
     * @param  list<array{dia: string, numero: int, delMes: bool, esHoy: bool, finDeSemana: bool}>  $dias
     */
    private function __construct(
        public string $mes,
        public string $etiqueta,
        public string $anterior,
        public string $siguiente,
        public string $primerDia,
        public string $ultimoDia,
        public array $dias,
    ) {}

    /**
     * @param  string  $mes  en formato `Y-m`. Lo que no se entienda es el mes actual.
     */
    public static function de(string $mes, ?Carbon $hoy = null): self
    {
        $hoy ??= Carbon::today();
        $primero = self::interpretar($mes, $hoy);

        // Lunes de la semana en la que cae el día 1, que es donde arranca la
        // rejilla aunque ese lunes sea del mes anterior.
        $inicio = $primero->copy()->startOfWeek(Carbon::MONDAY);

        $dias = [];

        for ($i = 0; $i < self::SEMANAS * 7; $i++) {
            $dia = $inicio->copy()->addDays($i);

            $dias[] = [
                'dia' => $dia->toDateString(),
                'numero' => $dia->day,
                'delMes' => $dia->month === $primero->month && $dia->year === $primero->year,
                'esHoy' => $dia->isSameDay($hoy),
                'finDeSemana' => $dia->isWeekend(),
            ];
        }

        return new self(
            mes: $primero->format('Y-m'),
            etiqueta: self::nombre($primero),
            anterior: $primero->copy()->subMonthNoOverflow()->format('Y-m'),
            siguiente: $primero->copy()->addMonthNoOverflow()->format('Y-m'),
            // Los extremos de la rejilla, no los del mes: el calendario enseña
            // los días de relleno y lo que caiga en ellos también cuenta.
            primerDia: $inicio->toDateString(),
            ultimoDia: $inicio->copy()->addDays(self::SEMANAS * 7 - 1)->toDateString(),
            dias: $dias,
        );
    }

    /**
     * Un mes que no se entiende es el mes de hoy.
     *
     * Mismo criterio que los extremos de un rango de fechas en los filtros: lo
     * que llega por la query string no puede reventar la consulta, y un 500 en
     * una URL que la gente comparte es peor que enseñar otro mes.
     */
    private static function interpretar(string $mes, Carbon $hoy): Carbon
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) !== 1) {
            return $hoy->copy()->startOfMonth();
        }

        [$anio, $numero] = array_map(intval(...), explode('-', $mes));

        return Carbon::create($anio, $numero, 1)?->startOfMonth() ?? $hoy->copy()->startOfMonth();
    }

    /** «Septiembre de 2026», con la inicial en mayúscula. */
    private static function nombre(Carbon $primero): string
    {
        return ucfirst($primero->locale('es')->isoFormat('MMMM [de] YYYY'));
    }
}
