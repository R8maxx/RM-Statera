<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad;

use App\Domain\NoConformidad\Models\NoConformidad;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta una no conformidad, con su primera transición.
 *
 * Dos cosas, y ninguna puede quedarse fuera del camino:
 *
 * 1. **El `refresh()`**, que es lo mismo que hacen `CrearTarea`,
 *    `GenerarDocumento::encolar()` y `RegistrarAuditoria`: `estado` y `origen` los
 *    pone la base con su valor por defecto —repetirlos en el modelo sería el mismo
 *    dato en dos sitios que pueden desincronizarse—, así que la instancia recién
 *    creada llega **sin estado**, y lo primero que lo lee revienta con un «call to
 *    a member function on null» que no menciona la palabra «estado».
 *
 * 2. **La transición de alta**, con `estado_anterior` nulo. «¿Desde cuándo está
 *    abierta?» es la primera pregunta que se hace sobre una no conformidad, y sin
 *    esta fila se contestaría con `created_at`, que es cuándo alguien la escribió
 *    en Statera y no cuándo se abrió.
 *
 * Las dos en la misma transacción: un alta sin su fila de histórico sería un
 * agujero en la traza desde el primer segundo (invariante 7).
 */
final class RegistrarNoConformidad
{
    public function __construct(private readonly RegistroTransicionesNoConformidad $registro) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(array $atributos, ?User $usuario = null, ?string $nota = null): NoConformidad
    {
        return DB::transaction(function () use ($atributos, $usuario, $nota): NoConformidad {
            $noConformidad = NoConformidad::query()->create($atributos);
            $noConformidad->refresh();

            $this->registro->registrar($noConformidad, null, $noConformidad->estado, $usuario, $nota);

            return $noConformidad;
        });
    }
}
