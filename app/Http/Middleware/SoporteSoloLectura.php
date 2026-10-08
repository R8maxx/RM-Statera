<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Plataforma\Soporte\SesionDeSoporte;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El soporte mira y no toca (punto 44).
 *
 * Es el segundo cerrojo. El primero es el `Gate::before` de
 * `AppServiceProvider`, que a quien administra la plataforma dentro de un
 * cliente sólo le concede los permisos `.ver`. Éste corta además cualquier
 * petición que no sea segura, venga por donde venga: una ruta sin `can:`, un
 * permiso mal clasificado o un endpoint que nadie revisó.
 *
 * **Sólo se le deja escribir lo suyo**: salir del soporte, cerrar sesión, su
 * propia cuenta y la propia plataforma. Nada de eso es un dato del cliente.
 */
class SoporteSoloLectura
{
    /** @var list<string> */
    private const LO_SUYO = [
        'logout',
        'plataforma',
        'plataforma/*',
        'perfil',
        'perfil/*',
        'user/*',
    ];

    public function __construct(private readonly ContextoOrganizacion $contexto) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var ?User $usuario */
        $usuario = $request->user();

        if (! SesionDeSoporte::activo($usuario, $this->contexto)
            || $request->isMethodSafe()
            || $request->is(...self::LO_SUYO)) {
            return $next($request);
        }

        abort(403, 'El acceso de soporte es de sólo lectura.');
    }
}
