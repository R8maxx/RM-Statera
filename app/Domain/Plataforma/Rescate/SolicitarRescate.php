<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Rescate;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\AdministradoresDePlataforma;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\CapacidadPlataforma;
use App\Domain\Plataforma\Enums\EstadoSolicitud;
use App\Domain\Plataforma\Enums\TipoRescate;
use App\Domain\Plataforma\Excepciones\RescateNoPermitido;
use App\Domain\Plataforma\Models\SolicitudPlataforma;
use App\Domain\Plataforma\TrazaPlataforma;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pide un rescate de cuenta (punto 52): restablecer el segundo factor de una
 * cuenta o designar un nuevo responsable de seguridad.
 *
 * Pedir no cambia nada: deja la solicitud, con la verificación escrita de quién
 * lo pidió y cómo se comprobó, para que la ejecute otra persona
 * (`ResolverRescate`).
 *
 * **Nunca sobre una cuenta de la plataforma**, aunque sea además de esta
 * organización: su llave abre también la plataforma, y eso se gestiona en la
 * lista de administradores, no desde un cliente.
 */
final class SolicitarRescate
{
    public function __construct(
        private readonly TrazaPlataforma $traza,
        private readonly AdministradoresDePlataforma $administradores,
    ) {}

    /**
     * @param  array{nombre?: string, email?: string}  $datos  del responsable nuevo, si no hay cuenta
     */
    public function __invoke(
        User $administrador,
        Organizacion $organizacion,
        TipoRescate $tipo,
        ?User $cuenta,
        array $datos,
        string $verificacion,
    ): SolicitudPlataforma {
        if (! $administrador->puedeEnPlataforma(CapacidadPlataforma::CuentasRescatar)) {
            throw RescateNoPermitido::sinCapacidad();
        }

        $this->comprobar($organizacion, $tipo, $cuenta, $datos);

        return DB::transaction(function () use ($administrador, $organizacion, $tipo, $cuenta, $datos, $verificacion): SolicitudPlataforma {
            $solicitud = SolicitudPlataforma::query()->create([
                'organizacion_afectada_id' => $organizacion->id,
                'tipo' => $tipo->value,
                'cuenta_id' => $cuenta?->id,
                'datos' => $cuenta === null ? $datos : null,
                'verificacion' => $verificacion,
                'solicitada_por' => $administrador->id,
                'solicitada_en' => Carbon::now(),
                'estado' => EstadoSolicitud::Pendiente->value,
                // Se fija ahora y no al ejecutar: si no, quien pide podría
                // retirar a la otra persona y ejecutarla sola.
                'requiere_segunda_persona' => $this->administradores->quedaOtro($administrador),
            ]);

            $this->traza->registrar(AccionPlataforma::RescateSolicitado, $organizacion, [
                'solicitud' => $solicitud->id,
                'tipo' => $tipo->value,
                'cuenta' => $cuenta->email ?? ($datos['email'] ?? null),
            ]);

            return $solicitud;
        });
    }

    /**
     * @param  array{nombre?: string, email?: string}  $datos
     */
    private function comprobar(Organizacion $organizacion, TipoRescate $tipo, ?User $cuenta, array $datos): void
    {
        if ($cuenta !== null) {
            if ($cuenta->organizacion_id !== $organizacion->id) {
                throw RescateNoPermitido::cuentaAjena();
            }

            if ($cuenta->esPlataforma()) {
                throw RescateNoPermitido::cuentaDePlataforma();
            }
        }

        if ($tipo === TipoRescate::RestablecerSegundoFactor && $cuenta === null) {
            throw RescateNoPermitido::cuentaAjena();
        }

        if ($tipo === TipoRescate::DesignarResponsable) {
            if ($cuenta === null && (($datos['nombre'] ?? '') === '' || ($datos['email'] ?? '') === '')) {
                throw RescateNoPermitido::sinDatosDelResponsable();
            }

            if ($cuenta !== null && $cuenta->rol() === Rol::ResponsableSeguridad) {
                throw RescateNoPermitido::yaEsResponsable();
            }
        }
    }
}
