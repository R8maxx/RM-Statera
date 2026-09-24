<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Domain\Autorizacion\Enums\Rol;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * El técnico entra a sus tareas y a sus implantaciones (§ 4.19).
 *
 * **Una vez por sesión, y sólo si la URL no trae ningún filtro.** La primera
 * visita redirige a la misma tabla con «Responsable: yo» puesto, que se ve en
 * la barra de filtros y se quita como cualquier otro. Si se quita, no vuelve:
 * la marca de sesión dice que ya se le ofreció, y una tabla que se re-filtra
 * sola cada vez que alguien limpia los filtros es una tabla que no se puede
 * ver entera.
 *
 * **Y sólo si tiene algo suyo.** Un técnico sin nada asignado entraría a una
 * tabla vacía que dice «Ningún resultado con estos filtros», que como punto de
 * partida es peor que la tabla entera. Lo destapó el recorrido en el navegador:
 * la técnica sembrada tiene tareas y ninguna implantación.
 *
 * Es un punto de partida y no una restricción: el técnico lee todo. Lo que
 * escribe lo decide `EscribeLoSuyo`.
 */
trait EmpiezaPorLoMio
{
    /**
     * @param  Closure(User): bool  $tieneAlgo  Si la cuenta tiene al menos un registro a su cargo.
     */
    protected function empezarPorLoMio(Request $request, string $clave, Closure $tieneAlgo): ?RedirectResponse
    {
        /** @var ?User $usuario */
        $usuario = $request->user();
        $marca = "statera.lo_mio.{$clave}";

        if ($usuario === null
            || $request->has('filter')
            || $request->header('X-Inertia-Partial-Data') !== null
            || $request->session()->get($marca) === true
            || $usuario->rol() !== Rol::Tecnico) {
            return null;
        }

        $request->session()->put($marca, true);

        if (! $tieneAlgo($usuario)) {
            return null;
        }

        return redirect()->to($request->url().'?'.http_build_query([
            ...$request->query(),
            'filter' => ['responsable_id' => (string) $usuario->id],
        ]));
    }
}
