<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion;

use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea una decisión de la revisión y la deja vinculada (9.3.3).
 *
 * Hermana de `AbrirAccionCorrectiva`, `AbrirActuacion` y `AbrirActuacionDeMejora`,
 * y vive aquí por la dirección de la dependencia: este módulo sabe de tareas y el
 * plan de acción no tiene por qué saber de revisiones por la dirección.
 *
 * **El origen se pone, no se pregunta**, y es `OrigenTarea::RevisionDireccion` —el
 * que llevaba desde la primera migración declarado y sin ofrecerse, esperando
 * justamente a este módulo—.
 *
 * Todo en una transacción: una tarea creada y sin vincular sería una decisión que
 * el acta no recoge y que la revisión siguiente no comprobará.
 */
final class AbrirDecision
{
    public function __construct(
        private readonly CrearTarea $crearTarea,
        private readonly VincularDecision $vinculos,
    ) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(RevisionDireccion $revision, array $atributos, ?User $autor = null): Tarea
    {
        return DB::transaction(function () use ($revision, $atributos, $autor): Tarea {
            $tarea = ($this->crearTarea)([
                ...$atributos,
                'origen' => OrigenTarea::RevisionDireccion->value,
                'estado' => EstadoTarea::Pendiente->value,
            ], $autor);

            $this->vinculos->vincular($revision, $tarea, $autor);

            return $tarea->refresh();
        });
    }
}
