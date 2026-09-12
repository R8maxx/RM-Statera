<?php

declare(strict_types=1);

namespace App\Domain\Tarea;

use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Alta de una tarea, con su primera línea de histórico.
 *
 * El alta deja transición —con `estado_anterior` nulo— por lo mismo que en
 * implantaciones: «desde cuándo está abierta» es la misma pregunta que «desde
 * cuándo está hecha», y contestarla con `created_at` obligaría a mirar en dos
 * sitios distintos según el caso.
 *
 * Si viene con implantaciones, se vinculan en la misma transacción: una tarea que
 * nace de un requisito pendiente y se queda sin el vínculo pierde exactamente lo
 * que la hacía trazable.
 */
final class CrearTarea
{
    public function __construct(
        private readonly RegistroTransicionesTarea $registro,
        private readonly VincularTarea $vinculos,
    ) {}

    /**
     * @param  array<string, mixed>  $atributos
     * @param  list<Implantacion>  $implantaciones
     */
    public function __invoke(array $atributos, ?User $autor = null, array $implantaciones = []): Tarea
    {
        return DB::transaction(function () use ($atributos, $autor, $implantaciones): Tarea {
            /*
             * `refresh()` y no un valor por defecto en el modelo: los de
             * `estado`, `origen` y `prioridad` los pone la base, y repetirlos
             * aquí sería tener el mismo dato en dos sitios que pueden
             * desincronizarse. Sin esto, una tarea creada sin `estado` llega al
             * registro de transiciones con el estado a nulo.
             */
            $tarea = Tarea::query()->create($atributos)->refresh();

            $this->registro->registrar($tarea, null, $tarea->estado, $autor);

            foreach ($implantaciones as $implantacion) {
                $this->vinculos->vincular($tarea, $implantacion, $autor);
            }

            return $tarea->refresh();
        });
    }

    /**
     * La tarea que nace de un requisito pendiente, con el origen ya puesto.
     *
     * Es el camino corto desde la ficha de una implantación, y el único que hoy
     * produce tareas con origen trazable: crear una desde cero y vincularla
     * después deja el mismo resultado, pero nadie lo hace y el campo `origen`
     * acabaría diciendo «propia» en todas.
     *
     * @param  array<string, mixed>  $atributos
     */
    public function desdeImplantacion(
        Implantacion $implantacion,
        array $atributos,
        ?User $autor = null,
    ): Tarea {
        return $this(
            [...$atributos, 'estado' => EstadoTarea::Pendiente->value],
            $autor,
            [$implantacion],
        );
    }
}
