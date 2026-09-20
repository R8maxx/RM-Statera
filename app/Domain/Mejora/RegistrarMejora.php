<?php

declare(strict_types=1);

namespace App\Domain\Mejora;

use App\Domain\Mejora\Models\Mejora;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta una oportunidad de mejora, con su primera transición.
 *
 * Las dos cosas de siempre, y ninguna puede quedarse fuera del camino: el
 * `refresh()` —porque `estado` y `origen` los pone la base con su valor por
 * defecto y la instancia recién creada llega sin ellos— y la transición de alta
 * con `estado_anterior` nulo.
 *
 * Es el mismo patrón que `RegistrarNoConformidad`, `RegistrarObjetivo`,
 * `RegistrarAuditoria` y `CrearTarea`.
 */
final class RegistrarMejora
{
    public function __construct(private readonly RegistroTransicionesMejora $registro) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(array $atributos, ?User $usuario = null, ?string $nota = null): Mejora
    {
        return DB::transaction(function () use ($atributos, $usuario, $nota): Mejora {
            $mejora = Mejora::query()->create($atributos);
            $mejora->refresh();

            $this->registro->registrar($mejora, null, $mejora->estado, $usuario, $nota);

            return $mejora;
        });
    }
}
