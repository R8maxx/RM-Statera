<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión tras un rato sin actividad (§ 6: «bloqueo por inactividad»).
 *
 * **No es lo mismo que la vida de la sesión.** `SESSION_LIFETIME` también se
 * renueva con cada petición, pero se mide sobre la cookie y cuenta cualquier
 * cosa que llegue al servidor. Aquí la marca es de la aplicación, vive en la
 * sesión y se compara en cada petición, así que el plazo es el que dice
 * `seguridad.inactividad_minutos` y no el que decida el driver.
 *
 * Una pestaña olvidada con un sondeo activo cuenta como actividad: es
 * tráfico real de la cuenta, y distinguirlo del de una persona obligaría a
 * marcar cada petición de sondeo a mano.
 */
class BloqueoPorInactividad
{
    public const CLAVE = 'statera.ultima_actividad';

    public function handle(Request $request, Closure $next): Response
    {
        $minutos = (int) config('seguridad.inactividad_minutos');

        if ($minutos <= 0 || $request->user() === null) {
            return $next($request);
        }

        $ultima = $request->session()->get(self::CLAVE);
        $ahora = now()->getTimestamp();

        if (is_int($ultima) && $ahora - $ultima > $minutos * 60) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'status',
                "Se cerró la sesión tras {$minutos} minutos sin actividad. Vuelve a entrar para seguir.",
            );
        }

        $request->session()->put(self::CLAVE, $ahora);

        return $next($request);
    }
}
