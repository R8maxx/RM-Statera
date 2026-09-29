<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Usuario\SesionesAbiertas;
use App\Http\Requests\CerrarSesionesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Cerrar desde «Mi cuenta» las sesiones que la cuenta tiene abiertas en otros
 * navegadores.
 *
 * **Las dos piden la contraseña** (`CerrarSesionesRequest`). Quien se sienta
 * delante de una sesión olvidada abierta podría, sin eso, echar al dueño de
 * todos sus otros navegadores y quedarse solo dentro — el mismo motivo por el
 * que el secreto del segundo factor exige reconfirmar.
 *
 * La sesión se señala por su huella y nunca por su id, que es el valor de la
 * cookie: el razonamiento está en `SesionesAbiertas`.
 */
class PerfilSesionController extends Controller
{
    public function __construct(private readonly SesionesAbiertas $sesiones) {}

    public function destroy(CerrarSesionesRequest $request, string $clave): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $cerrada = $this->sesiones->cerrar($usuario, $clave, $request->session()->getId());

        Inertia::flash(
            $cerrada ? 'exito' : 'error',
            $cerrada ? 'Sesión cerrada.' : 'Esa sesión ya no estaba abierta.',
        );

        return to_route('perfil');
    }

    public function destroyOtras(CerrarSesionesRequest $request): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $cerradas = $this->sesiones->cerrarLasDemas($usuario, $request->session()->getId());

        Inertia::flash('exito', match ($cerradas) {
            0 => 'No había otras sesiones abiertas.',
            1 => 'Cerrada la otra sesión.',
            default => "Cerradas las otras {$cerradas} sesiones.",
        });

        return to_route('perfil');
    }
}
