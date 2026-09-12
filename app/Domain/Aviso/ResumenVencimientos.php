<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use App\Domain\Evidencia\Models\Evidencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Qué vence en la organización activa.
 *
 * **Se cuenta con los mismos scopes que usa el panel** —`Evidencia::caducadas()`
 * y `porCaducar()`—, por el mismo motivo por el que los indicadores del
 * inventario usan `Filtro::porScope()`: con la condición escrita dos veces, el
 * día que cambie una el correo dirá 12 y la pantalla enseñará 9, y a partir de
 * ahí nadie se fía de ninguno de los dos.
 *
 * No decide a quién se avisa ni cómo: eso es del comando y de la notificación.
 * Aquí sólo se contesta a la pregunta.
 */
final readonly class ResumenVencimientos
{
    /**
     * Cuánto se mira hacia delante.
     *
     * Treinta días es lo que ya enseña el panel, y es margen suficiente para
     * renovar un certificado o volver a pedirle una captura a quien la tiene.
     */
    public const DIAS = 30;

    public function __invoke(int $dias = self::DIAS): Vencimientos
    {
        return new Vencimientos(
            caducadas: $this->filas(Evidencia::query()->caducadas()->orderBy('fecha_caducidad')),
            porCaducar: $this->filas(Evidencia::query()->porCaducar($dias)->orderBy('fecha_caducidad')),
            dias: $dias,
        );
    }

    /**
     * @param  Builder<Evidencia>  $consulta
     * @return list<EvidenciaQueVence>
     */
    private function filas(Builder $consulta): array
    {
        $hoy = Carbon::today();

        return $consulta
            ->with('responsable:id,name')
            ->get()
            ->map(fn (Evidencia $evidencia): EvidenciaQueVence => new EvidenciaQueVence(
                id: $evidencia->id,
                titulo: $evidencia->titulo,
                fecha: $evidencia->fecha_caducidad?->format('d/m/Y') ?? '—',
                // Con signo: `diffInDays` sin más devuelve siempre positivo y una
                // evidencia caducada hace cuatro días parecería vencer dentro de
                // cuatro, que es lo contrario de lo que pasa.
                dias: $evidencia->fecha_caducidad === null
                    ? 0
                    : (int) $hoy->diffInDays($evidencia->fecha_caducidad, false),
                responsable: $evidencia->responsable?->name,
            ))
            ->values()
            ->all();
    }
}
