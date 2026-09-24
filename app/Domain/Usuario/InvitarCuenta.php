<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Persona\Models\Persona;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Da de alta una cuenta y le manda la invitación (§ 4.19).
 *
 * **Sin registro self-service**, que es una exclusión de alcance: la única
 * puerta de entrada a Statera es que un responsable de seguridad invite a
 * alguien. La contraseña inicial es aleatoria y nadie la conoce, así que hasta
 * aceptar la invitación la cuenta no entra — y `EstadoCuenta` además lo impide
 * por `activada_en`, que son dos cerrojos y no uno.
 *
 * La traza se escribe a mano porque `User` no lleva `RegistraTraza`: `users`
 * está fuera de las tres capas y se crea también desde seeders y tests sin
 * contexto, donde el evento no tendría dónde escribirse.
 */
final class InvitarCuenta
{
    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly AlcanceDeCuenta $alcance,
        private readonly VincularPersona $vincular,
        private readonly EnviarInvitacion $enviar,
        private readonly RegistroTraza $traza,
    ) {}

    /**
     * @param  list<int>  $sistemas
     */
    public function __invoke(
        string $nombre,
        string $email,
        Rol $rol,
        array $sistemas = [],
        ?Carbon $accesoHasta = null,
        ?Persona $persona = null,
    ): User {
        $cuenta = DB::transaction(function () use ($nombre, $email, $rol, $sistemas, $accesoHasta, $persona): User {
            $cuenta = User::query()->create([
                'name' => $nombre,
                'email' => $email,
                'password' => Str::password(64),
                'organizacion_id' => $this->contexto->idObligatorio(),
            ]);

            $cuenta->forceFill(['invitada_en' => now(), 'activada_en' => null])->save();
            $cuenta->syncRoles([$rol->value]);

            $this->traza->evento($cuenta, AccionAuditada::Creado, null, [
                'name' => $cuenta->name,
                'email' => $cuenta->email,
                'rol' => $rol->value,
            ]);

            $this->alcance->fijar($cuenta, $rol, $sistemas, $accesoHasta);

            if ($persona !== null) {
                ($this->vincular)($cuenta, $persona);
            }

            return $cuenta;
        });

        ($this->enviar)($cuenta);

        return $cuenta;
    }
}
