<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/plataforma` es sólo de quien administra la plataforma (punto 41).
 *
 * No es un permiso de spatie: los permisos viven en el «team» de una
 * organización, y el administrador no pertenece a ninguna. Una cuenta de
 * cliente recibe 403, sea cual sea su rol.
 *
 * **Y siempre con segundo factor, también para leer**, como `/cuentas`: lo que
 * se ve aquí es la lista de clientes, y desde aquí se dan de alta. Lo exige
 * este middleware y no `ExigirDosFactores`, porque aquél decide por los
 * permisos de escritura del rol y el administrador no tiene rol.
 */
class SoloPlataforma
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var ?User $usuario */
        $usuario = $request->user();

        abort_unless($usuario?->esPlataforma() === true, 403);

        if (config('seguridad.exigir_dos_factores') && ! $usuario->dosFactoresConfirmado()) {
            Inertia::flash(
                'error',
                'Activa la verificación en dos pasos antes de entrar en la plataforma: desde aquí se ve y se da de alta a cada cliente.',
            );

            return redirect()->route('perfil');
        }

        return $next($request);
    }
}
