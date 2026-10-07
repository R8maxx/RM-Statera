<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Soporte\SesionDeSoporte;
use App\Domain\Usuario\Models\CuentaSistema;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
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

        if ($usuario?->esPlataforma() === true) {
            return $this->comoPlataforma($request, $next);
        }

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

    /**
     * Quien administra la plataforma no tiene organización: sin ventana de
     * soporte se queda sin contexto y no ve nada de ningún cliente (punto 44).
     *
     * **Con ventana, el contexto se fija sobre la organización que la abrió**,
     * igual que para una cuenta suya, y las tres capas siguen aplicando. No se
     * acota a sistemas: el soporte ve la organización entera, en lectura. Y se
     * comprueba en cada petición, no sólo al entrar: si el cliente cierra la
     * puerta o se acaba el plazo, se sale en la siguiente.
     */
    private function comoPlataforma(Request $request, Closure $next): Response
    {
        $this->contexto->olvidar();

        $id = $request->hasSession() ? $request->session()->get(SesionDeSoporte::CLAVE) : null;

        if ($id === null) {
            return $next($request);
        }

        $organizacion = Organizacion::query()->find((int) $id);

        if ($organizacion === null || ! $organizacion->soporteAbierto()) {
            $request->session()->forget(SesionDeSoporte::CLAVE);
            Inertia::flash('error', 'El acceso de soporte se ha cerrado: la organización lo cerró o se acabó el plazo.');

            return redirect()->route('plataforma.organizaciones.index');
        }

        $this->contexto->establecer($organizacion);

        return $next($request);
    }
}
