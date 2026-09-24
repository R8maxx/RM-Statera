<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;
use App\Domain\Usuario\Notifications\InvitacionACuenta;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;

/**
 * Manda (o vuelve a mandar) el enlace para fijar la contraseña.
 *
 * Reenviar genera un token nuevo, y el broker guarda uno por correo: el
 * enlace anterior deja de servir, que es lo que se quiere cuando alguien dice
 * «no me ha llegado» y luego aparecen los dos.
 */
final class EnviarInvitacion
{
    public const BROKER = 'invitaciones';

    public function __invoke(User $cuenta): void
    {
        if ($cuenta->estadoCuenta() !== EstadoCuenta::Invitada) {
            throw OperacionDeCuentaNoPermitida::yaAceptada();
        }

        /** @var PasswordBroker $broker */
        $broker = Password::broker(self::BROKER);
        $token = $broker->createToken($cuenta);

        $cuenta->forceFill(['invitada_en' => now()])->save();

        $cuenta->notify(new InvitacionACuenta(
            enlace: route('invitacion.show', ['token' => $token, 'email' => $cuenta->email]),
            organizacion: (string) $cuenta->organizacion?->nombre,
            rol: mb_strtolower($cuenta->rol()?->etiqueta() ?? 'sin rol'),
            dias: self::diasDeValidez(),
        ));
    }

    public static function diasDeValidez(): int
    {
        return intdiv((int) config('auth.passwords.'.self::BROKER.'.expire'), 60 * 24);
    }
}
