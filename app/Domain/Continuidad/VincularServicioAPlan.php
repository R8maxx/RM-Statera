<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Continuidad\Excepciones\ServicioNoValido;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;

/**
 * Qué servicios cubre un plan de continuidad.
 *
 * **Comprueba las dos fronteras antes de escribir nada, en el dominio y no
 * sólo en el `FormRequest`**: que el documento es de tipo `plan_continuidad`
 * y que el activo es un servicio. Es el mismo razonamiento que `RegistrarBia`
 * ya aplica del lado del activo — la regla vale igual para lo que llegue por
 * un importador el día que exista uno, y la base no puede imponer ninguna de
 * las dos con un `CHECK`: comprobar una columna de otra tabla desde una
 * restricción no es portable.
 *
 * **Idempotente, como el resto de su familia** (`VincularActuacionDeMejora`,
 * `VincularAccionCorrectiva`): vincular dos veces el mismo servicio no
 * duplica la fila. `syncWithoutDetaching()` sobre la pivote lo garantiza, y
 * `organizacion_id` se rellena a mano con la del documento —la misma trampa
 * que `ActivoController::sincronizarAlcance()`—: `attach()`/`sync()` insertan
 * con SQL directo y no pasan por el evento `creating` del modelo, así que sin
 * esto RLS rechazaría la fila con un error que no menciona la palabra
 * «organización».
 */
final class VincularServicioAPlan
{
    /**
     * @throws ServicioNoValido
     */
    public function vincular(Documento $documento, Activo $activo): void
    {
        if ($documento->tipo !== TipoDocumento::PlanContinuidad) {
            throw ServicioNoValido::noEsPlan();
        }

        if ($activo->tipo !== TipoActivo::Servicios) {
            throw ServicioNoValido::noEsServicio();
        }

        $documento->serviciosCubiertos()->syncWithoutDetaching([
            $activo->id => ['organizacion_id' => $documento->organizacion_id],
        ]);
    }

    /**
     * Suelta el servicio del plan, y no borra nada más.
     *
     * El BIA del servicio y su histórico siguen existiendo: desvincular dice
     * «este plan ya no cubre este servicio», no «este servicio deja de
     * analizarse».
     */
    public function desvincular(Documento $documento, Activo $activo): void
    {
        $documento->serviciosCubiertos()->detach($activo->id);
    }
}
