<?php

declare(strict_types=1);

namespace App\Domain\Mejora;

use App\Domain\Mejora\Models\Mejora;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea la actuación de una mejora y la deja vinculada.
 *
 * Hermana de `AbrirAccionCorrectiva` y de `AbrirActuacion`, y vive aquí por la
 * dirección de la dependencia: este módulo sabe de tareas y el plan de acción no
 * tiene por qué saber de mejoras.
 *
 * **El origen se pone, no se pregunta**, y es `OrigenTarea::Mejora` y no
 * `NoConformidad`: una acción correctiva ataca la causa de algo que incumple y
 * esto materializa algo que se puede hacer mejor. El reparto por origen del plan
 * de acción existe justamente para distinguir lo reactivo de lo voluntario, y
 * colapsarlos haría que un plan lleno de mejoras se leyera como una organización
 * apagando fuegos.
 *
 * Todo en una transacción: una tarea creada y sin vincular sería trabajo suelto
 * que no materializa ninguna mejora.
 */
final class AbrirActuacionDeMejora
{
    public function __construct(
        private readonly CrearTarea $crearTarea,
        private readonly VincularActuacionDeMejora $vinculos,
    ) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(Mejora $mejora, array $atributos, ?User $autor = null): Tarea
    {
        return DB::transaction(function () use ($mejora, $atributos, $autor): Tarea {
            $tarea = ($this->crearTarea)([
                ...$atributos,
                'origen' => OrigenTarea::Mejora->value,
                'estado' => EstadoTarea::Pendiente->value,
            ], $autor);

            $this->vinculos->vincular($mejora, $tarea, $autor);

            return $tarea->refresh();
        });
    }
}
