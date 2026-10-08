<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\CapacidadPlataforma;
use App\Domain\Plataforma\Enums\EstadoSolicitud;
use App\Domain\Plataforma\Models\SolicitudPlataforma;
use App\Domain\Usuario\EnviarInvitacion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Lo que requiere atención hoy en la plataforma (punto 53).
 *
 * Cada cifra es una pregunta con acción detrás, y cada una enlaza a la lista
 * filtrada que la contesta. **Lee sólo tablas fuera de RLS**: organizaciones,
 * planes, users y solicitudes. Nada de lo que cada cliente guarda dentro.
 */
final class CuadroDeMando
{
    /**
     * **Cada cifra se calcula sólo para quien puede actuar sobre ella.** Los
     * rescates pendientes son de Administración: a Gestión comercial no se le
     * calcula ni se le manda, y no basta con esconder la tarjeta, porque la
     * cifra viajaría igual en los props. Lo encontró la revisión de seguridad.
     *
     * @return array{clientes: int, vencenPronto: int, enGracia: int, enSoloLectura: int, sobreSuPlan: int, deBaja: int, soporteAbierto: int, invitacionesCaducadas: int, rescatesPendientes: ?int}
     */
    public function cifras(User $quien): array
    {
        return [
            'clientes' => Organizacion::query()->whereNull('baja_en')->count(),
            'vencenPronto' => Organizacion::query()->vencePronto()->count(),
            'enGracia' => Organizacion::query()->enGracia()->count(),
            'enSoloLectura' => Organizacion::query()->enSoloLectura()->count(),
            'sobreSuPlan' => Organizacion::query()->sobreSuPlan()->count(),
            'deBaja' => Organizacion::query()->deBaja()->count(),
            'soporteAbierto' => Organizacion::query()->conSoporteAbierto()->count(),
            'invitacionesCaducadas' => $this->invitacionesCaducadas(),
            'rescatesPendientes' => $quien->puedeEnPlataforma(CapacidadPlataforma::CuentasRescatar) ? $this->rescatesPendientes() : null,
        ];
    }

    /**
     * Invitaciones de clientes que nadie aceptó y cuyo enlace ya no sirve. La
     * del primer responsable es la que más importa: sin ella el cliente no
     * tiene a nadie dentro.
     */
    private function invitacionesCaducadas(): int
    {
        return User::query()
            ->whereNotNull('organizacion_id')
            ->whereNull('activada_en')
            ->whereNull('desactivada_en')
            ->where('invitada_en', '<', Carbon::now()->subDays(EnviarInvitacion::diasDeValidez()))
            ->count();
    }

    private function rescatesPendientes(): int
    {
        return SolicitudPlataforma::query()
            ->where('estado', EstadoSolicitud::Pendiente->value)
            ->where('solicitada_en', '>=', Carbon::now()->subHours(SolicitudPlataforma::HORAS_DE_VALIDEZ))
            ->count();
    }
}
