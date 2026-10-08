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

        // Quien administra la plataforma y está como soporte en un cliente va
        // por su rama. Si no, es una cuenta más: con su organización si la
        // tiene (punto 45), y sin ninguna si no.
        if ($usuario?->esPlataforma() === true && $this->organizacionDeSoporte($request) !== null) {
            return $this->comoSoporte($request, $next);
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

    private function organizacionDeSoporte(Request $request): ?int
    {
        $id = $request->hasSession() ? $request->session()->get(SesionDeSoporte::CLAVE) : null;

        return $id === null ? null : (int) $id;
    }

    /**
     * Quien administra la plataforma, dentro de un cliente como soporte
     * (punto 44). Sin ventana no ve nada de ningún cliente que no sea el suyo.
     *
     * **Con ventana, el contexto se fija sobre la organización que la abrió**,
     * igual que para una cuenta suya, y las tres capas siguen aplicando. No se
     * acota a sistemas: el soporte ve la organización entera, en lectura. Y se
     * comprueba en cada petición, no sólo al entrar: si el cliente cierra la
     * puerta o se acaba el plazo, se sale en la siguiente.
     */
    private function comoSoporte(Request $request, Closure $next): Response
    {
        $this->contexto->olvidar();

        $organizacion = Organizacion::query()->find($this->organizacionDeSoporte($request));

        // La misma ventana por la que se entró, no una cualquiera abierta: si el
        // cliente la cerró y abrió otra, hay que volver a entrar.
        $mismaVentana = $organizacion !== null
            && $request->session()->get(SesionDeSoporte::CLAVE_VENTANA) === SesionDeSoporte::ventana($organizacion);

        if ($organizacion === null || ! $organizacion->soporteAbierto() || ! $mismaVentana) {
            $request->session()->forget([SesionDeSoporte::CLAVE, SesionDeSoporte::CLAVE_VENTANA]);
            Inertia::flash('error', 'El acceso de soporte se ha cerrado: la organización lo cerró o se acabó el plazo.');

            return redirect()->route('plataforma.organizaciones.index');
        }

        $this->contexto->establecer($organizacion);

        return $next($request);
    }
}
