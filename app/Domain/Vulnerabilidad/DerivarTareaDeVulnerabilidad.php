<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad;

use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Abre la tarea de remediar una vulnerabilidad: aplicar el parche, cambiar la
 * configuración, sustituir el equipo sin soporte.
 *
 * Doble rastro, como la de un proveedor: el origen `vulnerabilidad` y la fila en
 * `vulnerabilidad_tarea`. **Si no se dice otra fecha, la tarea vence cuando
 * vence el plazo de remediación**: es la fecha que la organización ya se
 * comprometió a cumplir, y dejarla en blanco sería perderla por el camino.
 */
final class DerivarTareaDeVulnerabilidad
{
    public function __construct(private readonly CrearTarea $crearTarea) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(Vulnerabilidad $vulnerabilidad, array $atributos, User $autor): Tarea
    {
        return DB::transaction(function () use ($vulnerabilidad, $atributos, $autor): Tarea {
            $tarea = ($this->crearTarea)([
                ...$atributos,
                'fecha_limite' => $atributos['fecha_limite'] ?? $vulnerabilidad->fecha_limite?->toDateString(),
                'origen' => OrigenTarea::Vulnerabilidad->value,
                'estado' => EstadoTarea::Pendiente->value,
            ], $autor);

            $vulnerabilidad->tareas()->syncWithoutDetaching([
                $tarea->id => [
                    'organizacion_id' => $vulnerabilidad->organizacion_id,
                    'vinculada_por_id' => $autor->id,
                    'created_at' => Carbon::now(),
                ],
            ]);

            return $tarea->refresh();
        });
    }
}
