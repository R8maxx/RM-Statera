<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Usuario\AceptarInvitacion;
use App\Domain\Usuario\EnviarInvitacion;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;
use App\Http\Requests\AceptarInvitacionRequest;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aceptar una invitación: fijar la contraseña desde el enlace del correo.
 *
 * **No reutiliza `/reset-password` de Fortify**, y no por gusto: aquel valida
 * contra el broker `users`, que caduca en una hora, y una invitación tiene que
 * durar días. Con su broker y su tabla, pedir una contraseña nueva tampoco
 * pisa una invitación pendiente.
 *
 * Tras aceptar **no se abre sesión**: se manda al login. Así la primera
 * entrada pasa por la misma tubería que todas —el rechazo de cuentas no
 * vigentes, el segundo factor, el registro de la sesión— y no por un atajo.
 */
class InvitacionController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        return Inertia::render('auth/AceptarInvitacion', [
            'email' => $request->string('email')->toString(),
            'token' => $token,
        ]);
    }

    public function store(AceptarInvitacionRequest $request, AceptarInvitacion $aceptar): RedirectResponse
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker(EnviarInvitacion::BROKER);

        $resultado = $broker->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (CanResetPassword $cuenta, string $password) use ($aceptar): void {
                if (! $cuenta instanceof User) {
                    return;
                }

                try {
                    $aceptar($cuenta, $password);
                } catch (OperacionDeCuentaNoPermitida $error) {
                    throw ValidationException::withMessages(['email' => $error->getMessage()]);
                }
            },
        );

        if ($resultado !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'El enlace no es válido o ha caducado. Pide que te reenvíen la invitación.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Contraseña fijada. Ya puedes entrar.');
    }
}
