<?php

declare(strict_types=1);

namespace App\Domain\Usuario\Autenticacion;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Paso del login de Fortify que rechaza una cuenta que ya no tiene acceso.
 *
 * Va **antes del segundo factor**: sin él, una cuenta desactivada con 2FA
 * llegaría a la pantalla del código, que es decirle que la contraseña era
 * buena. Y **sólo habla si la contraseña es la buena**, por lo mismo al revés:
 * contestar «esta cuenta está desactivada» a quien prueba correos al azar es
 * decirle qué correos existen.
 *
 * La cuenta se busca por el proveedor del guard, igual que el resto de la
 * tubería de Fortify, y no con un `User::query()`: aquí todavía no se sabe de
 * qué organización es nadie.
 */
final class RechazarCuentaNoVigente
{
    public function __construct(private readonly StatefulGuard $guard) {}

    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $provider = $this->guard->getProvider();
        $credenciales = [
            Fortify::username() => $request->input(Fortify::username()),
            'password' => $request->input('password'),
        ];

        $cuenta = $provider->retrieveByCredentials([Fortify::username() => $credenciales[Fortify::username()]]);

        if ($cuenta instanceof User
            && $provider->validateCredentials($cuenta, $credenciales)
            && ! $cuenta->estadoCuenta()->puedeEntrar()) {
            throw ValidationException::withMessages([
                Fortify::username() => match ($cuenta->estadoCuenta()->value) {
                    'caducada' => 'Tu acceso terminó en la fecha prevista. Si necesitas seguir, pídele a tu contacto que lo amplíe.',
                    'invitada' => 'Esta cuenta todavía no ha aceptado su invitación: fija la contraseña desde el enlace del correo.',
                    default => 'Esta cuenta ya no tiene acceso a Statera.',
                },
            ]);
        }

        return $next($request);
    }
}
