<?php

declare(strict_types=1);

namespace App\Domain\Proveedor;

use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Abre una tarea desde un proveedor: lo que una evaluación deja pendiente.
 *
 * Doble rastro, como la de una prueba de continuidad: el origen `proveedor` en
 * la tarea y la fila en `proveedor_tarea`, que es de donde sale «qué hay abierto
 * con este proveedor». La dependencia va de proveedores a tareas; el plan de
 * acción no sabe de proveedores.
 */
final class DerivarTareaDeProveedor
{
    public function __construct(private readonly CrearTarea $crearTarea) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(Proveedor $proveedor, array $atributos, User $autor): Tarea
    {
        return DB::transaction(function () use ($proveedor, $atributos, $autor): Tarea {
            $tarea = ($this->crearTarea)([
                ...$atributos,
                'origen' => OrigenTarea::Proveedor->value,
                'estado' => EstadoTarea::Pendiente->value,
            ], $autor);

            $proveedor->tareas()->syncWithoutDetaching([
                $tarea->id => [
                    'organizacion_id' => $proveedor->organizacion_id,
                    'vinculada_por_id' => $autor->id,
                    'created_at' => Carbon::now(),
                ],
            ]);

            return $tarea->refresh();
        });
    }
}
