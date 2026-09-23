<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Enums\ResultadoPrueba;
use App\Domain\Continuidad\Excepciones\TransicionDePruebaNoPermitida;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Mejora\Enums\OrigenMejora;
use App\Domain\Mejora\Models\Mejora;
use App\Domain\Mejora\RegistrarMejora;
use App\Domain\NoConformidad\Enums\OrigenNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\NoConformidad\RegistrarNoConformidad;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Las tres costuras de una prueba de continuidad: § 4.11 y `op.cont.3`.
 *
 * Calca el reparto de `AbrirAccionCorrectiva` y `AbrirActuacionDeMejora`: **el
 * origen se pone, no se pregunta**, y este dominio es el que sabe de tareas, de
 * no conformidades y de mejoras —la dirección de la dependencia es la contraria,
 * ninguno de esos tres módulos sabe de continuidad—.
 *
 * **Las tres exigen la misma condición**, y la comprueba una sola vez: la prueba
 * tiene que estar `realizada` y su resultado no puede ser `superada`. Una prueba
 * que sigue `planificada` o `cancelada` no tiene resultado del que derivar nada,
 * y una `superada` no dejó nada que corregir —es el mismo argumento por el que
 * `ResultadoPrueba::Fallida` no gasta el rojo de `caducada`: fallar es la prueba
 * **funcionando**—.
 *
 * **`tarea()` deja doble rastro y las otras dos, uno solo.** La tarea entra en la
 * pivote `prueba_continuidad_tarea` —de ahí sale «cuánto trabajo dejó esta
 * prueba»—; la no conformidad se ata por `prueba_continuidad_id`, espejo exacto
 * de `incidente_id`; la mejora **no lleva clave foránea**, igual que
 * `OrigenMejora::Incidente`: la prueba ya quedó registrada con su resultado, y
 * atar la mejora sería fingir una trazabilidad que no hay.
 */
final class DerivarDePrueba
{
    public function __construct(
        private readonly CrearTarea $crearTarea,
        private readonly RegistrarNoConformidad $registrarNoConformidad,
        private readonly RegistrarMejora $registrarMejora,
    ) {}

    /**
     * @param  array<string, mixed>  $atributos
     *
     * @throws TransicionDePruebaNoPermitida
     */
    public function tarea(PruebaContinuidad $prueba, array $atributos, User $autor): Tarea
    {
        $this->comprobar($prueba);

        return DB::transaction(function () use ($prueba, $atributos, $autor): Tarea {
            $tarea = ($this->crearTarea)([
                ...$atributos,
                'origen' => OrigenTarea::Continuidad->value,
                'estado' => EstadoTarea::Pendiente->value,
            ], $autor);

            // Idempotente, como `VincularAccionCorrectiva::vincular()`: volver a
            // vincular lo mismo no es un error del usuario.
            $prueba->tareas()->syncWithoutDetaching([
                $tarea->id => [
                    'organizacion_id' => $prueba->organizacion_id,
                    'vinculada_por_id' => $autor->id,
                    'created_at' => Carbon::now(),
                ],
            ]);

            return $tarea->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $atributos
     *
     * @throws TransicionDePruebaNoPermitida
     */
    public function noConformidad(PruebaContinuidad $prueba, array $atributos, User $autor): NoConformidad
    {
        $this->comprobar($prueba);

        return ($this->registrarNoConformidad)([
            ...$atributos,
            'origen' => OrigenNoConformidad::PruebaContinuidad->value,
            'prueba_continuidad_id' => $prueba->id,
        ], $autor);
    }

    /**
     * @param  array<string, mixed>  $atributos
     *
     * @throws TransicionDePruebaNoPermitida
     */
    public function mejora(PruebaContinuidad $prueba, array $atributos, User $autor): Mejora
    {
        $this->comprobar($prueba);

        return ($this->registrarMejora)([
            ...$atributos,
            'origen' => OrigenMejora::PruebaContinuidad->value,
        ], $autor);
    }

    /**
     * @throws TransicionDePruebaNoPermitida
     */
    private function comprobar(PruebaContinuidad $prueba): void
    {
        if ($prueba->estado !== EstadoPrueba::Realizada || $prueba->resultado === ResultadoPrueba::Superada) {
            throw TransicionDePruebaNoPermitida::noDerivable();
        }
    }
}
