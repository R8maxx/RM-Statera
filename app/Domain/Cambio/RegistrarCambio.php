<?php

declare(strict_types=1);

namespace App\Domain\Cambio;

use App\Domain\Cambio\Models\CambioSgsi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta un cambio del SGSI, con su primera transición.
 *
 * Siempre como propuesto: la firma y el plazo llegan por su transición, que es
 * la que los comprueba. Mismo patrón que `RegistrarMejora` y
 * `RegistrarObjetivo`, con el `refresh()` que trae los valores por defecto de la
 * base.
 */
final class RegistrarCambio
{
    public function __construct(private readonly RegistroTransicionesCambio $registro) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(array $atributos, ?User $usuario = null): CambioSgsi
    {
        return DB::transaction(function () use ($atributos, $usuario): CambioSgsi {
            unset($atributos['estado']);

            $cambio = CambioSgsi::query()->create($atributos);
            $cambio->refresh();

            $this->registro->registrar($cambio, null, $cambio->estado, $usuario);

            return $cambio;
        });
    }
}
