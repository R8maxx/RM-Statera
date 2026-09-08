<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organizacion\ContextoOrganizacion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la organización activa a partir del usuario autenticado.
 *
 * Un usuario sin organización se queda sin contexto, y sin contexto no ve nada:
 * ni el scope de Eloquent ni la política de RLS devuelven fila alguna. Es el
 * comportamiento correcto, no un caso que haya que salvar.
 */
class EstablecerContextoOrganizacion
{
    public function __construct(private readonly ContextoOrganizacion $contexto) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario?->organizacion_id !== null) {
            $this->contexto->establecer($usuario->organizacion_id);
        } else {
            $this->contexto->olvidar();
        }

        return $next($request);
    }
}
