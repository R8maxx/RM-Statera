<?php

declare(strict_types=1);

namespace App\Domain\Objetivo;

use App\Domain\Objetivo\Models\Objetivo;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea una actuación de un objetivo y la deja vinculada.
 *
 * Es el hermano de `AbrirAccionCorrectiva` y de `CrearTarea::desdeImplantacion()`,
 * y vive aquí y no en `Domain\Tarea` por la dirección de la dependencia: este
 * módulo sabe de tareas —las usa como «qué se hará»— y el plan de acción no tiene
 * por qué saber de objetivos de seguridad.
 *
 * **El origen se pone, no se pregunta.** Preguntarlo invita a cambiarlo, y una
 * actuación marcada «iniciativa propia» pierde justo lo que la hacía trazable.
 * Éste es el camino **normal** y no el único: `OrigenTarea::Objetivo` sí está en
 * `disponibles()` —su módulo existe, que es lo que esa lista decide—, así que una
 * tarea puede nacer suelta con ese origen y vincularse después. Lo que este
 * camino garantiza es que la que nace aquí llega ya vinculada.
 *
 * Todo en una transacción: una tarea creada y sin vincular sería trabajo suelto
 * que no hace avanzar ningún objetivo y que nadie relacionaría con nada.
 */
final class AbrirActuacion
{
    public function __construct(
        private readonly CrearTarea $crearTarea,
        private readonly VincularActuacion $vinculos,
    ) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(Objetivo $objetivo, array $atributos, ?User $autor = null): Tarea
    {
        return DB::transaction(function () use ($objetivo, $atributos, $autor): Tarea {
            $tarea = ($this->crearTarea)([
                ...$atributos,
                'origen' => OrigenTarea::Objetivo->value,
                'estado' => EstadoTarea::Pendiente->value,
            ], $autor);

            $this->vinculos->vincular($objetivo, $tarea, $autor);

            return $tarea->refresh();
        });
    }
}
