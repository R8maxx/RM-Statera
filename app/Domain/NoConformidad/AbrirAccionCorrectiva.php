<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad;

use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea la acción correctiva de una no conformidad y la deja vinculada.
 *
 * Es el hermano de `CrearTarea::desdeImplantacion()`, y vive aquí y no allí por
 * la dirección de la dependencia: el módulo de no conformidades sabe de tareas
 * —las usa como acciones correctivas— y el plan de acción no tiene por qué saber
 * de no conformidades.
 *
 * **El origen se pone, no se pregunta.** Es lo mismo que hace
 * `/tareas/crear?implantacion={id}`: preguntarlo invita a cambiarlo, y una acción
 * correctiva marcada «iniciativa propia» pierde justo lo que la hacía trazable.
 * Y es además el único camino que produce tareas con `OrigenTarea::NoConformidad`,
 * porque ese origen no se ofrece en el formulario general de tareas.
 *
 * Todo en una transacción: una tarea creada y sin vincular sería trabajo
 * correctivo suelto que no cierra ninguna no conformidad y que nadie relacionaría
 * con nada.
 */
final class AbrirAccionCorrectiva
{
    public function __construct(
        private readonly CrearTarea $crearTarea,
        private readonly VincularAccionCorrectiva $vinculos,
    ) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(NoConformidad $noConformidad, array $atributos, ?User $autor = null): Tarea
    {
        return DB::transaction(function () use ($noConformidad, $atributos, $autor): Tarea {
            $tarea = ($this->crearTarea)([
                ...$atributos,
                'origen' => OrigenTarea::NoConformidad->value,
                'estado' => EstadoTarea::Pendiente->value,
            ], $autor);

            /*
             * Y aquí es donde se ata el segundo vínculo, el de la medida: lo hace
             * `VincularAccionCorrectiva` y no este método, para que la tarea que
             * alguien vincule más tarde desde la ficha lo tenga también.
             */
            $this->vinculos->vincular($noConformidad, $tarea, $autor);

            return $tarea->refresh();
        });
    }
}
