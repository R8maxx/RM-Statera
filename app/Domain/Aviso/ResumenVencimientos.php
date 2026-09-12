<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Qué vence en la organización activa.
 *
 * **Se cuenta con los mismos scopes que usan el panel y los filtros de cada
 * tabla** —`Evidencia::caducadas()`, `porCaducar()`, `Tarea::vencidas()`,
 * `porVencer()`—, por el mismo motivo por el que los indicadores del inventario
 * usan `Filtro::porScope()`: con la condición escrita dos veces, el día que
 * cambie una el correo dirá 12 y la pantalla enseñará 9, y a partir de ahí nadie
 * se fía de ninguno de los dos.
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
     * renovar un certificado o para hacer algo con una tarea antes de que venza.
     * La misma ventana para las dos cosas: dos listas con plazos distintos no se
     * pueden leer seguidas.
     */
    public const DIAS = 30;

    public function __invoke(int $dias = self::DIAS): Vencimientos
    {
        return new Vencimientos(
            evidenciasCaducadas: $this->filas(Evidencia::query()->caducadas()->orderBy('fecha_caducidad'), 'fecha_caducidad'),
            evidenciasPorCaducar: $this->filas(Evidencia::query()->porCaducar($dias)->orderBy('fecha_caducidad'), 'fecha_caducidad'),
            tareasVencidas: $this->filas(Tarea::query()->vencidas()->orderBy('fecha_limite'), 'fecha_limite'),
            tareasPorVencer: $this->filas(Tarea::query()->porVencer($dias)->orderBy('fecha_limite'), 'fecha_limite'),
            dias: $dias,
        );
    }

    /**
     * @param  Builder<Evidencia>|Builder<Tarea>  $consulta
     * @return list<Vencimiento>
     */
    private function filas(Builder $consulta, string $columna): array
    {
        $hoy = Carbon::today();

        return $consulta
            ->with('responsable:id,name')
            ->get()
            ->map(function (Model $fila) use ($hoy, $columna): Vencimiento {
                /** @var ?Carbon $fecha */
                $fecha = $fila->getAttribute($columna);

                return new Vencimiento(
                    id: (int) $fila->getKey(),
                    titulo: (string) $fila->getAttribute('titulo'),
                    fecha: $fecha?->format('d/m/Y') ?? '—',
                    // Con signo: `diffInDays` sin más devuelve siempre positivo y
                    // algo vencido hace cuatro días parecería vencer dentro de
                    // cuatro, que es lo contrario de lo que pasa.
                    dias: $fecha === null ? 0 : (int) $hoy->diffInDays($fecha, false),
                    responsable: $fila->getRelationValue('responsable')?->getAttribute('name'),
                );
            })
            ->values()
            ->all();
    }
}
