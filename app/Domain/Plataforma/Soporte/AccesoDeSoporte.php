<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Soporte;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Excepciones\SoporteNoPermitido;
use App\Domain\Plataforma\Notifications\EntradaDeSoporte;
use App\Domain\Plataforma\TrazaPlataforma;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Quien administra la plataforma entra en un cliente como soporte, y sale
 * (punto 44).
 *
 * **Sólo por la puerta que el cliente abrió**, y sólo mientras está abierta.
 * Dentro ve lo que vería un auditor sin alcance acotado —todo permiso `.ver`—
 * y no escribe nada: lo impiden `Gate::before` y `SoporteSoloLectura`, dos
 * cerrojos y no uno.
 *
 * La entrada y la salida quedan en las dos trazas. En la del cliente, porque es
 * él quien tiene que poder decirle a su auditor quién de fuera entró y cuándo.
 * A sus responsables de seguridad además les llega un correo al entrar.
 *
 * Aquí no se toca la sesión: eso es del controlador. Esto decide si se puede y
 * deja escrito que se hizo.
 */
final class AccesoDeSoporte
{
    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly RegistroTraza $traza,
        private readonly TrazaPlataforma $trazaPlataforma,
    ) {}

    public function entrar(User $administrador, Organizacion $organizacion): void
    {
        if ($administrador->esSuOrganizacion($organizacion->id)) {
            throw SoporteNoPermitido::esLaSuya();
        }

        if (! $administrador->esPlataforma() || $organizacion->estaDeBaja() || ! $organizacion->soporteAbierto()) {
            throw SoporteNoPermitido::ventanaCerrada();
        }

        $this->anotar($administrador, $organizacion, AccionAuditada::SoporteEntrada, AccionPlataforma::SoporteEntrada);

        $hasta = $organizacion->soporte_hasta?->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?? '';

        foreach ($this->responsables($organizacion) as $responsable) {
            $responsable->notify(new EntradaDeSoporte($administrador->name, $organizacion->nombre, $hasta));
        }
    }

    public function salir(User $administrador, Organizacion $organizacion): void
    {
        $this->anotar($administrador, $organizacion, AccionAuditada::SoporteSalida, AccionPlataforma::SoporteSalida);
    }

    private function anotar(User $administrador, Organizacion $organizacion, AccionAuditada $tenant, AccionPlataforma $plataforma): void
    {
        DB::transaction(function () use ($administrador, $organizacion, $tenant, $plataforma): void {
            $this->contexto->paraOrganizacion($organizacion, fn () => $this->traza->evento($organizacion, $tenant, null, [
                'administrador' => $administrador->name,
                'email' => $administrador->email,
                'hasta' => $organizacion->soporte_hasta?->toIso8601String(),
            ]));

            $this->trazaPlataforma->registrar($plataforma, $organizacion);
        });
    }

    /**
     * Los responsables de seguridad que pueden entrar, que son quienes abrieron
     * la puerta o pueden cerrarla.
     *
     * @return list<User>
     */
    private function responsables(Organizacion $organizacion): array
    {
        return User::query()
            ->where('users.organizacion_id', $organizacion->id)
            ->whereNull('users.desactivada_en')
            ->whereNotNull('users.activada_en')
            ->get()
            ->filter(static fn (User $cuenta): bool => $cuenta->rol() === Rol::ResponsableSeguridad)
            ->values()
            ->all();
    }
}
