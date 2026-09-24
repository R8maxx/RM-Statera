<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Usuario\Models\CuentaSistema;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la organización activa a partir del usuario autenticado.
 *
 * Un usuario sin organización se queda sin contexto, y sin contexto no ve nada:
 * ni el scope de Eloquent ni la política de RLS devuelven fila alguna. Es el
 * comportamiento correcto, no un caso que haya que salvar.
 *
 * Y fija también el **alcance** de la cuenta (§ 4.19): los sistemas que ve el
 * auditor externo. Va aquí y no en otro middleware por lo mismo que el «team»
 * de permisos va dentro de `ContextoOrganizacion`: que no exista un camino que
 * fije la organización y se olvide del alcance. Se lee después de fijar la
 * organización porque `cuenta_sistemas` está bajo RLS.
 */
class EstablecerContextoOrganizacion
{
    public function __construct(private readonly ContextoOrganizacion $contexto) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario?->organizacion_id !== null) {
            $this->contexto->establecer($usuario->organizacion_id);

            /** @var list<int> $sistemas */
            $sistemas = CuentaSistema::query()->where('user_id', $usuario->id)->pluck('sistema_id')->all();
            $this->contexto->acotarASistemas($sistemas);
        } else {
            $this->contexto->olvidar();
        }

        return $next($request);
    }
}
