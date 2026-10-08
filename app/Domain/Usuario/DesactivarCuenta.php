<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Persona\Models\Persona;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;
use App\Domain\Usuario\Models\CuentaSistema;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

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
 *
 * **Con quien administra la plataforma no desactiva: le saca de la
 * organización** (punto 45). La cuenta es también la de la plataforma, y un
 * cliente no puede dejar fuera de Statera a quien la administra. Pierde el
 * rol, el alcance y el vínculo con su persona en esta organización, y deja de
 * ver nada de ella; lo que hizo sigue a su nombre.
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

        if ($cuenta->esPlataforma()) {
            $this->sacarDeLaOrganizacion($cuenta, $motivo);

            return;
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

    private function sacarDeLaOrganizacion(User $cuenta, ?string $motivo): void
    {
        $organizacionId = $cuenta->organizacion_id;

        DB::transaction(function () use ($cuenta, $motivo, $organizacionId): void {
            // La traza, antes de soltar la organización: el evento es suyo, y
            // sin `organizacion_id` no tendría dónde escribirse.
            $this->traza->evento(
                $cuenta,
                AccionAuditada::Actualizado,
                ['organizacion_id' => $organizacionId, 'rol' => $cuenta->rol()?->value],
                ['organizacion_id' => null, 'rol' => null, 'motivo_desactivacion' => $motivo],
            );

            DB::table('model_has_roles')
                ->where('model_type', $cuenta->getMorphClass())
                ->where('model_id', $cuenta->id)
                ->where('organizacion_id', $organizacionId)
                ->delete();

            CuentaSistema::query()->where('user_id', $cuenta->id)->delete();
            // Una a una y no con un `update` masivo: así cada persona deja su
            // evento en la traza.
            Persona::query()->where('user_id', $cuenta->id)->get()
                ->each(static fn (Persona $persona): bool => $persona->forceFill(['user_id' => null])->save());

            $cuenta->forceFill(['organizacion_id' => null])->save();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
