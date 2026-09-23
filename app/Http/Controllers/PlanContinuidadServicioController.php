<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\Models\Activo;
use App\Domain\Continuidad\Excepciones\ServicioNoValido;
use App\Domain\Continuidad\VincularServicioAPlan;
use App\Domain\Documento\Models\Documento;
use App\Http\Requests\VincularServicioAPlanRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Qué servicios cubre un plan de continuidad: § 4.11.
 *
 * Sobre `MejoraController::vincularActuacion()`/`desvincularActuacion()`,
 * mismo reparto de verbos. **La diferencia está en el binding de `{activo}`**:
 * la ruta va SIN `scopeBindings()` a propósito, porque `scopeBindings()`
 * pluraliza el nombre del parámetro en inglés —`activo` → `activos`— y
 * `Documento` no tiene esa relación, tiene `serviciosCubiertos()`. Resolverlo
 * así respondería 500 con un «Call to undefined method» en vez de acotar nada.
 * Se resuelve `{activo}` por binding implícito normal: el scope global de
 * `Activo` ya lo deja dentro de la organización, y `VincularServicioAPlan`
 * comprueba en el dominio que además es un servicio de este plan concreto.
 */
class PlanContinuidadServicioController extends Controller
{
    public function store(
        VincularServicioAPlanRequest $request,
        Documento $documento,
        VincularServicioAPlan $vincular,
    ): RedirectResponse {
        $activo = Activo::query()->findOrFail($request->validated('activo_id'));

        try {
            $vincular->vincular($documento, $activo);
        } catch (ServicioNoValido $error) {
            return back()->withErrors(['activo_id' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Servicio vinculado al plan.');

        return back();
    }

    public function destroy(Documento $documento, Activo $activo, VincularServicioAPlan $vincular): RedirectResponse
    {
        $vincular->desvincular($documento, $activo);

        Inertia::flash('exito', 'Servicio desvinculado del plan.');

        return back();
    }
}
