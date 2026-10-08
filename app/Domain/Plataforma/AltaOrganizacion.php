<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Autorizacion\SembrarRoles;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Excepciones\AltaNoPermitida;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Traza\RegistroTraza;
use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\EnviarInvitacion;
use App\Domain\Usuario\InvitarCuenta;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta una organización cliente con su primer responsable (punto 41).
 *
 * Es **la única receta** para que exista un tenant: la usan la plataforma y el
 * seeder de desarrollo. Hasta aquí una organización sólo nacía en el seeder, y
 * el primer responsable no tenía camino de entrada, porque invitar exige que
 * ya haya alguien que invite.
 *
 * **Lo que deja hecho es lo mínimo para entrar**: la fila, los roles de su
 * «team» y la invitación del responsable de seguridad. Todo lo demás —la ficha
 * completa, el sistema, la valoración de las cinco dimensiones— lo hace el
 * propio cliente desde la interfaz, porque la aplicabilidad se deriva de lo que
 * él valora y no la decide nadie por él (invariante 4).
 *
 * **No cruza organizaciones**: crea la fila y entra en ella con
 * `paraOrganizacion()`, igual que un comando que visita tenants de uno en uno.
 * No usa `comoMantenimiento()`, que sigue prohibido en una petición web.
 *
 * El alta queda en las dos trazas: en la de la plataforma, porque es lo que
 * hizo un administrador, y en la del tenant, porque el cliente tiene que poder
 * ver desde cuándo existe y quién lo creó. La segunda se escribe a mano porque
 * `Organizacion` sólo registra `updated` (ver su `booted()`).
 */
final class AltaOrganizacion
{
    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly SembrarRoles $roles,
        private readonly InvitarCuenta $invitar,
        private readonly EnviarInvitacion $enviarInvitacion,
        private readonly RegistroTraza $traza,
        private readonly TrazaPlataforma $trazaPlataforma,
        private readonly CambiarSuscripcion $suscripcion,
        private readonly UnirAdministrador $unir,
    ) {}

    /**
     * @param  array{nombre: string, cif?: ?string, razon_social?: ?string, sector?: ?string}  $ficha
     */
    public function __invoke(
        array $ficha,
        string $nombreResponsable,
        string $emailResponsable,
        ?Plan $plan = null,
        ?Carbon $venceEn = null,
    ): Organizacion {
        if (! Marco::query()->where('codigo', 'ENS-RD311-2022')->exists()) {
            throw AltaNoPermitida::catalogoSinImportar();
        }

        [$organizacion, $responsable] = DB::transaction(function () use ($ficha, $nombreResponsable, $emailResponsable, $plan, $venceEn): array {
            $organizacion = Organizacion::query()->create([
                'nombre' => $ficha['nombre'],
                'cif' => $ficha['cif'] ?? null,
                'razon_social' => $ficha['razon_social'] ?? null,
                'sector' => $ficha['sector'] ?? null,
                'activa' => true,
            ]);

            $responsable = $this->contexto->paraOrganizacion(
                $organizacion,
                function () use ($organizacion, $nombreResponsable, $emailResponsable): User {
                    $this->roles->paraOrganizacion($organizacion);
                    $this->traza->creado($organizacion);

                    // Si el responsable es alguien de la plataforma, se le une con
                    // su cuenta en vez de invitarle (punto 45).
                    $administrador = UnirAdministrador::libreCon($emailResponsable);

                    return $administrador !== null
                        ? ($this->unir)($administrador, $organizacion, Rol::ResponsableSeguridad)
                        : ($this->invitar)($nombreResponsable, $emailResponsable, Rol::ResponsableSeguridad, enviar: false);
                },
            );

            if ($plan !== null) {
                ($this->suscripcion)($organizacion, $plan, $venceEn, 'Alta de la organización');
            }

            $this->trazaPlataforma->registrar(AccionPlataforma::OrganizacionAlta, $organizacion, [
                'nombre' => $organizacion->nombre,
                'cif' => $organizacion->cif,
                'responsable' => $responsable->email,
            ]);

            return [$organizacion, $responsable];
        });

        // El correo, después de confirmar: si la transacción se deshace, nadie
        // recibe un enlace a una cuenta que no existe. Y sólo si hay algo que
        // aceptar: si el responsable es alguien de la plataforma, ya tenía
        // cuenta y se le ha añadido con la suya (punto 45).
        if ($responsable->estadoCuenta() === EstadoCuenta::Invitada) {
            ($this->enviarInvitacion)($responsable);
        }

        return $organizacion;
    }
}
