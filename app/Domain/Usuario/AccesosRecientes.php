<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Las últimas veces que se entró —o se intentó— con esta cuenta.
 *
 * **No hay registro propio: se lee la traza.** `RegistrarSesion` ya anota cada
 * entrada y cada contraseña mala con su IP en `eventos_auditoria`, y esa tabla
 * es inmutable, que es justo lo que se quiere de un registro de accesos. Otra
 * tabla sería una segunda copia que sí se podría tocar.
 *
 * La traza está bajo RLS, así que esto corre dentro del contexto de la
 * organización de la cuenta, que en una petición ya está puesto.
 */
final class AccesosRecientes
{
    /**
     * @return list<array{fecha: string, ip: ?string, correcto: bool}>
     */
    public function de(User $cuenta, int $cuantos = 10): array
    {
        return $this->eventos($cuenta, [AccionAuditada::InicioSesion, AccionAuditada::IntentoFallido])
            ->limit($cuantos)
            ->get()
            ->map(static fn (EventoAuditoria $evento): array => [
                'fecha' => $evento->created_at->toIso8601String(),
                'ip' => $evento->ip,
                'correcto' => $evento->accion === AccionAuditada::InicioSesion,
            ])
            ->all();
    }

    /**
     * La entrada de antes de ésta, y las contraseñas malas desde entonces.
     *
     * La más reciente es la de la sesión actual —también la que entra por
     * «recordarme», que dispara el mismo evento—, así que la que se enseña es la
     * segunda. Es la que delata un acceso que no has hecho tú: la tuya ya sabes
     * cuándo fue.
     *
     * @return array{fecha: string, ip: ?string, fallidosDesde: int}|null
     */
    public function entradaAnterior(User $cuenta): ?array
    {
        $anterior = $this->eventos($cuenta, [AccionAuditada::InicioSesion])->skip(1)->first();

        if ($anterior === null) {
            return null;
        }

        return [
            'fecha' => $anterior->created_at->toIso8601String(),
            'ip' => $anterior->ip,
            'fallidosDesde' => $this->eventos($cuenta, [AccionAuditada::IntentoFallido])
                ->where('created_at', '>', $anterior->created_at)
                ->count(),
        ];
    }

    /**
     * @param  list<AccionAuditada>  $acciones
     * @return Builder<EventoAuditoria>
     */
    private function eventos(User $cuenta, array $acciones): Builder
    {
        return EventoAuditoria::query()
            ->where('entidad', class_basename(User::class))
            ->where('entidad_id', $cuenta->id)
            ->whereIn('accion', array_map(static fn (AccionAuditada $accion): string => $accion->value, $acciones))
            ->latest('created_at')
            ->latest('id');
    }
}
