<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Autorizacion\SembrarRoles;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hace a quien administra la plataforma usuario de una organización, con su
 * cuenta de siempre (punto 45).
 *
 * **Sólo lo hace la plataforma**: al dar de alta una organización con un
 * administrador como responsable, o por consola. Un cliente no puede: si su
 * responsable invitara el correo de un administrador y eso le uniera, cualquier
 * cliente podría meter a un administrador en su organización sin que éste lo
 * aceptara, y la respuesta le confirmaría de quién es ese correo. Desde el
 * cliente, ese correo es uno más que ya está en uso.
 *
 * No hay invitación que mandar: ya tiene contraseña y segundo factor.
 */
final class UnirAdministrador
{
    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly SembrarRoles $roles,
        private readonly RegistroTraza $traza,
        private readonly TrazaPlataforma $trazaPlataforma,
    ) {}

    /** El administrador libre con ese correo, o nulo si no lo hay. */
    public static function libreCon(string $email): ?User
    {
        return User::query()
            ->whereNull('organizacion_id')
            ->where('es_plataforma', true)
            ->where('email', $email)
            ->first();
    }

    public function __invoke(User $administrador, Organizacion $organizacion, Rol $rol): User
    {
        if (! $administrador->esPlataforma() || $administrador->organizacion_id !== null) {
            throw OperacionDeCuentaNoPermitida::correoEnUso();
        }

        if ($rol === Rol::Auditor) {
            throw OperacionDeCuentaNoPermitida::plataformaComoAuditor();
        }

        return $this->contexto->paraOrganizacion($organizacion, function () use ($administrador, $organizacion, $rol): User {
            return DB::transaction(function () use ($administrador, $organizacion, $rol): User {
                $this->roles->paraOrganizacion($organizacion);

                $administrador->forceFill(['organizacion_id' => $organizacion->id])->save();
                $administrador->syncRoles([$rol->value]);

                $this->traza->evento($administrador, AccionAuditada::Creado, null, [
                    'name' => $administrador->name,
                    'email' => $administrador->email,
                    'rol' => $rol->value,
                    'plataforma' => true,
                ]);

                $this->trazaPlataforma->registrar(AccionPlataforma::AdministradorPromovido, $organizacion, [
                    'email' => $administrador->email,
                    'rol' => $rol->value,
                ]);

                return $administrador;
            });
        });
    }
}
