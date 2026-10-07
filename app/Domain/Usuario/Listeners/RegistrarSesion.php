<?php

declare(strict_types=1);

namespace App\Domain\Usuario\Listeners;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\TrazaPlataforma;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Models\User;
use Closure;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * El registro de sesiones del § 6: quién entró, quién salió y quién lo intentó.
 *
 * **Se escribe dentro de la organización de la cuenta**, no en la del
 * contexto: al entrar todavía no hay contexto —el middleware que lo fija corrió
 * antes de que hubiera usuario— y la traza está bajo RLS. `paraOrganizacion()`
 * lo pone y lo quita, y no deja nada puesto para el resto de la petición.
 *
 * El intento fallido sólo se registra cuando el correo es de una cuenta, que
 * es cuando se sabe de qué organización es. Un correo que no existe no tiene
 * dueño a quien anotárselo, y guardarlo en otro sitio sería guardar correos
 * ajenos que alguien tecleó mal.
 */
final class RegistrarSesion
{
    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly RegistroTraza $traza,
        private readonly TrazaPlataforma $trazaPlataforma,
    ) {}

    public function alEntrar(Login $evento): void
    {
        if (! $evento->user instanceof User) {
            return;
        }

        $cuenta = $evento->user;

        $this->anotar($cuenta, AccionAuditada::InicioSesion, function () use ($cuenta): void {
            // Sin eventos: la última entrada no es un cambio de la cuenta, y el
            // propio inicio de sesión ya queda en la traza.
            $cuenta->forceFill(['ultimo_acceso_en' => now()])->saveQuietly();
        });
    }

    public function alSalir(Logout $evento): void
    {
        if ($evento->user instanceof User) {
            $this->anotar($evento->user, AccionAuditada::CierreSesion);
        }
    }

    public function alFallar(Failed $evento): void
    {
        if ($evento->user instanceof User) {
            $this->anotar($evento->user, AccionAuditada::IntentoFallido);
        }
    }

    private function anotar(User $cuenta, AccionAuditada $accion, ?Closure $antes = null): void
    {
        // Quien administra la plataforma no tiene tenant donde anotarlo: va a
        // la traza de la plataforma (punto 41).
        if ($cuenta->esPlataforma()) {
            if ($antes !== null) {
                $antes();
            }

            $this->trazaPlataforma->registrar(match ($accion) {
                AccionAuditada::InicioSesion => AccionPlataforma::InicioSesion,
                AccionAuditada::CierreSesion => AccionPlataforma::CierreSesion,
                default => AccionPlataforma::IntentoFallido,
            }, null, [], $cuenta->id);

            return;
        }

        if ($cuenta->organizacion_id === null) {
            return;
        }

        $this->contexto->paraOrganizacion($cuenta->organizacion_id, function () use ($cuenta, $accion, $antes): void {
            if ($antes !== null) {
                $antes();
            }

            $this->traza->evento($cuenta, $accion);
        });
    }
}
