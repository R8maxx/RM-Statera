<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saca a quien ya no debería estar dentro (§ 4.19).
 *
 * El login por contraseña ya rechaza una cuenta desactivada o caducada
 * (`RechazarCuentaNoVigente`), pero hay dos caminos que no pasan por ahí: **la
 * sesión que ya estaba abierta** cuando se desactivó la cuenta o llegó su fecha
 * de fin, y **el login con passkey**, que tiene su propio controlador. Esto
 * cubre los dos en la siguiente petición.
 */
class CuentaVigente
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var ?User $usuario */
        $usuario = $request->user();

        if ($usuario === null || $usuario->estadoCuenta()->puedeEntrar()) {
            return $next($request);
        }

        $estado = $usuario->estadoCuenta();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', match ($estado->value) {
            'caducada' => 'Tu acceso terminó en la fecha prevista. Si necesitas seguir, pídele a tu contacto que lo amplíe.',
            default => 'Esta cuenta ya no tiene acceso a Statera.',
        });
    }
}
