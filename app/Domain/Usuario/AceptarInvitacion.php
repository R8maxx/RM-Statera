<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * La cuenta fija su contraseña y pasa a estar activa.
 *
 * Corre **sin sesión y sin contexto**: quien acepta todavía no ha entrado. La
 * traza, en cambio, vive bajo RLS, así que el evento se escribe dentro de la
 * organización de la propia cuenta —y sólo de ella—.
 */
final class AceptarInvitacion
{
    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly RegistroTraza $traza,
    ) {}

    public function __invoke(User $cuenta, string $password): void
    {
        if ($cuenta->estadoCuenta() !== EstadoCuenta::Invitada || $cuenta->organizacion_id === null) {
            throw OperacionDeCuentaNoPermitida::yaAceptada();
        }

        $this->contexto->paraOrganizacion($cuenta->organizacion_id, function () use ($cuenta, $password): void {
            $cuenta->forceFill([
                'password' => Hash::make($password),
                'activada_en' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $this->traza->evento($cuenta, AccionAuditada::Actualizado, ['activada_en' => null], [
                'activada_en' => $cuenta->activada_en?->toIso8601String(),
            ]);
        });
    }
}
