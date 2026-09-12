<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Todo lo que vence en un tramo de fechas, venga de donde venga.
 *
 * Vive en `Aviso/` y no en `Tarea/` **a propósito**: es la primera pieza del
 * calendario de obligaciones de § 4.16, que unifica todo lo periódico —revisión
 * por la dirección, auditoría interna, reevaluación de riesgos, formación,
 * pruebas de continuidad, reevaluación de proveedores, informe INES— y que
 * incluye literalmente la caducidad de evidencias. Colgarlo de tareas obligaría
 * a mudarlo el día que llegue ese módulo, o a tener dos calendarios.
 *
 * **Es también el único sitio donde se decide qué es un vencimiento.** El
 * resumen diario que sale por correo se apoya en esto mismo: si cada uno
 * consultara por su cuenta, el correo y el calendario acabarían discrepando y el
 * que se mira menos es el que se queda mal.
 */
final readonly class CalendarioVencimientos
{
    /**
     * Lo que vence entre dos fechas, ambas incluidas.
     *
     * Ordenado por fecha: un calendario agrupa por día y una lista de agenda se
     * lee de arriba abajo, y las dos quieren lo mismo.
     *
     * @return list<Vencimiento>
     */
    public function entre(Carbon $desde, Carbon $hasta): array
    {
        $vencimientos = [
            ...$this->deTareas(
                Tarea::query()
                    ->abiertas()
                    ->whereNotNull('fecha_limite')
                    ->whereDate('fecha_limite', '>=', $desde)
                    ->whereDate('fecha_limite', '<=', $hasta)
            ),
            ...$this->deEvidencias(
                Evidencia::query()
                    ->whereNotNull('fecha_caducidad')
                    ->whereDate('fecha_caducidad', '>=', $desde)
                    ->whereDate('fecha_caducidad', '<=', $hasta)
            ),
        ];

        usort($vencimientos, static fn (Vencimiento $a, Vencimiento $b): int => [$a->dia, $a->titulo] <=> [$b->dia, $b->titulo]);

        return $vencimientos;
    }

    /**
     * Las tareas abiertas que vencen, en el orden y con el tono del dominio.
     *
     * @param  Builder<Tarea>  $consulta
     * @return list<Vencimiento>
     */
    public function deTareas(Builder $consulta): array
    {
        return $this->filas($consulta, 'fecha_limite', Fuente::Tarea);
    }

    /**
     * @param  Builder<Evidencia>  $consulta
     * @return list<Vencimiento>
     */
    public function deEvidencias(Builder $consulta): array
    {
        return $this->filas($consulta, 'fecha_caducidad', Fuente::Evidencia);
    }

    /**
     * @param  Builder<Evidencia>|Builder<Tarea>  $consulta
     * @return list<Vencimiento>
     */
    private function filas(Builder $consulta, string $columna, Fuente $fuente): array
    {
        $hoy = Carbon::today();

        return $consulta
            ->with('responsable:id,name')
            ->orderBy($columna)
            ->get()
            ->map(function (Model $fila) use ($hoy, $columna, $fuente): Vencimiento {
                /** @var ?Carbon $fecha */
                $fecha = $fila->getAttribute($columna);

                // Con signo: `diffInDays` sin más devuelve siempre positivo y algo
                // vencido hace cuatro días parecería vencer dentro de cuatro, que
                // es lo contrario de lo que pasa.
                $dias = $fecha === null ? 0 : (int) $hoy->diffInDays($fecha, false);

                return new Vencimiento(
                    id: (int) $fila->getKey(),
                    fuente: $fuente,
                    titulo: (string) $fila->getAttribute('titulo'),
                    dia: $fecha?->toDateString() ?? '',
                    fecha: $fecha?->format('d/m/Y') ?? '—',
                    dias: $dias,
                    responsable: $fila->getRelationValue('responsable')?->getAttribute('name'),
                    tono: $this->tono($dias),
                );
            })
            ->values()
            ->all();
    }

    /**
     * El rojo es de lo que ya se ha pasado, aquí como en la tabla.
     *
     * Una semana es lo que cabe en un sprint: más allá, avisar de que algo vence
     * no dice nada que la fecha no diga ya.
     */
    private function tono(int $dias): string
    {
        return match (true) {
            $dias < 0 => 'caducada',
            $dias <= 7 => 'en_progreso',
            default => 'implantado',
        };
    }
}
