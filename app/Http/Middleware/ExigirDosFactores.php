<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nadie escribe sin segundo factor.
 *
 * Requisito no funcional del § 6: la herramienta entra en el alcance del propio
 * SGSI, así que una contraseña robada no puede bastar para tocar el inventario
 * de activos ni las evidencias de una organización.
 *
 * Sólo afecta a quien puede escribir. A un auditor —rol de sólo lectura— no se
 * le pide: no puede alterar nada, y exigírselo sería fricción sin ganancia.
 *
 * Va sobre las rutas de escritura y NUNCA sobre `/perfil`, que es donde se
 * activa: bloquear la única salida sería dejar a la persona encerrada fuera.
 */
class ExigirDosFactores
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('seguridad.exigir_dos_factores')) {
            return $next($request);
        }

        /** @var ?User $usuario */
        $usuario = $request->user();

        if ($usuario === null || $usuario->dosFactoresConfirmado() || ! $this->puedeEscribir($usuario)) {
            return $next($request);
        }

        Inertia::flash(
            'error',
            'Activa la verificación en dos pasos antes de escribir: la herramienta contiene el inventario y las evidencias de la organización.',
        );

        return redirect()->route('perfil');
    }

    private function puedeEscribir(User $usuario): bool
    {
        foreach (Permiso::deEscritura() as $permiso) {
            if ($usuario->can($permiso->value)) {
                return true;
            }
        }

        return false;
    }
}
