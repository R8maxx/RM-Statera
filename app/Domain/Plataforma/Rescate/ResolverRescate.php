<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Rescate;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\AdministradoresDePlataforma;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\CapacidadPlataforma;
use App\Domain\Plataforma\Enums\EstadoSolicitud;
use App\Domain\Plataforma\Enums\TipoRescate;
use App\Domain\Plataforma\Excepciones\RescateNoPermitido;
use App\Domain\Plataforma\Models\SolicitudPlataforma;
use App\Domain\Plataforma\Notifications\AvisoDeRescate;
use App\Domain\Plataforma\TrazaPlataforma;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Domain\Usuario\CambiarRol;
use App\Domain\Usuario\EnviarInvitacion;
use App\Domain\Usuario\InvitarCuenta;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Ejecuta o rechaza una solicitud de rescate (punto 52), con la segunda mirada.
 *
 * **La ejecuta otra persona de Administración, no quien la pidió.** Es lo que
 * protege a la cuenta de alguien que llame haciéndose pasar por su dueño: hace
 * falta convencer a dos. La única excepción es que sólo haya una persona de
 * Administración, y entonces se marca `sin_segunda_persona`, va así a la traza
 * y lo dice el correo al cliente.
 *
 * Lo que cambia en la cuenta va a la traza del cliente, dentro de su contexto.
 */
final class ResolverRescate
{
    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly AdministradoresDePlataforma $administradores,
        private readonly TrazaPlataforma $trazaPlataforma,
        private readonly RegistroTraza $traza,
        private readonly CambiarRol $cambiarRol,
        private readonly InvitarCuenta $invitar,
        private readonly EnviarInvitacion $enviarInvitacion,
    ) {}

    public function ejecutar(User $administrador, SolicitudPlataforma $solicitud): void
    {
        $this->comprobar($administrador, $solicitud);

        $sinSegundaPersona = $solicitud->solicitada_por === $administrador->id;
        $organizacion = $solicitud->organizacion;

        $afectada = DB::transaction(function () use ($administrador, $solicitud, $organizacion, $sinSegundaPersona): User {
            $afectada = $this->contexto->paraOrganizacion($organizacion, fn (): User => match ($solicitud->tipo) {
                TipoRescate::RestablecerSegundoFactor => $this->restablecerSegundoFactor($solicitud),
                TipoRescate::DesignarResponsable => $this->designarResponsable($administrador, $solicitud),
            });

            $solicitud->forceFill([
                'estado' => EstadoSolicitud::Ejecutada->value,
                'resuelta_por' => $administrador->id,
                'resuelta_en' => Carbon::now(),
                'sin_segunda_persona' => $sinSegundaPersona,
            ])->save();

            $this->trazaPlataforma->registrar(AccionPlataforma::RescateEjecutado, $organizacion, [
                'solicitud' => $solicitud->id,
                'tipo' => $solicitud->tipo->value,
                'cuenta' => $afectada->email,
                'sin_segunda_persona' => $sinSegundaPersona,
            ]);

            return $afectada;
        });

        // Las invitaciones y los correos, al confirmar: si algo se deshace,
        // nadie recibe un enlace a una cuenta que no cambió.
        if ($afectada->estadoCuenta()->value === 'invitada') {
            ($this->enviarInvitacion)($afectada);
        }

        $this->avisar($solicitud, $organizacion, $afectada, $sinSegundaPersona);
    }

    public function rechazar(User $administrador, SolicitudPlataforma $solicitud, string $motivo): void
    {
        if (! $administrador->puedeEnPlataforma(CapacidadPlataforma::CuentasRescatar)) {
            throw RescateNoPermitido::sinCapacidad();
        }

        if ($solicitud->estado() !== EstadoSolicitud::Pendiente) {
            throw RescateNoPermitido::yaResuelta();
        }

        DB::transaction(function () use ($administrador, $solicitud, $motivo): void {
            $solicitud->forceFill([
                'estado' => EstadoSolicitud::Rechazada->value,
                'resuelta_por' => $administrador->id,
                'resuelta_en' => Carbon::now(),
                'motivo_rechazo' => $motivo,
            ])->save();

            $this->trazaPlataforma->registrar(AccionPlataforma::RescateRechazado, $solicitud->organizacion, [
                'solicitud' => $solicitud->id,
                'motivo' => $motivo,
            ]);
        });
    }

    private function comprobar(User $administrador, SolicitudPlataforma $solicitud): void
    {
        if (! $administrador->puedeEnPlataforma(CapacidadPlataforma::CuentasRescatar)) {
            throw RescateNoPermitido::sinCapacidad();
        }

        if ($solicitud->estado() !== EstadoSolicitud::Pendiente) {
            throw RescateNoPermitido::yaResuelta();
        }

        // La segunda mirada, salvo que no haya nadie más que pueda darla.
        if ($solicitud->solicitada_por === $administrador->id && $this->administradores->quedaOtro($administrador)) {
            throw RescateNoPermitido::mismaPersona();
        }
    }

    private function restablecerSegundoFactor(SolicitudPlataforma $solicitud): User
    {
        $cuenta = $this->cuentaDeLaSolicitud($solicitud);

        $cuenta->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('passkeys')->where('user_id', $cuenta->id)->delete();

        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $cuenta->id)->delete();
        }

        $this->traza->evento($cuenta, AccionAuditada::Actualizado, ['segundo_factor' => 'activo'], [
            'segundo_factor' => 'restablecido por la plataforma',
            'solicitud' => $solicitud->id,
        ]);

        return $cuenta;
    }

    private function designarResponsable(User $administrador, SolicitudPlataforma $solicitud): User
    {
        if ($solicitud->cuenta_id !== null) {
            $cuenta = $this->cuentaDeLaSolicitud($solicitud);
            ($this->cambiarRol)($administrador, $cuenta, Rol::ResponsableSeguridad);

            return $cuenta;
        }

        return ($this->invitar)(
            (string) ($solicitud->datos['nombre'] ?? ''),
            (string) ($solicitud->datos['email'] ?? ''),
            Rol::ResponsableSeguridad,
            enviar: false,
        );
    }

    /**
     * La cuenta, acotada a la organización de la solicitud: `users` está fuera
     * de las tres capas, y la solicitud pudo escribirse con otra.
     */
    private function cuentaDeLaSolicitud(SolicitudPlataforma $solicitud): User
    {
        $cuenta = User::query()
            ->where('organizacion_id', $solicitud->organizacion_afectada_id)
            ->whereKey($solicitud->cuenta_id)
            ->first();

        if ($cuenta === null) {
            throw RescateNoPermitido::cuentaAjena();
        }

        if ($cuenta->esPlataforma()) {
            throw RescateNoPermitido::cuentaDePlataforma();
        }

        return $cuenta;
    }

    private function avisar(SolicitudPlataforma $solicitud, Organizacion $organizacion, User $afectada, bool $sinSegundaPersona): void
    {
        $responsables = User::query()
            ->where('organizacion_id', $organizacion->id)
            ->whereNull('desactivada_en')
            ->whereNotNull('activada_en')
            ->get()
            ->filter(static fn (User $cuenta): bool => $cuenta->rol() === Rol::ResponsableSeguridad);

        $destinatarios = $responsables->push($afectada)->unique('id')->values();

        Notification::send($destinatarios, new AvisoDeRescate(
            $solicitud->tipo->etiqueta(),
            $organizacion->nombre,
            "{$afectada->name} ({$afectada->email})",
            $sinSegundaPersona,
        ));
    }
}
