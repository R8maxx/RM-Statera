<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Continuidad\Excepciones\ServicioNoValido;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Edita una prueba de continuidad todavía `planificada`.
 *
 * **El estado se comprueba en el controlador, no aquí.** A diferencia de
 * `EditarBia` —que sí cambia de estado al editar un BIA aprobado—, una prueba
 * fuera de `planificada` sencillamente no se edita: no hay una transición que
 * disparar, hay una puerta cerrada, y ésa es la clase de comprobación que
 * vive en la capa HTTP, junto a la redirección con el error. Este servicio
 * asume que quien lo llama ya lo comprobó.
 *
 * **`servicios` se resincroniza entero con `sync()`**, no se amplía: quien
 * edita decide de nuevo qué servicios cubre la prueba, y `organizacion_id` se
 * pasa a mano en la pivote por lo mismo que `PlanificarPrueba` y
 * `VincularServicioAPlan` — `sync()` inserta con SQL directo y no pasa por el
 * evento `creating` del modelo.
 *
 * **No toca `rto_alcanzado_horas` ni `rpo_alcanzado_horas`.** Esas cifras son
 * de `RegistrarResultadoPrueba`, y una prueba editable —`planificada`— no las
 * tiene todavía: el `CHECK` de `pruebas_continuidad_realizada_check` ya lo
 * impide del otro lado.
 */
final class EditarPrueba
{
    /**
     * Los únicos campos que una edición puede tocar. `documento_id` queda
     * fuera por el mismo motivo que `activo_id` en `EditarBia`: una prueba no
     * cambia de plan, y `codigo` sí es editable, como en `IncidenteController`.
     *
     * @var list<string>
     */
    private const CAMPOS_EDITABLES = [
        'codigo',
        'titulo',
        'tipo',
        'fecha_prevista',
        'responsable_id',
    ];

    /**
     * @param  array<string, mixed>  $atributos
     * @param  list<int>  $servicioIds
     *
     * @throws ServicioNoValido
     * @throws InvalidArgumentException
     */
    public function __invoke(PruebaContinuidad $prueba, array $atributos, array $servicioIds): PruebaContinuidad
    {
        $this->exigirCamposEditables($atributos);

        foreach ($servicioIds as $servicioId) {
            $this->exigirServicio($servicioId);
        }

        return DB::transaction(function () use ($prueba, $atributos, $servicioIds): PruebaContinuidad {
            $prueba->update($atributos);

            $prueba->servicios()->sync(collect($servicioIds)->mapWithKeys(
                static fn (int $servicioId): array => [$servicioId => ['organizacion_id' => $prueba->organizacion_id]],
            )->all());

            return $prueba->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function exigirCamposEditables(array $atributos): void
    {
        $noPermitidos = array_diff(array_keys($atributos), self::CAMPOS_EDITABLES);

        if ($noPermitidos !== []) {
            throw new InvalidArgumentException(sprintf(
                'EditarPrueba no admite el campo «%s».',
                implode('», «', $noPermitidos),
            ));
        }
    }

    /**
     * @throws ServicioNoValido
     */
    private function exigirServicio(int $servicioId): void
    {
        $activo = Activo::query()->find($servicioId);

        if (! $activo instanceof Activo || $activo->tipo !== TipoActivo::Servicios) {
            throw ServicioNoValido::noEsServicio();
        }
    }
}
