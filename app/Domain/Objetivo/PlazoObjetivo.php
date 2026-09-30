<?php

declare(strict_types=1);

namespace App\Domain\Objetivo;

use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Models\Objetivo;
use Illuminate\Support\Carbon;

/**
 * El plazo de un objetivo contado desde que se firmó, en la forma que pinta
 * `BarraPlazo`.
 *
 * **Corre desde la aprobación y no desde el alta.** Un borrador no compromete a
 * nadie, así que sin firma no hay plazo que dibujar aunque ya lleve fecha: es
 * la regla del módulo, «opcional al escribir, obligatoria al firmar». Volver a
 * `propuesto` suelta la firma y con ella la barra.
 *
 * **Sólo corre en `aprobado`**: el tramo acaba hoy mientras siga abierto, y si
 * no, el día del cierre. Los días se cuentan aquí y no con el reloj del
 * navegador, igual que en `CicloRemediacion`, para que la cifra y la barra no
 * discrepen.
 */
final class PlazoObjetivo
{
    /**
     * @return array{dias: int, detectada: string, limite: string, fin: string, finRotulo: string, corre: bool, transcurridos: int, fuera: int}|null
     */
    public function de(Objetivo $objetivo): ?array
    {
        if ($objetivo->fecha_objetivo === null || $objetivo->aprobado_en === null) {
            return null;
        }

        $inicio = $objetivo->aprobado_en->copy()->startOfDay();
        $limite = $objetivo->fecha_objetivo->copy()->startOfDay();
        $corre = $objetivo->estado === EstadoObjetivo::Aprobado;

        $fin = $corre || $objetivo->fecha_cierre === null
            ? Carbon::today()
            : $objetivo->fecha_cierre->copy()->startOfDay();
        $fin = $fin->lt($inicio) ? $inicio->copy() : $fin;

        return [
            'dias' => max(0, (int) $inicio->diffInDays($limite)),
            // La clave se llama como en `BarraPlazo`: aquí es la aprobación.
            'detectada' => $inicio->toDateString(),
            'limite' => $limite->toDateString(),
            'fin' => $fin->toDateString(),
            'finRotulo' => $corre ? 'Hoy' : $objetivo->estado->etiqueta(),
            'corre' => $corre,
            'transcurridos' => (int) $inicio->diffInDays($fin),
            'fuera' => $fin->gt($limite) ? (int) $limite->diffInDays($fin) : 0,
        ];
    }
}
