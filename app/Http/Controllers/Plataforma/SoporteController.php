<?php

declare(strict_types=1);

namespace App\Http\Controllers\Plataforma;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Excepciones\SoporteNoPermitido;
use App\Domain\Plataforma\Soporte\AccesoDeSoporte;
use App\Domain\Plataforma\Soporte\SesionDeSoporte;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Entrar en un cliente como soporte y salir (punto 44).
 *
 * La regla vive en `AccesoDeSoporte`; aquí sólo se pone y se quita la clave de
 * la sesión, que es lo que `EstablecerContextoOrganizacion` lee en cada
 * petición para fijar el contexto.
 */
class SoporteController extends Controller
{
    public function entrar(Request $request, Organizacion $organizacion, AccesoDeSoporte $acceso): RedirectResponse
    {
        /** @var User $administrador */
        $administrador = $request->user();

        try {
            $acceso->entrar($administrador, $organizacion);
        } catch (SoporteNoPermitido $error) {
            return back()->withErrors(['soporte' => $error->getMessage()]);
        }

        $request->session()->put(SesionDeSoporte::CLAVE, $organizacion->id);
        $request->session()->put(SesionDeSoporte::CLAVE_VENTANA, SesionDeSoporte::ventana($organizacion));

        Inertia::flash('exito', "Estás dentro de {$organizacion->nombre} como soporte, en sólo lectura.");

        return redirect()->route('panel');
    }

    public function salir(Request $request, AccesoDeSoporte $acceso): RedirectResponse
    {
        /** @var User $administrador */
        $administrador = $request->user();
        $id = $request->session()->pull(SesionDeSoporte::CLAVE);
        $request->session()->forget(SesionDeSoporte::CLAVE_VENTANA);
        $organizacion = $id === null ? null : Organizacion::query()->find((int) $id);

        if ($organizacion === null) {
            return redirect()->route('plataforma.organizaciones.index');
        }

        $acceso->salir($administrador, $organizacion);

        return redirect()->route('plataforma.organizaciones.show', $organizacion);
    }
}
