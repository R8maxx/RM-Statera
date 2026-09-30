<?php

declare(strict_types=1);

namespace App\Domain\Cambio;

use App\Domain\Cambio\Models\CambioSgsi;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea una actuación de un cambio del SGSI y la deja vinculada.
 *
 * Hermana de `AbrirActuacionDeMejora`, y vive aquí por la dirección de la
 * dependencia: este módulo sabe de tareas y el plan de acción no tiene por qué
 * saber de cambios. **El origen se pone, no se pregunta**: `OrigenTarea::CambioSgsi`.
 *
 * Todo en una transacción: una tarea creada y sin vincular sería trabajo suelto.
 */
final class AbrirActuacionDeCambio
{
    public function __construct(
        private readonly CrearTarea $crearTarea,
        private readonly VincularActuacionDeCambio $vinculos,
    ) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(CambioSgsi $cambio, array $atributos, ?User $autor = null): Tarea
    {
        return DB::transaction(function () use ($cambio, $atributos, $autor): Tarea {
            $tarea = ($this->crearTarea)([
                ...$atributos,
                'origen' => OrigenTarea::CambioSgsi->value,
                'estado' => EstadoTarea::Pendiente->value,
            ], $autor);

            $this->vinculos->vincular($cambio, $tarea, $autor);

            return $tarea->refresh();
        });
    }
}
