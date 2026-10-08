<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una organización con la suscripción vencida y sin gracia lee, pero no escribe
 * (punto 43).
 *
 * **Sólo lectura no es perder nada.** Se sigue entrando, viendo y descargando:
 * la SoA ya entregada y las evidencias que vio el auditor siguen ahí, porque son
 * del cliente. Lo que se corta es toda petición que no sea segura, en cualquier
 * ruta de la organización.
 *
 * **Salvo la cuenta propia.** Cerrar sesión, cambiar la contraseña, el segundo
 * factor o las passkeys no tocan el SGSI, y bloquearlos dejaría a alguien sin
 * poder proteger su cuenta por un impago que no es suyo. La lista va por
 * camino porque las rutas de Fortify y de passkeys no son nuestras.
 *
 * **Y salvo renovar** (punto 51). Contratar un plan es justo lo que saca a la
 * organización de sólo lectura, así que cortarlo la dejaría encerrada.
 *
 * Va detrás de `EstablecerContextoOrganizacion`: sin contexto no hay
 * organización cuya suscripción mirar, y quien administra la plataforma no se
 * ve afectado en `/plataforma`.
 */
class SuscripcionVigente
{
    /** @var list<string> */
    private const CUENTA_PROPIA = [
        'logout',
        'perfil',
        'perfil/*',
        'user/*',
    ];

    /** @var list<string> */
    private const RENOVAR = [
        'organizacion/plan',
    ];

    public function __construct(private readonly ContextoOrganizacion $contexto) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! $this->contexto->hayContexto() || $request->is(...self::CUENTA_PROPIA, ...self::RENOVAR)) {
            return $next($request);
        }

        $organizacion = Organizacion::query()->with('plan')->find($this->contexto->id());

        if ($organizacion === null || $organizacion->estadoSuscripcion()->permiteEscribir()) {
            return $next($request);
        }

        Inertia::flash(
            'error',
            'La suscripción de la organización ha vencido y Statera está en sólo lectura: puedes ver y descargar todo, pero no cambiar nada hasta que se renueve.',
        );

        return back(303);
    }
}
