<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

use App\Domain\Activo\Models\Activo;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Riesgo\Excepciones\RiesgoSinActivos;
use App\Domain\Riesgo\Models\Riesgo;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Los dos vínculos de un riesgo: sobre qué pesa y qué lo contiene.
 *
 * Una clase y no dos porque comparten la regla que importa —«un riesgo pesa sobre
 * al menos un activo»—, y esa regla se comprueba al desvincular, no sólo al crear.
 * Separarlas dejaría `CrearRiesgo` protegiendo la entrada y nada protegiendo la
 * salida.
 *
 * La salvaguarda apunta a `implantaciones` y no a `requisitos`, que es lo que
 * distingue «el ENS pide cifrado» de «nosotros lo tenemos implantado en este
 * sistema». Y es lo que hace que un mismo control valga a la vez de prueba de
 * cumplimiento y de tratamiento de un riesgo **sin registrarlo dos veces**, que es
 * el argumento entero del producto.
 *
 * El vínculo de salvaguarda sí lleva nota, a diferencia del de activo y del de
 * tarea: hay algo que explicar sobre POR QUÉ ese control cubre este riesgo, y es
 * lo que el auditor lee cuando pregunta de dónde sale el residual.
 */
final class VincularRiesgo
{
    public function salvaguarda(Riesgo $riesgo, Implantacion $implantacion, ?string $nota = null, ?User $usuario = null): void
    {
        // Idempotente: volver a vincular lo mismo no es un error del usuario.
        $riesgo->salvaguardas()->syncWithoutDetaching([
            $implantacion->id => [
                'organizacion_id' => $riesgo->organizacion_id,
                'nota' => $nota,
                'vinculada_por_id' => $usuario?->id,
                'created_at' => Carbon::now(),
            ],
        ]);
    }

    public function desvincularSalvaguarda(Riesgo $riesgo, Implantacion $implantacion): void
    {
        $riesgo->salvaguardas()->detach($implantacion->id);
    }

    public function activo(Riesgo $riesgo, Activo $activo, ?User $usuario = null): void
    {
        $riesgo->activos()->syncWithoutDetaching([
            $activo->id => [
                'organizacion_id' => $riesgo->organizacion_id,
                'vinculado_por_id' => $usuario?->id,
                'created_at' => Carbon::now(),
            ],
        ]);
    }

    /**
     * @throws RiesgoSinActivos
     */
    public function desvincularActivo(Riesgo $riesgo, Activo $activo): void
    {
        /*
         * El último no se quita. Un riesgo sin activos no se puede puntuar —el
         * impacto sale de lo que valen— y deja el histórico de valoraciones
         * colgando de nada. Si el riesgo ya no aplica, lo que procede es cerrarlo
         * con su motivo, que es el mismo criterio por el que una tarea que no se
         * va a hacer se descarta en vez de borrarse.
         */
        if ($riesgo->activos()->count() <= 1) {
            throw RiesgoSinActivos::alDesvincular($riesgo->codigo);
        }

        $riesgo->activos()->detach($activo->id);
    }
}
