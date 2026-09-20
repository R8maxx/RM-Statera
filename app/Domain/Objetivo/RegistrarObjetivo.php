<?php

declare(strict_types=1);

namespace App\Domain\Objetivo;

use App\Domain\Objetivo\Models\Objetivo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta un objetivo de seguridad, con su primera transición.
 *
 * Dos cosas, y ninguna puede quedarse fuera del camino:
 *
 * 1. **El `refresh()`**, que es lo mismo que hacen `CrearTarea`,
 *    `RegistrarAuditoria`, `RegistrarNoConformidad` y `GenerarDocumento::encolar()`:
 *    `estado` lo pone la base con su valor por defecto —repetirlo en el modelo
 *    sería el mismo dato en dos sitios que pueden desincronizarse—, así que la
 *    instancia recién creada llega **sin estado**, y lo primero que lo lee
 *    revienta con un «call to a member function on null» que no menciona la
 *    palabra «estado».
 *
 * 2. **La transición de alta**, con `estado_anterior` nulo.
 *
 * Un objetivo nace siempre **propuesto**: aprobar es un gesto aparte con su firma,
 * y dejar crear uno ya aprobado permitiría saltarse la única decisión del módulo
 * que es de dirección. Es la misma razón por la que una versión de documento no
 * nace numerada.
 */
final class RegistrarObjetivo
{
    public function __construct(private readonly RegistroTransicionesObjetivo $registro) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(array $atributos, ?User $usuario = null, ?string $nota = null): Objetivo
    {
        return DB::transaction(function () use ($atributos, $usuario, $nota): Objetivo {
            $objetivo = Objetivo::query()->create($atributos);
            $objetivo->refresh();

            $this->registro->registrar($objetivo, null, $objetivo->estado, $usuario, $nota);

            return $objetivo;
        });
    }
}
