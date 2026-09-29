<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A quién le llega el resumen diario de vencimientos.
 *
 * **El responsable de seguridad, que es quien responde de que la prueba
 * exista, y sólo si no lo ha apagado en su cuenta.** El técnico ve sus
 * evidencias en la herramienta; recibir además el resumen de toda la
 * organización es ruido para él y nadie lee un correo que casi nunca le toca.
 *
 * Vive aquí y no en el comando porque la pregunta se hace desde dos sitios: el
 * comando, para mandarlo, y «Mi cuenta», para decir si te llega. Con la regla
 * escrita dos veces, la pantalla diría «te llega» a quien el comando salta.
 */
final class DestinatariosDelResumen
{
    public function __construct(private readonly ContextoOrganizacion $contexto) {}

    /** @return Collection<int, User> */
    public function de(Organizacion $organizacion): Collection
    {
        // Dentro del contexto porque los roles de spatie van por «team»: fuera
        // de él, `role()` miraría los de otra organización o los de ninguna.
        return $this->contexto->paraOrganizacion($organizacion, fn () => User::query()
            ->where('organizacion_id', $organizacion->id)
            ->where('avisos_por_correo', true)
            ->whereNull('desactivada_en')
            ->role(Rol::ResponsableSeguridad->value)
            ->get());
    }

    /** Si su rol es de los que lo reciben, lo tenga encendido o no. */
    public static function leCorresponde(User $cuenta): bool
    {
        return $cuenta->rol() === Rol::ResponsableSeguridad;
    }
}
