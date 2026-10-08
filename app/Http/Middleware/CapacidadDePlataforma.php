<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Plataforma\Enums\CapacidadPlataforma;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cada ruta de `/plataforma` exige una capacidad (punto 48): `plataforma:clientes.ver`.
 *
 * Va detrás de `SoloPlataforma`, que dice si es de la plataforma; esto dice si
 * su perfil le deja hacer esto en concreto. Una capacidad que no existe es un
 * error de programación y revienta, no un 403 silencioso.
 */
class CapacidadDePlataforma
{
    public function handle(Request $request, Closure $next, string $capacidad): Response
    {
        /** @var ?User $usuario */
        $usuario = $request->user();

        abort_unless($usuario?->puedeEnPlataforma(CapacidadPlataforma::from($capacidad)) === true, 403);

        return $next($request);
    }
}
