<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Desactiva una cuenta. **No la borra**: la fila es autora, responsable y
 * firmante en todo el histórico, y borrarla dejaría la traza sin nombre.
 *
 * Cierra además las tres puertas por las que la cuenta podría seguir dentro:
 *
 * 1. **Las sesiones abiertas**, que se borran de la tabla. `CuentaVigente` las
 *    cerraría igual en la siguiente petición, pero borrarlas no espera a que
 *    esa petición llegue.
 * 2. **El «recordarme»**, cambiando el `remember_token`: la cookie vieja deja de
 *    casar con nada.
 * 3. **La invitación pendiente**, si la había: un enlace en un correo que
 *    todavía sirve es una cuenta que todavía entra.
 */
final class DesactivarCuenta
{
    public function __construct(
        private readonly ResponsablesDeSeguridad $responsables,
        private readonly RegistroTraza $traza,
    ) {}

    public function __invoke(User $quien, User $cuenta, ?string $motivo = null): void
    {
        if ($cuenta->is($quien)) {
            throw OperacionDeCuentaNoPermitida::sobreSiMisma();
        }

        if ($cuenta->rol() === Rol::ResponsableSeguridad && ! $this->responsables->quedaOtro($cuenta)) {
            throw OperacionDeCuentaNoPermitida::ultimoResponsable();
        }

        DB::transaction(function () use ($cuenta, $motivo): void {
            $cuenta->forceFill([
                'desactivada_en' => now(),
                'motivo_desactivacion' => $motivo,
                'remember_token' => Str::random(60),
            ])->save();

            if (config('session.driver') === 'database') {
                DB::table((string) config('session.table', 'sessions'))->where('user_id', $cuenta->id)->delete();
            }

            /** @var PasswordBroker $broker */
            $broker = Password::broker(EnviarInvitacion::BROKER);
            $broker->deleteToken($cuenta);

            $this->traza->evento(
                $cuenta,
                AccionAuditada::Actualizado,
                ['desactivada_en' => null],
                ['desactivada_en' => $cuenta->desactivada_en?->toIso8601String(), 'motivo_desactivacion' => $motivo],
            );
        });
    }
}
